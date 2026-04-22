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
    $res = $conn->query("SELECT * FROM lesson_plans WHERE id = $edit_id AND teacher_id = $uid AND status IN ('draft', 'pending') LIMIT 1");
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
    'phase3_duration' => "VARCHAR(20) NULL"
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

// Handle Unsubmit / Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unsubmit_plan'])) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    $check = $conn->query("SELECT status FROM lesson_plans WHERE id = $plan_id AND teacher_id = $uid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['status'] === 'pending') {
            if ($conn->query("UPDATE lesson_plans SET status = 'draft' WHERE id = $plan_id")) {
                redirect('lesson_plans', 'success', "Lesson plan unsubmitted to draft.");
            }
        } else {
            redirect('lesson_plans', 'error', "Cannot unsubmit a plan that has been reviewed.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    $check = $conn->query("SELECT status FROM lesson_plans WHERE id = $plan_id AND teacher_id = $uid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['status'] === 'pending' || $row['status'] === 'draft') {
            if ($conn->query("DELETE FROM lesson_plans WHERE id = $plan_id")) {
                redirect('lesson_plans', 'success', "Lesson plan deleted successfully.");
            }
        } else {
            redirect('lesson_plans', 'error', "Cannot delete a reviewed plan.");
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
            $stmt = $conn->prepare("
                UPDATE lesson_plans SET 
                class_name=?, subject_id=?, week_number=?, topic=?, objectives=?, 
                week_ending=?, day_of_week=?, duration=?, strand=?, sub_strand=?, class_size=?,
                content_standard=?, indicator=?, lesson_number=?, performance_indicator=?, core_competencies=?,
                `references`=?, tlm=?, new_words=?, starter_activities=?, starter_resources=?,
                learning_activities=?, learning_resources=?, learning_assessment=?,
                reflection_activities=?, reflection_resources=?, homework=?, semester=?, academic_year=?,
                phase1_duration=?, phase2_duration=?, phase3_duration=?, status=?
                WHERE id=? AND teacher_id=?
            ");
            $stmt->bind_param(
                "siisssssssisssssssssssssssssssssii", 
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
            redirect('lesson_plans', 'success', $msg);
        } else {
            redirect('lesson_plans', 'error', "Failed: " . $conn->error);
        }
    } else {
        redirect('lesson_plans', 'error', "Please fill out required fields (Class, Subject, Sub-strand).");
    }
}

// Fetch teacher's past plans
$plans = $conn->query("
    SELECT l.*, s.name as subject_name 
    FROM lesson_plans l 
    JOIN subjects s ON l.subject_id = s.id 
    WHERE l.teacher_id = $uid 
    ORDER BY l.created_at DESC
");

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

    <main class="admin-main-content p-4 md:p-8 min-h-screen relative">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-6">
            <i class="fas fa-file-contract text-green-500"></i> Lesson Planning
        </h1>

        <!-- Global Flash Messages handled by top_nav.php -->

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-emerald-700 p-6 flex justify-between items-center">
                    <div>
                        <h2 class="font-bold text-white text-lg flex items-center gap-2">
                            <i class="fas fa-edit"></i> GES Standard Lesson Note
                        </h2>
                        <p class="text-green-100 text-[0.625rem] mt-1 font-medium tracking-wide">Refining educational excellence with precise planning.</p>
                    </div>
                    <?php if($edit_id): ?>
                        <a href="lesson_plans" class="bg-white/20 text-white text-[0.625rem] font-bold px-3 py-1.5 rounded-lg border border-white/20 hover:bg-white/30 transition">Cancel Edit</a>
                    <?php endif; ?>
                </div>
                
                <form method="POST" class="p-6 space-y-10">
                    <input type="hidden" name="existing_plan_id" value="<?= $edit_id ?>">

                    <!-- Block 1: Header Grid -->
                    <div>
                        <h3 class="flex items-center gap-2 text-xs font-black text-indigo-500 uppercase tracking-[0.2em] mb-6">
                            <span class="w-1 h-4 bg-indigo-500 rounded-full"></span> Header Grid
                        </h3>
                        <div class="space-y-4">
                            <!-- Row 1 -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Week Ending</label>
                                    <input type="date" name="week_ending" value="<?= $v('week_ending') ?>" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Day</label>
                                    <select name="day_of_week" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm appearance-none">
                                        <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                                            <option value="<?= $d ?>" <?= $v('day_of_week') == $d ? 'selected' : '' ?>><?= $d ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Subject</label>
                                    <select name="subject_id" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm appearance-none">
                                        <?php foreach($allocated_subjects as $id => $name): ?>
                                            <option value="<?= $id ?>" <?= $v('subject_id') == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Row 2 -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Duration</label>
                                    <input type="text" name="duration" value="<?= $v('duration') ?>" placeholder="e.g. 60 mins" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group md:col-span-2">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Strand</label>
                                    <input type="text" name="strand" value="<?= $v('strand') ?>" placeholder="e.g. Forces & Energy" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                            </div>
                            <!-- Row 3 -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Class</label>
                                    <select name="class_name" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm appearance-none">
                                        <?php foreach($allocated_classes as $cl): ?>
                                            <option value="<?= htmlspecialchars($cl) ?>" <?= $v('class_name') == $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Class Size</label>
                                    <input type="number" name="class_size" value="<?= $v('class_size') ?>" placeholder="45" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Sub Strand</label>
                                    <input type="text" name="sub_strand" value="<?= $v('sub_strand') ?>" placeholder="e.g. Force & Motion" required class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
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
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Content Standard</label>
                                    <input type="text" name="content_standard" value="<?= $v('content_standard') ?>" placeholder="B7.4.4.1 Examine..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Indicator</label>
                                    <input type="text" name="indicator" value="<?= $v('indicator') ?>" placeholder="B7.4.4.1.1 Understand..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Lesson</label>
                                    <input type="text" name="lesson_number" value="<?= $v('lesson_number') ?>" placeholder="1 of 2" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                            </div>
                            <!-- Row 5 -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Performance Indicator</label>
                                    <textarea name="performance_indicator" rows="2" placeholder="Learners can explain..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm"><?= $v('performance_indicator') ?></textarea>
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">Core Competencies</label>
                                    <textarea name="core_competencies" rows="2" placeholder="DL 5.3: CI 6.8..." class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm"><?= $v('core_competencies') ?></textarea>
                                </div>
                            </div>
                            <!-- Row 6 & 7 -->
                            <div class="grid grid-cols-1 gap-4">
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">References</label>
                                    <input type="text" name="references" value="<?= $v('references') ?>" placeholder="e.g. Science Curriculum Pg. 33-34" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
                                </div>
                                <div class="relative group">
                                    <label class="absolute -top-2 left-3 px-1 bg-white text-[0.625rem] font-black text-gray-400 group-focus-within:text-green-600 transition-colors">New Words</label>
                                    <input type="text" name="new_words" value="<?= $v('new_words') ?>" placeholder="balanced, unbalanced, force" class="w-full px-4 py-3 bg-gray-50/50 border border-gray-200 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 focus:border-green-500 outline-none transition-all text-sm">
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
                                        <textarea name="starter_activities" rows="3" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-green-500 focus:ring-4 focus:ring-green-500/10 outline-none transition-all"><?= $v('starter_activities') ?></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Resources</label>
                                        <textarea name="starter_resources" rows="3" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-green-500 focus:ring-4 focus:ring-green-500/10 outline-none transition-all"><?= $v('starter_resources') ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Phase 2: New Learning -->
                            <div class="bg-emerald-50/30 p-6 rounded-2xl border border-emerald-100/50">
                                <div class="flex justify-between items-center mb-4 border-b border-emerald-100/50 pb-2">
                                    <h4 class="text-[0.625rem] font-black text-emerald-800 uppercase tracking-widest">Phase 2: New Learning & Development</h4>
                                    <input type="text" name="phase2_duration" value="<?= $v('phase2_duration') ?>" placeholder="Duration" class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs w-24">
                                </div>
                                <div class="space-y-6">
                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                        <div class="lg:col-span-2">
                                            <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Learner Activities</label>
                                            <textarea name="learning_activities" rows="10" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-green-500 focus:ring-4 focus:ring-green-500/10 outline-none transition-all"><?= $v('learning_activities') ?></textarea>
                                            
                                            <div class="mt-4 p-4 bg-white/60 border border-blue-100 rounded-xl">
                                                <label class="block text-[0.5625rem] font-black text-blue-600 uppercase mb-2"><i class="fas fa-clipboard-question"></i> Evaluation / Assessment Queries</label>
                                                <textarea name="learning_assessment" rows="3" placeholder="Define key terms, specific questions..." class="w-full px-4 py-3 bg-white border border-blue-100/30 rounded-lg text-sm focus:border-blue-500 outline-none transition-all"><?= $v('learning_assessment') ?></textarea>
                                            </div>
                                        </div>
                                        <div class="flex flex-col">
                                            <label class="block text-[0.5625rem] font-black text-gray-400 uppercase mb-2">Teaching & Learning Materials (TLMs)</label>
                                            <textarea name="tlm" rows="16" placeholder="Batteries, Torch, Charts, Flashcards..." class="flex-1 w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-green-500 outline-none transition-all"><?= $v('tlm') ?></textarea>
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
                                        <textarea name="reflection_activities" rows="4" class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm focus:border-green-500 outline-none transition-all"><?= $v('reflection_activities') ?></textarea>
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
                        <button type="submit" name="submit_plan" class="flex-[3] bg-green-600 text-white font-black py-5 rounded-2xl hover:bg-green-700 transition shadow-xl shadow-green-200/50 flex items-center justify-center gap-3 text-sm uppercase tracking-widest">
                            <i class="fas fa-paper-plane"></i> <?= ($edit_id) ? 'Update & Submit Note' : 'Submit Lesson Note' ?>
                        </button>
                        <button type="submit" name="save_draft" class="flex-[1] bg-white text-gray-600 border-2 border-gray-100 font-bold py-5 rounded-2xl hover:bg-gray-50 hover:border-gray-200 transition flex items-center justify-center gap-3 text-sm">
                            <i class="fas fa-floppy-disk text-gray-400"></i> <?= ($edit_id) ? 'Update Draft' : 'Save Draft' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 h-fit sticky top-24">
                <h2 class="font-black text-gray-900 border-b border-gray-100 pb-3 mb-6 uppercase tracking-tighter flex items-center gap-2">
                    <i class="fas fa-clock-rotate-left text-indigo-500"></i> My Plan History
                </h2>
                <div class="space-y-4">
                    <?php if($plans && $plans->num_rows > 0): while($p = $plans->fetch_assoc()): ?>
                        <div class="border <?= $p['status'] === 'draft' ? 'border-dashed border-gray-300' : 'border-gray-50' ?> p-5 rounded-2xl bg-gray-50/50 group hover:bg-white hover:border-green-200 transition-all shadow-sm flex flex-col justify-between items-start gap-4">
                            <div class="w-full">
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[0.625rem] font-bold bg-white text-indigo-600 border border-indigo-100 px-2 py-0.5 rounded uppercase">Week <?= $p['week_number'] ?></span>
                                        <span class="text-[0.625rem] font-bold bg-white text-gray-500 border border-gray-100 px-2 py-0.5 rounded"><?= htmlspecialchars($p['class_name']) ?></span>
                                    </div>
                                    <div>
                                        <?php if($p['status'] === 'draft'): ?>
                                            <span class="text-[0.5625rem] font-black text-gray-400 bg-white px-2 py-0.5 rounded border border-gray-200 uppercase tracking-widest">Draft</span>
                                        <?php elseif($p['status'] === 'pending'): ?>
                                            <span class="text-[0.5625rem] font-black text-yellow-600 bg-yellow-50 px-2 py-0.5 rounded border border-yellow-100 uppercase tracking-widest animate-pulse">Pending Review</span>
                                        <?php elseif($p['status'] === 'approved'): ?>
                                            <span class="text-[0.5625rem] font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100 uppercase tracking-widest">Approved</span>
                                        <?php else: ?>
                                            <span class="text-[0.5625rem] font-black text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100 uppercase tracking-widest">Rejected</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h4 class="font-bold text-gray-900 mb-1 leading-tight"><?= htmlspecialchars($p['sub_strand']) ?></h4>
                                <div class="text-[0.625rem] text-gray-400 font-bold uppercase tracking-tight flex items-center gap-1.5"><?= htmlspecialchars($p['subject_name']) ?> <span class="w-1 h-1 bg-gray-300 rounded-full"></span> last activity <?= date('d M, Y', strtotime($p['updated_at'] ?? $p['created_at'])) ?></div>
                                
                                <?php if($p['supervisor_comments']): ?>
                                    <div class="mt-4 p-3 bg-white rounded-xl border border-red-100 relative group/msg">
                                        <div class="text-[0.5rem] font-black text-red-400 uppercase tracking-widest mb-1">Supervisor Remark</div>
                                        <div class="text-[0.6875rem] text-gray-600 italic">"<?= htmlspecialchars($p['supervisor_comments']) ?>"</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2 w-full pt-2 border-t border-gray-100">
                                <?php if($p['status'] === 'draft'): ?>
                                    <a href="?edit=<?= $p['id'] ?>" class="px-3 py-2 bg-indigo-600 text-white text-[0.625rem] font-black rounded-xl hover:bg-indigo-700 transition flex items-center justify-center gap-2 uppercase">
                                        <i class="fas fa-edit"></i> Edit Draft
                                    </a>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>&view=html" target="_blank" class="px-3 py-2 bg-white border border-gray-200 text-gray-700 text-[0.625rem] font-black rounded-xl hover:bg-gray-50 transition flex items-center justify-center gap-2 uppercase">
                                        <i class="fas fa-eye text-indigo-500"></i> View Note
                                    </a>
                                <?php endif; ?>

                                <div class="flex gap-1">
                                    <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>" target="_blank" class="flex-1 px-3 py-2 bg-white border border-gray-200 text-gray-700 text-[0.625rem] font-black rounded-xl hover:bg-gray-50 transition flex items-center justify-center">
                                        <i class="fas fa-file-pdf text-red-500"></i>
                                    </a>
                                    
                                    <?php if($p['status'] === 'pending'): ?>
                                        <form method="POST" onsubmit="return confirm('Note: Unsubmitting will move this back to drafts.');" class="flex-1">
                                            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="unsubmit_plan" class="w-full px-3 py-2 bg-white border border-yellow-200 text-yellow-600 text-[0.625rem] font-black rounded-xl hover:bg-yellow-50 transition flex items-center justify-center">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if($p['status'] === 'pending' || $p['status'] === 'draft'): ?>
                                        <form method="POST" onsubmit="return confirm('Delete this lesson plan permanently?');" class="flex-1">
                                            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete_plan" class="w-full px-3 py-2 bg-white border border-red-100 text-red-500 text-[0.625rem] font-black rounded-xl hover:bg-red-50 transition flex items-center justify-center">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-10">
                            <i class="fas fa-folder-open text-3xl text-gray-200 mb-2"></i>
                            <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">No plans found</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
        </div>
    </main>
</body>
</html>

