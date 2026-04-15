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
$current_term = getCurrentTerm($conn);

// 1. Process Global Transcript Terminal Weights
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_global_weights') {
    $oa_w = floatval($_POST['oa_weight']);
    $ex_w = floatval($_POST['exam_weight']);
    
    if (($oa_w + $ex_w) == 100) {
        setSystemSetting($conn, 'term_oa_weight', $oa_w, $_SESSION['username']);
        setSystemSetting($conn, 'term_exam_weight', $ex_w, $_SESSION['username']);
        $success = "Terminal Report Rules saved strictly at 100% split!";
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
$c_res = $conn->query("SELECT max_marks_allocation, is_exam FROM assessment_configurations WHERE academic_year = '$current_academic_year' AND term = '$current_term'");
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
                $stmt = $conn->prepare("INSERT INTO assessment_configurations (academic_year, term, assessment_name, max_marks_allocation, is_exam) VALUES (?, ?, ?, ?, 1)");
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
                $stmt = $conn->prepare("INSERT INTO assessment_configurations (academic_year, term, assessment_name, max_marks_allocation, is_exam) VALUES (?, ?, ?, ?, 0)");
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

// 4. Process Class Subject Mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'map_subjects') {
    $mapped_class = trim($_POST['class']);
    $sub_ids = $_POST['subjects'] ?? [];
    if ($mapped_class) {
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE class_name = ?");
        $stmt->bind_param("s", $mapped_class);
        $stmt->execute();
        if (!empty($sub_ids)) {
            $insert_stmt = $conn->prepare("INSERT INTO class_subjects (class_name, subject_id) VALUES (?, ?)");
            foreach($sub_ids as $sid) {
                $sid = intval($sid);
                $insert_stmt->bind_param("si", $mapped_class, $sid);
                $insert_stmt->execute();
            }
            $success = count($sub_ids)." subjects mapped to ".htmlspecialchars($mapped_class)."!";
        }
    }
}

// Refetch DB values for UI rendering
$global_oa = floatval(getSystemSetting($conn, 'term_oa_weight', 30));
$global_ex = floatval(getSystemSetting($conn, 'term_exam_weight', 70));

$configs = $conn->query("SELECT * FROM assessment_configurations WHERE academic_year = '$current_academic_year' AND term = '$current_term' ORDER BY is_exam ASC, id ASC");

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

$all_subjects = $conn->query("SELECT * FROM subjects ORDER BY name");
$classes_res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
$classes_list = [];
if ($classes_res) { while($r = $classes_res->fetch_assoc()) $classes_list[] = $r['class']; }
$mappings = [];
$map_res = $conn->query("SELECT class_name, subject_id FROM class_subjects");
while($r = $map_res->fetch_assoc()){ $mappings[$r['class_name']][] = $r['subject_id']; }
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
                        
                        <button type="submit" class="w-full mt-6 bg-red-600 text-white font-bold py-3 rounded hover:bg-red-700 transition uppercase text-sm tracking-wider tracking-widest border border-red-500">Lock Master Split Logic</button>
                    </form>
                </div>
            </div>

            <!-- Column 2: Subject Mappings -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden h-fit">
                <div class="bg-purple-50 px-6 py-4 border-b border-purple-100 flex justify-between items-center">
                    <h2 class="font-bold text-purple-900 flex items-center gap-2">
                        <i class="fas fa-sitemap"></i> Class Curriculum Binder
                    </h2>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="map_subjects">
                    
                    <div class="mb-6 pb-6 border-b border-gray-100">
                        <label class="block text-sm font-semibold text-gray-800 mb-2">Select Target Class to Map</label>
                        <select name="class" required onchange="this.form.submit()" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 font-medium hover:border-purple-400">
                            <option value="">-- Choose Class --</option>
                            <?php foreach($classes_list as $cl): ?>
                                <option value="<?= htmlspecialchars($cl) ?>" <?= (trim($_POST['class'] ?? '') === $cl) ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if(!empty($_POST['class'])): 
                        $target_cl = trim($_POST['class']);
                        $active_maps = $mappings[$target_cl] ?? [];
                    ?>
                        <label class="block text-sm font-semibold text-gray-800 mb-3">Allowed Curriculum for <span class="text-purple-700"><?= htmlspecialchars($target_cl) ?></span></label>
                        <div class="grid grid-cols-2 gap-3 max-h-[500px] overflow-y-auto mb-6 p-2 rounded border border-gray-50 bg-gray-50 inner-shadow">
                            <?php 
                            $all_subjects->data_seek(0);
                            while($sub = $all_subjects->fetch_assoc()): 
                                $checked = in_array($sub['id'], $active_maps) ? 'checked' : '';
                            ?>
                                <label class="flex items-center gap-3 p-3 bg-white border border-gray-200 rounded-lg hover:border-purple-300 hover:shadow-sm cursor-pointer transition">
                                    <input type="checkbox" name="subjects[]" value="<?= $sub['id'] ?>" <?= $checked ?> class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <span class="text-sm font-medium text-gray-800"><?= htmlspecialchars($sub['name']) ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <button type="submit" class="w-full bg-purple-600 text-white font-bold py-3 rounded-lg hover:bg-purple-700 shadow-sm transition">
                            <i class="fas fa-save mr-1"></i> Save <?= htmlspecialchars($target_cl) ?> Curriculum
                        </button>
                    <?php else: ?>
                        <div class="text-center py-12 text-gray-400">
                            <i class="fas fa-layer-group text-4xl mb-3 text-purple-200"></i>
                            <p class="text-sm font-medium text-gray-500">Select a class from the dropdown above<br>to configure its valid subjects.</p>
                        </div>
                    <?php endif; ?>
                </form>

            </div>
        </div>
    </main>
</body>
</html>
