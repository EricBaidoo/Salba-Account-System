<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

// Messages are handled globally via Flash system
$uid = $_SESSION['user_id'];

// Load plan for editing if requested
$edit_id = intval($_GET['edit'] ?? 0);
$edit_data = null;
if ($edit_id) {
    $res = $conn->query("SELECT * FROM lesson_plans WHERE id = $edit_id AND teacher_id = $uid AND status IN ('draft', 'pending', 'rejected') LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
    }
}
$v = fn($k) => htmlspecialchars($_POST[$k] ?? $edit_data[$k] ?? '');

// Safe Migration: Ensure lesson_plans has all modern columns (MySQL 5.7+ compatible)
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    'status' => "VARCHAR(20) DEFAULT 'pending' AFTER objectives",
    'supervisor_comments' => "TEXT NULL AFTER status",
    'week_ending' => "DATE NULL",
    'day_of_week' => "VARCHAR(20) NULL",
    'duration' => "VARCHAR(20) NULL",
    'strand' => "VARCHAR(255) NULL",
    'sub_strand' => "VARCHAR(255) NULL",
    'class_size' => "INT DEFAULT 0",
    'content_standard' => "TEXT NULL",
    'indicator' => "TEXT NULL",
    'lesson_number' => "VARCHAR(20) NULL",
    'performance_indicator' => "TEXT NULL",
    'core_competencies' => "TEXT NULL",
    'references' => "TEXT NULL",
    'tlm' => "TEXT NULL",
    'new_words' => "TEXT NULL",
    'starter_activities' => "TEXT NULL",
    'starter_resources' => "TEXT NULL",
    'learning_activities' => "TEXT NULL",
    'learning_resources' => "TEXT NULL",
    'learning_assessment' => "TEXT NULL",
    'reflection_activities' => "TEXT NULL",
    'reflection_resources' => "TEXT NULL",
    'academic_year' => "VARCHAR(20) NULL",
    'phase1_duration' => "VARCHAR(20) NULL",
    'phase2_duration' => "VARCHAR(20) NULL",
    'phase3_duration' => "VARCHAR(20) NULL",
    'week_number' => "INT DEFAULT 1 AFTER subject_id"
];
foreach ($cols_to_check as $col => $def) {
    $exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = '$col'")->fetch_row()[0];
    if (!$exists) {
        $conn->query("ALTER TABLE lesson_plans ADD COLUMN `$col` $def");
    } elseif ($col === 'status') {
        // Ensure status is at least VARCHAR(20) to support 'draft'
        $conn->query("ALTER TABLE lesson_plans MODIFY COLUMN `$col` VARCHAR(20) DEFAULT 'pending'");
    }
}

include_once '../../includes/system_settings.php';
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

// Handle Unsubmit / Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsubmit_plan'])) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    $check = $conn->query("SELECT status FROM lesson_plans WHERE id = $plan_id AND teacher_id = $uid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['status'] === 'pending') {
            if ($conn->query("UPDATE lesson_plans SET status = 'draft' WHERE id = $plan_id")) {
                redirect('lesson_portfolio', 'success', "Lesson plan unsubmitted to draft.");
            }
        } else {
            redirect('lesson_portfolio', 'error', "Cannot unsubmit a plan that has been reviewed.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    $check = $conn->query("SELECT status FROM lesson_plans WHERE id = $plan_id AND teacher_id = $uid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['status'] === 'pending' || $row['status'] === 'draft' || $row['status'] === 'rejected') {
            if ($conn->query("DELETE FROM lesson_plans WHERE id = $plan_id")) {
                redirect('lesson_portfolio', 'success', "Lesson plan deleted successfully.");
            }
        } else {
            redirect('lesson_portfolio', 'error', "Cannot delete an approved plan.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['submit_plan']) || isset($_POST['save_draft']))) {
    $action_status = isset($_POST['save_draft']) ? 'draft' : 'pending';
    $plan_id      = intval($_POST['existing_plan_id'] ?? 0);
    
    $class        = trim($_POST['class_name'] ?? '');
    $subject_id   = intval($_POST['subject_id'] ?? 0);
    $week         = intval($_POST['week_number'] ?? 1);
    $week_ending  = !empty($_POST['week_ending']) ? $_POST['week_ending'] : null;
    $day          = $_POST['day_of_week'] ?? '';
    $duration     = trim($_POST['duration'] ?? '');
    $strand       = trim($_POST['strand'] ?? '');
    $sub_strand   = trim($_POST['sub_strand'] ?? '');
    $class_size   = intval($_POST['class_size'] ?? 0);
    
    $topic        = $sub_strand; // Mapping sub-strand to topic for DB consistency
    $objectives   = trim($_POST['objectives'] ?? '');
    $standard     = trim($_POST['content_standard'] ?? '');
    $indicator    = trim($_POST['indicator'] ?? '');
    $lesson_num   = trim($_POST['lesson_number'] ?? '');
    $perf_ind     = trim($_POST['performance_indicator'] ?? '');
    $core_comp    = trim($_POST['core_competencies'] ?? '');
    $refs         = trim($_POST['references'] ?? '');
    $tlm          = trim($_POST['tlm'] ?? '');
    $new_words    = trim($_POST['new_words'] ?? '');
    
    $s_act        = trim($_POST['starter_activities'] ?? '');
    $s_res        = trim($_POST['starter_resources'] ?? '');
    $s_dur        = trim($_POST['phase1_duration'] ?? '');
    
    $l_act        = trim($_POST['learning_activities'] ?? '');
    $l_res        = trim($_POST['learning_resources'] ?? '');
    $l_ass        = trim($_POST['learning_assessment'] ?? '');
    $l_dur        = trim($_POST['phase2_duration'] ?? '');
    
    $r_act        = trim($_POST['reflection_activities'] ?? '');
    $r_res        = trim($_POST['reflection_resources'] ?? '');
    $r_dur        = trim($_POST['phase3_duration'] ?? '');
    
    $homework     = trim($_POST['homework'] ?? '');
    
    if ($class && $subject_id && $sub_strand) {
        if ($plan_id > 0) {
            // Update existing
            $stmt = $conn->prepare("UPDATE lesson_plans SET 
                class_name=?, subject_id=?, week_number=?, topic=?, objectives=?, 
                week_ending=?, day_of_week=?, duration=?, strand=?, sub_strand=?, class_size=?,
                content_standard=?, indicator=?, lesson_number=?, performance_indicator=?, core_competencies=?,
                `references`=?, tlm=?, new_words=?, starter_activities=?, starter_resources=?,
                learning_activities=?, learning_resources=?, learning_assessment=?,
                reflection_activities=?, reflection_resources=?, homework=?, semester=?, academic_year=?,
                phase1_duration=?, phase2_duration=?, phase3_duration=?, status=?
                WHERE id=? AND teacher_id=?");
            
            $types = "siisssssssissssssssssssssssssssssii";
            $stmt->bind_param($types, 
                $class, $subject_id, $week, $topic, $objectives,
                $week_ending, $day, $duration, $strand, $sub_strand, $class_size,
                $standard, $indicator, $lesson_num, $perf_ind, $core_comp,
                $refs, $tlm, $new_words, $s_act, $s_res,
                $l_act, $l_res, $l_ass,
                $r_act, $r_res, $homework, $current_term, $current_year,
                $s_dur, $l_dur, $r_dur, $action_status, $plan_id, $uid
            );
        } else {
            // Insert new
            $stmt = $conn->prepare("
                INSERT INTO lesson_plans 
                (teacher_id, class_name, subject_id, week_number, topic, objectives, 
                 week_ending, day_of_week, duration, strand, sub_strand, class_size,
                 content_standard, indicator, lesson_number, performance_indicator, core_competencies,
                 `references`, tlm, new_words, starter_activities, starter_resources,
                 learning_activities, learning_resources, learning_assessment,
                 reflection_activities, reflection_resources, homework, semester, academic_year,
                 phase1_duration, phase2_duration, phase3_duration, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "isiisssssssissssssssssssssssssssss", 
                $uid, $class, $subject_id, $week, $topic, $objectives,
                $week_ending, $day, $duration, $strand, $sub_strand, $class_size,
                $standard, $indicator, $lesson_num, $perf_ind, $core_comp,
                $refs, $tlm, $new_words, $s_act, $s_res,
                $l_act, $l_res, $l_ass,
                $r_act, $r_res, $homework, $current_term, $current_year,
                $s_dur, $l_dur, $r_dur, $action_status
            );
        }
        
        if ($stmt->execute()) {
            $msg = ($action_status === 'draft') ? "Lesson plan saved as draft." : "Lesson plan submitted successfully!";
            redirect('lesson_portfolio', 'success', $msg);
        } else {
            redirect('lesson_portfolio', 'error', "Failed: " . $conn->error);
        }
    } else {
        redirect('lesson_portfolio', 'error', "Please fill out required fields (Class, Subject, Sub-strand).");
    }
}

// Stats for dashboard (Optional if you still want counts here, but user said "nothing else")
// I'll keep the variables but they won't be used in the view for now to keep code clean if needed later.

// Stats for dashboard
$stats_res = $conn->query("
    SELECT 
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status IN ('approved', 'rejected') THEN 1 ELSE 0 END) as reviewed_count
    FROM lesson_plans 
    WHERE teacher_id = $uid
");
$stats = $stats_res->fetch_assoc();


// Fetch teacher's subjects
$allocated_subjects = [];
$res = $conn->query("SELECT s.id, s.name FROM subjects s ORDER BY s.name");
while($r = $res->fetch_assoc()) $allocated_subjects[$r['id']] = $r['name'];

$allocated_classes = [];
$res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid");
while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
if ($_SESSION['role'] === 'admin') {
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active'");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lesson Plans - Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen pb-20 max-w-7xl mx-auto px-4 md:px-8">
        <!-- Dashboard Style Header -->
        <div class="py-10">
            <!-- Flash Messages -->
            <?php 
            $flashes = get_flash();
            $url_msg = $_GET['msg'] ?? '';
            $url_type = $_GET['type'] ?? 'info';
            if ($url_msg) $flashes[] = ['type' => $url_type, 'message' => $url_msg];
            
            foreach ($flashes as $f): 
                $bgColor = ($f['type'] === 'success') ? 'bg-emerald-50 border-emerald-200' : ($f['type'] === 'error' ? 'bg-rose-50 border-rose-200' : 'bg-blue-50 border-blue-200');
                $textColor = ($f['type'] === 'success') ? 'text-emerald-800' : ($f['type'] === 'error' ? 'text-rose-800' : 'text-blue-800');
                $icon = ($f['type'] === 'success') ? 'fa-circle-check text-emerald-500' : ($f['type'] === 'error' ? 'fa-circle-exclamation text-rose-500' : 'fa-circle-info text-blue-500');
            ?>
                <div class="mb-8 p-6 rounded-3xl border <?= $bgColor ?> <?= $textColor ?> flex items-center justify-between shadow-xl shadow-slate-200/50 animate-in slide-in-from-top duration-500">
                    <div class="flex items-center gap-4">
                        <i class="fas <?= $icon ?> text-2xl"></i>
                        <div>
                            <p class="font-black text-[0.625rem] uppercase tracking-widest opacity-70"><?= $f['type'] ?></p>
                            <p class="text-sm font-bold"><?= htmlspecialchars($f['message']) ?></p>
                        </div>
                    </div>
                    <?php if($f['type'] === 'success'): ?>
                        <a href="lesson_portfolio" class="bg-white/50 hover:bg-white text-[0.625rem] font-black px-6 py-3 rounded-xl border border-current transition-all uppercase tracking-widest">Dashboard</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <h1 class="text-4xl font-black text-gray-900 flex items-center gap-4 tracking-tighter">
                    <div class="w-12 h-12 bg-slate-800 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-slate-200">
                        <i class="fas fa-landmark text-xl"></i>
                    </div>
                    Lesson Planning
                </h1>
                <a href="lesson_portfolio" class="group bg-white text-gray-600 px-6 py-3 rounded-2xl font-black text-[0.7rem] uppercase tracking-widest hover:bg-slate-800 hover:text-white transition-all shadow-sm border border-gray-100 flex items-center gap-3">
                    <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i> Back to Dashboard
                </a>
            </div>
        </div>
        </div>

        <!-- Row 1: Quick Import (Horizontal) -->
        <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/50 border border-slate-100 overflow-hidden mb-12">
            <div class="bg-slate-50/50 px-8 py-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.2em] flex items-center gap-2">
                    <i class="fas fa-bolt text-indigo-500"></i> Quick Import Tools
                </h3>
                <div class="flex gap-4">
                    <a href="../../assets/templates/GES_Lesson_Note_Template.xlsx" class="text-[0.625rem] text-slate-400 hover:text-emerald-400 font-black uppercase tracking-widest transition flex items-center gap-2" download>
                        <i class="fas fa-file-excel"></i> Excel Template
                    </a>
                    <a href="download_word_template.php" class="text-[0.625rem] text-slate-400 hover:text-blue-400 font-black uppercase tracking-widest transition flex items-center gap-2">
                        <i class="fas fa-file-word"></i> Word Template
                    </a>
                </div>
            </div>
            <div class="p-8">
                <div class="flex flex-col lg:flex-row items-center gap-6">
                    <form action="process_lesson_import.php" method="POST" enctype="multipart/form-data" class="flex-1 w-full flex items-center gap-4">
                        <div class="flex-1 relative group">
                            <input type="file" name="lesson_file" accept=".xlsx,.docx,.rtf,.doc" required class="absolute inset-0 opacity-0 cursor-pointer z-10">
                            <div class="w-full h-20 px-6 bg-slate-50 border-2 border-dashed border-slate-200 rounded-2xl flex items-center gap-4 group-hover:border-indigo-400 group-hover:bg-indigo-50/30 transition-all">
                                <div class="w-10 h-10 bg-white rounded-xl shadow-sm flex items-center justify-center text-indigo-500">
                                    <i class="fas fa-cloud-arrow-up text-lg"></i>
                                </div>
                                <div class="text-left">
                                    <div class="text-[0.625rem] text-slate-400 font-black uppercase tracking-widest mb-0.5">Upload Draft</div>
                                    <div class="text-sm font-bold text-slate-600">Drop file here or click</div>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="h-20 px-8 bg-indigo-600 text-white rounded-2xl font-black text-xs uppercase tracking-[0.2em] hover:bg-indigo-700 transition shadow-xl shadow-indigo-100 flex items-center justify-center gap-3 active:scale-95">
                            Import <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <div class="h-10 w-px bg-slate-100 hidden lg:block"></div>

                    <button onclick="document.getElementById('pasteModal').classList.remove('hidden')" class="w-full lg:w-auto h-20 px-10 bg-white text-blue-600 border-2 border-blue-100 font-black rounded-2xl hover:bg-blue-50 hover:border-blue-200 transition flex items-center justify-center gap-4 text-xs uppercase tracking-[0.2em] active:scale-95 shadow-lg shadow-blue-50">
                        <i class="fas fa-paste text-lg"></i>
                        <div class="text-left">
                            <p class="">Paste Content</p>
                           
                        </div>
                    </button>
                </div>
            </div>

            <!-- Paste Modal -->
            <div id="pasteModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4">
                <div class="bg-white w-full max-w-2xl rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
                    <div class="p-8 bg-gradient-to-r from-blue-600 to-indigo-700 text-white">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-xl font-black uppercase tracking-tighter">Paste Lesson Note</h3>
                                <p class="text-blue-100 text-xs mt-1 font-bold">Copy everything from Word and paste it here</p>
                            </div>
                            <button onclick="document.getElementById('pasteModal').classList.add('hidden')" class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-xl flex items-center justify-center transition">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <form action="process_lesson_paste.php" method="POST" class="p-8">
                        <textarea name="pasted_text" rows="12" required placeholder="Paste your lesson note text here..." class="w-full p-6 bg-slate-50 border-2 border-slate-100 rounded-2xl focus:border-blue-500 outline-none transition-all font-mono text-sm mb-6" spellcheck="false"></textarea>
                        <div class="flex gap-4">
                            <button type="submit" class="flex-1 bg-blue-600 text-white font-black py-5 rounded-2xl hover:bg-blue-700 transition flex items-center justify-center gap-3 uppercase tracking-[0.2em] text-xs">
                                <i class="fas fa-wand-magic-sparkles"></i> Process & Import
                            </button>
                            <button type="button" onclick="document.getElementById('pasteModal').classList.add('hidden')" class="px-8 bg-slate-100 text-slate-500 font-bold py-5 rounded-2xl hover:bg-slate-200 transition uppercase tracking-widest text-xs">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Row 2: Main Form (Full Width) -->
        <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-800 via-slate-700 to-indigo-900 p-10 flex justify-between items-center relative overflow-hidden">
                <div class="absolute right-0 top-0 w-64 h-64 bg-white/5 rounded-full -mr-20 -mt-20 blur-3xl"></div>
                <div class="relative z-10">
                    <h2 class="font-black text-white text-3xl flex items-center gap-4 tracking-tighter">
                        <i class="fas fa-university text-slate-400"></i> GES Lesson Note
                    </h2>
                    <p class="text-slate-300 text-xs mt-2 font-bold uppercase tracking-[0.3em] opacity-80">Official Academic Documentation Portal</p>
                </div>
                <?php if($edit_id): ?>
                    <a href="lesson_plans" class="relative z-10 bg-white/10 text-white text-[0.625rem] font-black px-6 py-3 rounded-xl border border-white/20 hover:bg-white hover:text-slate-900 transition-all uppercase tracking-widest shadow-lg">Cancel Edit</a>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="p-10 space-y-12">
                    <input type="hidden" name="existing_plan_id" value="<?= $edit_id ?>">

                    <!-- Block 1: Header Grid -->
                    <div>
                        <h3 class="flex items-center gap-2 text-xs font-black text-indigo-500 uppercase tracking-[0.2em] mb-6">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full"></span> Header Grid
                        </h3>
                        <div class="space-y-4">
                            <!-- Row 1 -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Week</label>
                                    <select name="week_number" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm appearance-none">
                                        <?php for($i=1; $i<=$total_weeks; $i++): ?>
                                            <option value="<?= $i ?>" <?= $v('week_number') == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Week Ending</label>
                                    <input type="date" name="week_ending" value="<?= $v('week_ending') ?>" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Day</label>
                                    <select name="day_of_week" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm appearance-none">
                                        <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                                            <option value="<?= $d ?>" <?= $v('day_of_week') == $d ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Subject</label>
                                    <select name="subject_id" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm appearance-none">
                                        <?php foreach($allocated_subjects as $id => $name): ?>
                                            <option value="<?= $id ?>" <?= $v('subject_id') == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Row 2 -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Duration</label>
                                    <input type="text" name="duration" value="<?= $v('duration') ?>" placeholder="e.g. 60 mins" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group md:col-span-2">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Strand</label>
                                    <input type="text" name="strand" value="<?= $v('strand') ?>" placeholder="e.g. Forces & Energy" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                            </div>
                            <!-- Row 3 -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Class</label>
                                    <select name="class_name" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm appearance-none">
                                        <?php foreach($allocated_classes as $cl): ?>
                                            <option value="<?= htmlspecialchars($cl) ?>" <?= $v('class_name') == $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Class Size</label>
                                    <input type="number" name="class_size" value="<?= $v('class_size') ?>" placeholder="45" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Sub Strand</label>
                                    <input type="text" name="sub_strand" value="<?= $v('sub_strand') ?>" placeholder="e.g. Force & Motion" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Block 2: Curriculum Grid -->
                    <div class="pt-6">
                        <h3 class="flex items-center gap-2 text-xs font-black text-indigo-500 uppercase tracking-[0.2em] mb-6">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full"></span> Curriculum Standards
                        </h3>
                        <div class="space-y-4">
                            <!-- Row 4 -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="relative group md:col-span-2">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Content Standard</label>
                                    <input type="text" name="content_standard" value="<?= $v('content_standard') ?>" placeholder="B7.4.4.1 Examine..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Indicator</label>
                                    <input type="text" name="indicator" value="<?= $v('indicator') ?>" placeholder="B7.4.4.1.1 Understand..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Lesson</label>
                                    <input type="text" name="lesson_number" value="<?= $v('lesson_number') ?>" placeholder="1 of 2" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                            </div>
                            <!-- Row 5 -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Performance Indicator</label>
                                    <textarea name="performance_indicator" rows="2" placeholder="Learners can explain..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm"><?= $v('performance_indicator') ?></textarea>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">Core Competencies</label>
                                    <textarea name="core_competencies" rows="2" placeholder="DL 5.3: CI 6.8..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm"><?= $v('core_competencies') ?></textarea>
                                </div>
                            </div>
                            <!-- Row 6 & 7 -->
                             <div class="grid grid-cols-1 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">References</label>
                                    <input type="text" name="references" value="<?= $v('references') ?>" placeholder="e.g. Science Curriculum Pg. 33-34" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-slate-800 transition-colors">New Words</label>
                                    <input type="text" name="new_words" value="<?= $v('new_words') ?>" placeholder="balanced, unbalanced, force" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-slate-800/10 focus:border-slate-800 outline-none transition-all text-sm">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Block 3: Instructional Phases -->
                    <div class="pt-6">
                        <h3 class="flex items-center gap-2 text-xs font-black text-indigo-500 uppercase tracking-[0.2em] mb-6">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full"></span> Delivery Phases
                        </h3>
                        
                        <div class="space-y-6">
                            <!-- Phase 1: Starter -->
                             <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100">
                                <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                                    <h4 class="text-[0.625rem] font-black text-gray-600 uppercase tracking-widest">Phase 1: Starter / Introduction</h4>
                                    <input type="text" name="phase1_duration" value="<?= $v('phase1_duration') ?>" placeholder="Duration" class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs w-24">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Learner Activities</label>
                                        <textarea name="starter_activities" rows="3" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-slate-800 focus:ring-4 focus:ring-slate-800/10 outline-none transition-all"><?= $v('starter_activities') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Resources</label>
                                        <textarea name="starter_resources" rows="3" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-slate-800 focus:ring-4 focus:ring-slate-800/10 outline-none transition-all"><?= $v('starter_resources') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Phase 2: New Learning -->
                             <div class="bg-indigo-50/30 p-6 rounded-2xl border border-indigo-100/50">
                                <div class="flex justify-between items-center mb-4 border-b border-indigo-100/50 pb-2">
                                    <h4 class="text-[0.625rem] font-black text-indigo-800 uppercase tracking-widest">Phase 2: New Learning & Development</h4>
                                    <input type="text" name="phase2_duration" value="<?= $v('phase2_duration') ?>" placeholder="Duration" class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs w-24">
                                </div>
                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                        <div class="lg:col-span-2">
                                            <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Learner Activities</label>
                                            <textarea name="learning_activities" rows="10" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-slate-800 focus:ring-4 focus:ring-slate-800/10 outline-none transition-all"><?= $v('learning_activities') ?></textarea>
                                            
                                            <div class="mt-4 p-4 bg-white/60 border border-blue-100 rounded-xl">
                                                <label class="block text-[0.5625rem] font-black text-blue-600 uppercase mb-2"><i class="fas fa-clipboard-question"></i> Evaluation / Assessment Queries</label>
                                                <textarea name="learning_assessment" rows="3" placeholder="Define key terms, specific questions..." class="w-full px-4 py-3 bg-white border border-blue-100/30 rounded-lg text-sm focus:border-blue-500 outline-none transition-all"><?= $v('learning_assessment') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Teaching & Learning Materials (TLMs)</label>
                                            <textarea name="tlm" rows="16" placeholder="Batteries, Torch, Charts, Flashcards..." class="flex-1 w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-slate-800 outline-none transition-all"><?= $v('tlm') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Phase 3: Reflection -->
                             <div class="bg-gray-50/50 p-6 rounded-2xl border border-gray-100">
                                <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                                    <h4 class="text-[0.625rem] font-black text-gray-600 uppercase tracking-widest">Phase 3: Reflection & Conclusion</h4>
                                    <input type="text" name="phase3_duration" value="<?= $v('phase3_duration') ?>" placeholder="Duration" class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs w-24">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Learner Reflection & Closure</label>
                                        <textarea name="reflection_activities" rows="4" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-slate-800 outline-none transition-all"><?= $v('reflection_activities') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-indigo-600 uppercase mb-2"><i class="fas fa-house-chimney"></i> Homework / Assignment</label>
                                        <textarea name="homework" rows="4" placeholder="List questions or tasks for home..." class="w-full px-4 py-3 bg-white border border-indigo-100 rounded-xl text-sm focus:border-indigo-500 outline-none transition-all"><?= $v('homework') ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 pt-10 border-t border-gray-50">
                        <button type="submit" name="submit_plan" class="flex-[3] bg-slate-800 text-white font-black py-5 rounded-2xl hover:bg-slate-900 transition shadow-xl shadow-slate-200/50 flex items-center justify-center gap-3 text-sm uppercase tracking-widest active:scale-[0.98]">
                            <i class="fas fa-paper-plane"></i> <?= ($edit_id) ? 'Update & Submit Note' : 'Submit Lesson Note' ?>
                        </button>
                        <button type="submit" name="save_draft" class="flex-[1] bg-white text-slate-600 border-2 border-slate-100 font-bold py-5 rounded-2xl hover:bg-slate-50 hover:border-slate-200 transition flex items-center justify-center gap-3 text-sm active:scale-[0.98]">
                            <i class="fas fa-floppy-disk text-slate-400"></i> <?= ($edit_id) ? 'Update Draft' : 'Save Draft' ?>
                        </button>
                    </div>
                </form>
            </div> <!-- Close form container card -->
        </div> <!-- Close form container card (Outer) -->
    </main>

    <style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #e2e8f0;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #cbd5e1;
    }
    </style>
</body>
</html>
