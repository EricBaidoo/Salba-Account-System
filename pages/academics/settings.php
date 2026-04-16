<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$current_academic_year = getAcademicYear($conn);
$current_term = getCurrentSemester($conn);

// 1. Process Academic Level Operations (New Dictionary)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_level') {
        $l_name = trim($_POST['level_name']);
        if ($l_name) {
            $stmt = $conn->prepare("INSERT IGNORE INTO academic_levels (level_name) VALUES (?)");
            $stmt->bind_param("s", $l_name);
            $stmt->execute();
            $success = "Physical Academic Level added!";
        }
    }
    if ($_POST['action'] === 'rename_level') {
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_name']);
        if ($old_name && $new_name && $old_name !== $new_name) {
            $conn->begin_transaction();
            try {
                // Update Dictionary
                $stmt = $conn->prepare("UPDATE academic_levels SET level_name = ? WHERE level_name = ?");
                $stmt->bind_param("ss", $new_name, $old_name);
                $stmt->execute();
                
                // Sync Classes
                $stmt2 = $conn->prepare("UPDATE classes SET Level = ? WHERE Level = ?");
                $stmt2->bind_param("ss", $new_name, $old_name);
                $stmt2->execute();
                
                $conn->commit();
                $success = "Institutional node renamed and synchronized!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
    if ($_POST['action'] === 'delete_level') {
        $l_name = trim($_POST['level_name']);
        $conn->query("DELETE FROM academic_levels WHERE level_name = '" . $conn->real_escape_string($l_name) . "'");
        $conn->query("UPDATE classes SET Level = NULL WHERE Level = '" . $conn->real_escape_string($l_name) . "'");
        $success = "Level purged from system dictionary.";
    }
}

// 1. Process Global Transcript Semester Report Weights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_global_weights') {
    $oa_w = floatval($_POST['oa_weight']);
    $ex_w = floatval($_POST['exam_weight']);
    
    if (($oa_w + $ex_w) == 100) {
        setSystemSetting($conn, 'term_oa_weight', $oa_w, $_SESSION['username']);
        setSystemSetting($conn, 'term_exam_weight', $ex_w, $_SESSION['username']);
        $success = "Semester Report Rules saved strictly at 100% split!";
    } else {
        $error = "Error: The Final Transcript weights must equal exactly 100% (Not ".($oa_w+$ex_w)."%)";
    }
}

// 2. Process Delete Assessment Config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_assessment') {
    $del_id = intval($_POST['delete_id']);
    $conn->query("DELETE FROM assessment_configurations WHERE id = $del_id");
    header("Location: settings.php?success=Rule+Deleted");
    exit;
}

// Get Current Totals for validation
$oa_total = 0;
$exam_total = 0;
$c_res = $conn->query("SELECT max_marks_allocation, is_exam FROM assessment_configurations WHERE academic_year = '$current_academic_year' AND semester = '$current_term'");
while($c = $c_res->fetch_assoc()) {
    if ($c['is_exam']) {
        $exam_total += floatval($c['max_marks_allocation']);
    } else {
        $oa_total += floatval($c['max_marks_allocation']);
    }
}

// 3. Process Add Assessment Config
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_assessment') {
    $a_name = trim($_POST['assessment_name']);
    $a_max = floatval($_POST['max_marks']);
    $is_exam = isset($_POST['is_exam']) ? 1 : 0;
    
    if($a_name && $a_max > 0) {
        if ($is_exam) {
            // Validate Exam Bucket (Max 100)
            if (($exam_total + $a_max) > 100) {
                $error = "Math Error: Exam configuration cannot exceed a base of 100%. You currently have $exam_total% allocated.";
            } else {
                $stmt = $conn->prepare("INSERT INTO assessment_configurations (academic_year, semester, assessment_name, max_marks_allocation, is_exam) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("sssd", $current_academic_year, $current_term, $a_name, $a_max);
                $stmt->execute();
                header("Location: settings.php?success=Exam+Configuration+Added");
                exit;
            }
        } else {
            // Validate OA Bucket (Max 100)
            if (($oa_total + $a_max) > 100) {
                $error = "Math Error: Sub-assessments (OA) must cap exactly at 100%. Adding $a_max pushes OA total to " . ($oa_total + $a_max) . "%.";
            } else {
                $stmt = $conn->prepare("INSERT INTO assessment_configurations (academic_year, semester, assessment_name, max_marks_allocation, is_exam) VALUES (?, ?, ?, ?, 0)");
                $stmt->bind_param("sssd", $current_academic_year, $current_term, $a_name, $a_max);
                $stmt->execute();
                header("Location: settings.php?success=SBA+Assessment+Added");
                exit;
            }
        }
    }
}

if(isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// 4. Process Class Subject Mapping (Migrated to subjects.php)

// 5. Process Class Level Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_class_levels') {
    if (!empty($_POST['levels'])) {
        $count = 0;
        $level_stmt = $conn->prepare("UPDATE classes SET Level = ? WHERE name = ?");
        foreach ($_POST['levels'] as $class_name => $level_val) {
            $class_name = trim($class_name);
            $level_val = trim($level_val);
            if ($class_name && $level_val) {
                $level_stmt->bind_param("ss", $level_val, $class_name);
                $level_stmt->execute();
                $count++;
            }
        }
        $success = "Successfully updated institutional levels for $count classes!";
    }
}

// 6. Process Semester Structure (Weeks per Semester)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_semester_structure') {
    $weeks = intval($_POST['weeks_per_semester']);
    if ($weeks > 0 && $weeks < 53) {
        setSystemSetting($conn, 'weeks_per_term', $weeks, $_SESSION['username']);
        $success = "Institutional Semester Structure updated to $weeks weeks.";
    } else {
        $error = "Invalid week count. Must be between 1 and 52.";
    }
}

// 7. Process School Calendar (Holidays/Breaks)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_calendar_event') {
        $c_date = $_POST['event_date'];
        $c_type = $_POST['event_type'];
        $c_desc = trim($_POST['description']);
        if ($c_date && $c_type) {
            $stmt = $conn->prepare("INSERT IGNORE INTO academic_calendar (event_date, event_type, description) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $c_date, $c_type, $c_desc);
            $stmt->execute();
            $success = "Academic Calendar updated: School closed on $c_date.";
        }
    }
    if ($_POST['action'] === 'delete_calendar_event') {
        $del_id = intval($_POST['delete_id']);
        $conn->query("DELETE FROM academic_calendar WHERE id = $del_id");
        $success = "Calendar event removed.";
    }
}

// Refetch DB values for UI rendering
$global_oa = floatval(getSystemSetting($conn, 'term_oa_weight', 30));
$global_ex = floatval(getSystemSetting($conn, 'term_exam_weight', 70));

$configs = $conn->query("SELECT * FROM assessment_configurations WHERE academic_year = '$current_academic_year' AND semester = '$current_term' ORDER BY is_exam ASC, id ASC");

// Recalculate for specific split rendering
$oa_total = 0; 
$exam_total = 0;
while($c = $configs->fetch_assoc()) { 
    if ($c['is_exam']) $exam_total += floatval($c['max_marks_allocation']); 
    else $oa_total += floatval($c['max_marks_allocation']); 
}
$configs->data_seek(0);

// For purely visual placeholder
$remaining_oa = 100 - $oa_total;
$remaining_ex = 100 - $exam_total;

// Fetch classes with their current levels for the new organizer
$classes_metadata = [];
$c_meta_res = $conn->query("SELECT name, Level FROM classes ORDER BY name");
if ($c_meta_res) {
    while($r = $c_meta_res->fetch_assoc()) {
        $classes_metadata[] = $r;
    }
}

$weeks_per_semester = intval(getSystemSetting($conn, 'weeks_per_term', 12));

// Fetch Calendar events
$calendar_events = [];
$cal_res = $conn->query("SELECT * FROM academic_calendar ORDER BY event_date ASC LIMIT 50");
if ($cal_res) {
    while($r = $cal_res->fetch_assoc()) $calendar_events[] = $r;
}

// Fetch dynamic levels from dictionary
$dynamic_levels = [];
$dl_res = $conn->query("SELECT level_name FROM academic_levels ORDER BY id ASC");
if($dl_res) {
    while($r = $dl_res->fetch_assoc()) $dynamic_levels[] = $r['level_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php 
    if ($_SESSION['role'] === 'admin') {
        if (file_exists('../../includes/sidebar_admin.php')) include '../../includes/sidebar_admin.php';
    } else {
        if (file_exists('../../includes/top_nav.php')) include '../../includes/top_nav.php';
    }
    ?>

    <main class="<?= $_SESSION['role'] === 'admin' ? 'ml-72' : 'w-full' ?> min-h-screen relative p-8">
        <div class="flex items-center gap-2 mb-4">
            <a href="dashboard.php" class="text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-1 text-sm font-medium">
                <i class="fas fa-arrow-left"></i> Back to Academics
            </a>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-2">
            <i class="fas fa-sliders text-indigo-600"></i> Academic Rules & Limits
        </h1>
        <p class="text-gray-500 mb-8 font-medium">Define your physical assessments, then determine how they split on the Report Card.</p>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm font-bold mb-6">
                <i class="fas fa-check-circle text-emerald-500 text-xl"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm font-bold mb-6">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Column 1: Math Builders -->
            <div class="space-y-6">
                
                <!-- Internal Mechanics (The Buckets) -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="bg-indigo-50 px-6 py-4 border-b border-indigo-100 flex justify-between items-center">
                        <h2 class="font-bold text-indigo-900 flex items-center gap-2">
                            <i class="fas fa-tasks"></i> Physical Assessments Dictionary
                        </h2>
                    </div>
                    <div class="p-6 text-sm">
                        
                        <!-- Dashboards -->
                        <div class="flex gap-4 mb-6">
                            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400 uppercase">OA Bucket Used</div>
                                <div class="text-xl font-black <?= $oa_total == 100 ? 'text-green-500' : 'text-blue-600' ?>"><?= $oa_total ?>% / 100</div>
                            </div>
                            <div class="flex-1 bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
                                <div class="text-[10px] font-bold text-gray-400 uppercase">Exam Bucket Used</div>
                                <div class="text-xl font-black <?= $exam_total == 100 ? 'text-green-500' : 'text-red-600' ?>"><?= $exam_total ?>% / 100</div>
                            </div>
                        </div>

                        <!-- Current Rules -->
                        <table class="w-full text-left text-sm mb-6 border border-gray-100">
                            <thead class="bg-gray-50 font-bold text-gray-500 text-xs uppercase">
                                <tr>
                                    <th class="py-2 px-3">Assessment Type</th>
                                    <th class="py-2 px-3">Base Out Of</th>
                                    <th class="py-2 px-3 text-center w-10">Act</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php while($c = $configs->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-3 px-3 font-bold text-gray-800">
                                            <?= htmlspecialchars($c['assessment_name']) ?>
                                            <?php if($c['is_exam']): ?>
                                                <span class="ml-2 bg-red-100 text-red-700 text-[10px] px-1.5 py-0.5 rounded font-black">EXAM RULE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-3 font-black <?= $c['is_exam'] ? 'text-red-500' : 'text-blue-500' ?>"><?= floatval($c['max_marks_allocation']) ?></td>
                                        <td class="py-3 px-3 text-center">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="delete_assessment">
                                                <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="text-gray-400 hover:text-red-500 transition"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>

                        <!-- Add Form -->
                        <form method="POST" class="border-t border-gray-100 pt-5">
                            <input type="hidden" name="action" value="save_assessment">
                            
                            <h3 class="font-bold text-sm text-gray-800 mb-3"><i class="fas fa-plus-circle text-indigo-500 mr-1"></i> Add Database Rule</h3>
                            
                            <div class="grid grid-cols-5 gap-3">
                                <div class="col-span-3">
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Assessment Label</label>
                                    <input type="text" name="assessment_name" required placeholder="e.g. 'EXAM'" class="w-full px-3 py-2 border border-gray-300 rounded font-medium bg-white">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Internal Target</label>
                                    <input type="number" step="0.1" name="max_marks" id="target_input" required class="w-full px-3 py-2 border border-gray-300 rounded font-bold bg-white text-center">
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between mt-4 bg-gray-50 p-3 rounded border border-gray-200">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox" name="is_exam" id="is_exam_chk" class="rounded border-gray-400 text-red-600 focus:ring-red-500 w-4 h-4 cursor-pointer">
                                    <span class="text-xs font-bold text-gray-600 uppercase group-hover:text-red-600 transition">Flag as Exam</span>
                                </label>
                                
                                <button type="submit" class="bg-indigo-600 text-white font-bold px-6 py-2 rounded hover:bg-indigo-700 transition">Save Record</button>
                            </div>
                            <div class="mt-2 text-[10px] text-gray-400 font-bold uppercase" id="math_indicator">Adding to OA Bucket. Available space: <?= $remaining_oa ?>.</div>
                        </form>
                        
                        <script>
                            // Simple UI helper to show remaining math space based on checkbox
                            const chk = document.getElementById('is_exam_chk');
                            const indicator = document.getElementById('math_indicator');
                            const target = document.getElementById('target_input');
                            
                            const remOa = <?= $remaining_oa ?>;
                            const remEx = <?= $remaining_ex ?>;
                            
                            function toggleRename(name) {
                                const disp = document.getElementById('display_' + name);
                                const edit = document.getElementById('edit_' + name);
                                if(edit.classList.contains('hidden')) {
                                    edit.classList.remove('hidden');
                                    disp.classList.add('hidden');
                                } else {
                                    edit.classList.add('hidden');
                                    disp.classList.remove('hidden');
                                }
                            }

                            function updateUI() {
                                if(chk.checked) {
                                    indicator.innerHTML = 'Adding to EXAM Bucket. Available space: ' + remEx;
                                    indicator.classList.add('text-red-500');
                                    indicator.classList.remove('text-gray-400');
                                    if(target.value === '' && remEx > 0) target.value = remEx;
                                } else {
                                    indicator.innerHTML = 'Adding to OA Bucket. Available space: ' + remOa;
                                    indicator.classList.remove('text-red-500');
                                    indicator.classList.add('text-gray-400');
                                    if(target.value === '' && remOa > 0) target.value = remOa;
                                }
                            }
                            chk.addEventListener('change', updateUI);
                            updateUI(); // Run once on load
                        </script>

                    </div>
                </div>

                <!-- Terminal Matrix -->
                <div class="bg-gray-900 rounded-xl shadow-sm border border-gray-800 overflow-hidden text-white">
                    <div class="px-6 py-4 border-b border-gray-700 flex justify-between items-center">
                        <h2 class="font-bold flex items-center gap-2"><i class="fas fa-file-pdf text-red-400"></i> Report Card Extractor Rules</h2>
                    </div>
                    <form method="POST" class="p-6">
                        <input type="hidden" name="action" value="save_global_weights">
                        <p class="text-xs text-gray-400 mb-6 font-bold uppercase tracking-wider leading-relaxed">
                            How should the 100-base rules defined above be mathematically extracted onto the Terminal Transcripts?
                        </p>
                        
                        <div class="flex items-center justify-between gap-6">
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-gray-300 uppercase mb-2 text-center">OA Pull</label>
                                <div class="relative">
                                    <input type="number" name="oa_weight" value="<?= $global_oa ?>" class="w-full bg-gray-800 border border-gray-600 rounded text-center text-xl font-bold py-3 text-white focus:border-red-400 outline-none">
                                    <span class="absolute right-4 top-3.5 text-gray-500 font-bold">%</span>
                                </div>
                            </div>
                            <div class="text-2xl font-black text-gray-600 mt-5">+</div>
                            <div class="flex-1">
                                <label class="block text-xs font-bold text-gray-300 uppercase mb-2 text-center">Exam Pull</label>
                                <div class="relative">
                                    <input type="number" name="exam_weight" value="<?= $global_ex ?>" class="w-full bg-gray-800 border border-gray-600 rounded text-center text-xl font-bold py-3 text-white focus:border-red-400 outline-none">
                                    <span class="absolute right-4 top-3.5 text-gray-500 font-bold">%</span>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="w-full mt-6 bg-red-600 text-white font-bold py-3 rounded hover:bg-red-700 transition uppercase text-sm tracking-widest border border-red-500">Lock Master Split Logic</button>
                    </form>
                </div>

                <!-- Semester Structure Configuration -->
                <div class="bg-white rounded-xl shadow-sm border border-indigo-100 overflow-hidden">
                    <div class="bg-indigo-50 px-6 py-4 border-b border-indigo-100 flex justify-between items-center">
                        <h2 class="font-bold text-indigo-900 flex items-center gap-2">
                            <i class="fas fa-calendar-alt"></i> Semester Structure (Institutional Weeks)
                        </h2>
                    </div>
                    <form method="POST" class="p-6">
                        <input type="hidden" name="action" value="save_semester_structure">
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Weeks per Academic Semester</label>
                            <div class="flex items-center gap-4">
                                <input type="number" name="weeks_per_semester" value="<?= $weeks_per_semester ?>" min="1" max="52" required class="w-32 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-center text-xl font-black text-indigo-600 focus:ring-2 focus:ring-indigo-500 outline-none transition-all">
                                <div class="flex-1 text-xs text-gray-500 leading-relaxed">
                                    Define the standard duration for the current semester. This value is used to calculate the <strong>Attendance Participation Rate</strong> in reports.
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-3 rounded-lg hover:bg-indigo-700 shadow-sm transition">
                            Save Semester Duration
                        </button>
                    </form>
                </div>

                <!-- School Calendar Manager -->
                <div class="bg-gray-900 rounded-xl shadow-sm border border-gray-800 overflow-hidden text-white">
                    <div class="px-6 py-4 border-b border-gray-800 bg-gray-800/50 flex justify-between items-center">
                        <h2 class="font-bold flex items-center gap-2">
                            <i class="fas fa-calendar-check text-indigo-400"></i> School Calendar (Closed Dates)
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="POST" class="mb-8 p-4 bg-gray-800/30 rounded-xl border border-gray-800">
                            <input type="hidden" name="action" value="add_calendar_event">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Date Closed</label>
                                    <input type="date" name="event_date" required class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs font-bold text-white focus:ring-1 focus:ring-indigo-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Type</label>
                                    <select name="event_type" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs font-bold text-white focus:ring-1 focus:ring-indigo-500 outline-none">
                                        <option value="holiday">Public Holiday</option>
                                        <option value="mid-semester">Mid-Semester Break</option>
                                        <option value="break">Semester Break</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-widest mb-1">Description (e.g. 'Independence Day')</label>
                                <input type="text" name="description" placeholder="Internal memo..." class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-xs font-bold text-white focus:ring-1 focus:ring-indigo-500 outline-none transition-all">
                            </div>
                            <button type="submit" class="w-full bg-indigo-600 text-white font-black py-2.5 rounded-lg text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition">Add Closed Date</button>
                        </form>

                        <div class="space-y-3">
                            <h4 class="text-[10px] font-black text-gray-600 uppercase tracking-widest mb-2">Upcoming Closures</h4>
                            <?php if(empty($calendar_events)): ?>
                                <div class="text-center py-8 text-gray-600 italic text-xs">No school closures scheduled.</div>
                            <?php else: ?>
                                <?php foreach($calendar_events as $ev): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-800 group">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-lg bg-indigo-500/10 flex flex-col items-center justify-center border border-indigo-500/20">
                                                <span class="text-[9px] font-black text-indigo-400 uppercase"><?= date('M', strtotime($ev['event_date'])) ?></span>
                                                <span class="text-sm font-black text-white"><?= date('d', strtotime($ev['event_date'])) ?></span>
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold text-gray-200"><?= htmlspecialchars($ev['description'] ?: 'School Closed') ?></div>
                                                <div class="text-[9px] font-black text-gray-500 uppercase tracking-tighter"><?= strtoupper($ev['event_type']) ?></div>
                                            </div>
                                        </div>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="delete_calendar_event">
                                            <input type="hidden" name="delete_id" value="<?= $ev['id'] ?>">
                                            <button type="submit" class="text-gray-600 hover:text-red-500 transition px-2"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full Width Row: Institutional Organization -->
            <div class="lg:col-span-2 space-y-8">
                
                <!-- Level Dictionary (Add/Rename/Delete) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-8 py-5 border-b border-gray-50 bg-gray-50/50 flex justify-between items-center">
                        <h3 class="font-extrabold text-gray-900 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="fas fa-book-atlas text-indigo-500"></i> Academic Level Dictionary
                        </h3>
                    </div>
                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                            <?php 
                            $dl_res->data_seek(0);
                            while($lvl = $dl_res->fetch_assoc()): 
                            ?>
                                <div class="p-5 bg-gray-50 rounded-2xl border border-gray-100 group transition-all hover:bg-white hover:shadow-md">
                                    <div class="flex justify-between items-start mb-4">
                                        <div class="text-xs font-black text-indigo-600 uppercase tracking-tighter">Academic Level</div>
                                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="toggleRename('<?= htmlspecialchars($lvl['level_name']) ?>')" class="text-gray-400 hover:text-indigo-600"><i class="fas fa-edit"></i></button>
                                            <form method="POST" onsubmit="return confirm('Delete level and unmap all classes?')">
                                                <input type="hidden" name="action" value="delete_level">
                                                <input type="hidden" name="level_name" value="<?= htmlspecialchars($lvl['level_name']) ?>">
                                                <button type="submit" class="text-gray-400 hover:text-red-600"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                    <div id="display_<?= htmlspecialchars($lvl['level_name']) ?>" class="text-xl font-extrabold text-gray-900 tracking-tight">
                                        <?= htmlspecialchars($lvl['level_name']) ?>
                                    </div>
                                    <form id="edit_<?= htmlspecialchars($lvl['level_name']) ?>" method="POST" class="hidden flex gap-2">
                                        <input type="hidden" name="action" value="rename_level">
                                        <input type="hidden" name="old_name" value="<?= htmlspecialchars($lvl['level_name']) ?>">
                                        <input type="text" name="new_name" value="<?= htmlspecialchars($lvl['level_name']) ?>" class="flex-1 bg-white border border-indigo-200 rounded-lg px-3 py-1 text-sm font-bold focus:ring-1 focus:ring-indigo-500 outline-none">
                                        <button type="submit" class="bg-indigo-600 text-white px-3 py-1 rounded-lg text-xs font-bold">Save</button>
                                        <button type="button" onclick="toggleRename('<?= htmlspecialchars($lvl['level_name']) ?>')" class="text-gray-400 text-xs font-bold">Cancel</button>
                                    </form>
                                </div>
                            <?php endwhile; ?>
                            
                            <!-- Add New Level -->
                            <form method="POST" class="p-5 bg-indigo-50/30 rounded-2xl border border-dashed border-indigo-200 flex flex-col justify-center">
                                <input type="hidden" name="action" value="add_level">
                                <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest mb-2">New Institutional Node</div>
                                <div class="flex gap-2">
                                    <input type="text" name="level_name" placeholder="e.g. 'Senior High'" required class="flex-1 bg-white border border-indigo-100 rounded-lg px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none">
                                    <button type="submit" class="bg-indigo-600 text-white w-10 h-10 rounded-lg shadow-sm hover:bg-indigo-700 transition">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Class Level Organization -->
                <div class="bg-gray-900 rounded-2xl shadow-xl border border-gray-800 overflow-hidden">
                    <div class="px-8 py-6 border-b border-gray-800 flex justify-between items-center">
                        <div>
                            <h2 class="text-xl font-bold text-white flex items-center gap-3">
                                <i class="fas fa-sitemap text-indigo-400"></i> Class Level Organization
                            </h2>
                            <p class="text-[10px] font-black text-gray-500 uppercase tracking-widest mt-1">Assign classes to institution-wide academic cycles</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-tighter">Total Active Nodes: <?= count($classes_metadata) ?></span>
                        </div>
                    </div>
                    
                    <form method="POST" class="p-8">
                        <input type="hidden" name="action" value="update_class_levels">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
                            <?php foreach($classes_metadata as $class): 
                                $current_level = $class['Level'];
                            ?>
                                <div class="bg-gray-800/50 border border-gray-700/50 rounded-xl p-4 hover:border-indigo-500/50 transition-all group">
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-8 h-8 rounded-lg bg-gray-700 flex items-center justify-center text-gray-400 group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                                            <i class="fas fa-chalkboard text-xs"></i>
                                        </div>
                                        <span class="font-bold text-gray-200 text-sm"><?= htmlspecialchars($class['name']) ?></span>
                                    </div>
                                    <select name="levels[<?= htmlspecialchars($class['name']) ?>]" class="w-full bg-gray-900 border border-gray-700 rounded-lg text-xs font-bold text-indigo-400 px-3 py-2 outline-none focus:border-indigo-500 transition-colors cursor-pointer">
                                        <option value="">-- No Level --</option>
                                        <?php foreach($dynamic_levels as $lvl): ?>
                                            <option value="<?= htmlspecialchars($lvl) ?>" <?= $current_level === $lvl ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($lvl) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex justify-between items-center pt-8 border-t border-gray-800">
                             <div class="flex items-center gap-3 text-gray-500">
                                <i class="fas fa-info-circle text-indigo-400"></i>
                                <span class="text-[10px] font-bold uppercase tracking-widest">Applying changes will re-classify students and curriculum rules.</span>
                            </div>
                            <button type="submit" class="bg-indigo-600 text-white font-black py-4 px-12 rounded-xl shadow-lg hover:bg-indigo-700 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3 text-sm">
                                <i class="fas fa-save"></i> Save Institutional Mapping
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
