<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];

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
    'references_materials' => "TEXT NULL",
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
    if (!$conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = '$col'")->fetch_row()[0]) {
        $conn->query("ALTER TABLE lesson_plans ADD COLUMN `$col` $def");
    }
}

include_once '../../includes/system_settings.php';
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Handle Unsubmit / Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $plan_id = intval($_POST['plan_id'] ?? 0);
    // Verify ownership and status (only delete if pending)
    $check = $conn->query("SELECT status FROM lesson_plans WHERE id = $plan_id AND teacher_id = $uid");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        if ($row['status'] === 'pending') {
            if ($conn->query("DELETE FROM lesson_plans WHERE id = $plan_id")) {
                $success = "Lesson plan deleted successfully.";
            } else {
                $error = "Failed to delete lesson plan.";
            }
        } else {
            $error = "Cannot delete a plan that has already been reviewed (Approved/Rejected).";
        }
    } else {
        $error = "Unauthorized or plan not found.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_plan'])) {
    $class        = trim($_POST['class_name'] ?? '');
    $subject_id   = intval($_POST['subject_id'] ?? 0);
    $week         = intval($_POST['week_number'] ?? 1);
    $week_ending  = !empty($_POST['week_ending']) ? $_POST['week_ending'] : null;
    $day          = $_POST['day_of_week'] ?? '';
    $duration     = trim($_POST['duration'] ?? '');
    $strand       = trim($_POST['strand'] ?? '');
    $sub_strand   = trim($_POST['sub_strand'] ?? '');
    $class_size   = intval($_POST['class_size'] ?? 0);
    
    $topic        = trim($_POST['topic'] ?? '');
    $objectives   = trim($_POST['objectives'] ?? ''); // Map to Performance Indicator or just keep
    $standard     = trim($_POST['content_standard'] ?? '');
    $indicator    = trim($_POST['indicator'] ?? '');
    $lesson_num   = trim($_POST['lesson_number'] ?? '');
    $perf_ind     = trim($_POST['performance_indicator'] ?? '');
    $core_comp    = trim($_POST['core_competencies'] ?? '');
    $refs         = trim($_POST['references_materials'] ?? '');
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
    
    if ($class && $subject_id && $topic) {
        $stmt = $conn->prepare("
            INSERT INTO lesson_plans 
            (teacher_id, class_name, subject_id, week_number, topic, objectives, 
             week_ending, day_of_week, duration, strand, sub_strand, class_size,
             content_standard, indicator, lesson_number, performance_indicator, core_competencies,
             references_materials, new_words, starter_activities, starter_resources,
             learning_activities, learning_resources, learning_assessment,
             reflection_activities, reflection_resources, homework, semester, academic_year,
             phase1_duration, phase2_duration, phase3_duration) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "isississsssissssssssssssssssssss", 
            $uid, $class, $subject_id, $week, $topic, $objectives,
            $week_ending, $day, $duration, $strand, $sub_strand, $class_size,
            $standard, $indicator, $lesson_num, $perf_ind, $core_comp,
            $refs, $new_words, $s_act, $s_res,
            $l_act, $l_res, $l_ass,
            $r_act, $r_res, $homework, $current_term, $current_year,
            $s_dur, $l_dur, $r_dur
        );
        if ($stmt->execute()) {
            $success = "Lesson plan submitted successfully! Awaiting supervisor approval.";
        } else {
            $error = "Failed to submit lesson plan: " . $conn->error;
        }
    } else {
        $error = "Please fill out required fields (Class, Subject, Topic).";
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

    <main class=" min-h-screen relative p-8">
        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3 mb-6">
            <i class="fas fa-file-contract text-green-500"></i> Lesson Planning
        </h1>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm mb-6"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3 shadow-sm mb-6"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-emerald-700 p-6">
                    <h2 class="font-bold text-white text-lg flex items-center gap-2">
                        <i class="fas fa-edit"></i> Standard Lesson Note
                    </h2>
                    <p class="text-green-100 text-xs mt-1">Fill all sections as per the national curriculum guide.</p>
                </div>
                
                <form method="POST" class="p-6 space-y-8">
                    <!-- SECTION 1: HEADER & LOGISTICS -->
                    <div>
                        <h3 class="flex items-center gap-2 text-sm font-black text-gray-400 uppercase tracking-widest mb-4">
                            <span class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-[10px]">1</span> Logistics & Header
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Teaching Week</label>
                                <input type="number" name="week_number" min="1" max="15" value="1" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Week Ending</label>
                                <input type="date" name="week_ending" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Day of Week</label>
                                <select name="day_of_week" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                                    <?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday'] as $d): ?>
                                        <option value="<?= $d ?>"><?= $d ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Target Class</label>
                                <select name="class_name" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                                    <?php foreach($allocated_classes as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl) ?>"><?= htmlspecialchars($cl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Duration (e.g. 60 mins)</label>
                                <input type="text" name="duration" placeholder="60 mins" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Class Size</label>
                                <input type="number" name="class_size" placeholder="45" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div class="md:col-span-3">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Subject</label>
                                <select name="subject_id" required class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                                    <?php foreach($allocated_subjects as $id => $name): ?>
                                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: CURRICULUM ALIGNMENT -->
                    <div class="pt-4 border-t border-gray-50">
                        <h3 class="flex items-center gap-2 text-sm font-black text-gray-400 uppercase tracking-widest mb-4">
                            <span class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-[10px]">2</span> Curriculum & Strand
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Strand</label>
                                <input type="text" name="strand" placeholder="e.g. Forces & Energy" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Sub-Strand</label>
                                <input type="text" name="sub_strand" placeholder="e.g. Force & Motion" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Content Standard</label>
                                <textarea name="content_standard" rows="2" placeholder="Describe the curriculum standard..." class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Indicator</label>
                                <textarea name="indicator" rows="2" placeholder="Specific indicator code and description..." class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Topic / Title <span class="text-red-500">*</span></label>
                                <input type="text" name="topic" required placeholder="Lesson title" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Lesson Number (e.g. 1 of 2)</label>
                                <input type="text" name="lesson_number" placeholder="1 of 2" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-700 mb-1">Performance Indicator</label>
                                <textarea name="performance_indicator" rows="2" placeholder="What should learners be able to do?" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: PREPARATION -->
                    <div class="pt-4 border-t border-gray-50">
                        <h3 class="flex items-center gap-2 text-sm font-black text-gray-400 uppercase tracking-widest mb-4">
                            <span class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-[10px]">3</span> Preparation & Materials
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Core Competencies</label>
                                <input type="text" name="core_competencies" placeholder="e.g. DL 5.3: CI 6.8" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">References & Teaching Materials</label>
                                <input type="text" name="references_materials" placeholder="Curriculum pages, books, physical items..." class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">New Words (Vocabulary)</label>
                                <input type="text" name="new_words" placeholder="e.g. Friction, Gravity, Force" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 transition">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: LESSON PHASES -->
                    <div class="pt-4 border-t border-gray-50">
                        <h3 class="flex items-center gap-2 text-sm font-black text-gray-400 uppercase tracking-widest mb-4">
                            <span class="w-6 h-6 bg-gray-100 rounded-full flex items-center justify-center text-[10px]">4</span> Lesson Delivery (Phases)
                        </h3>
                        
                        <!-- Phase 1 -->
                        <div class="bg-gray-50 p-5 rounded-2xl mb-5 space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-black text-green-700 uppercase">Phase 1: Starter / Introduction</h4>
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Duration</label>
                                    <input type="text" name="phase1_duration" placeholder="e.g. 5 mins" class="px-2 py-1 bg-white border border-gray-200 rounded text-xs w-24">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Learner Activities</label>
                                    <textarea name="starter_activities" rows="3" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Resources</label>
                                    <textarea name="starter_resources" rows="3" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Phase 2 -->
                        <div class="bg-green-50 p-5 rounded-2xl mb-5 space-y-4 border border-green-100">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-black text-green-800 uppercase">Phase 2: New Learning & Development</h4>
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-bold text-gray-400 border-green-100 uppercase">Duration</label>
                                    <input type="text" name="phase2_duration" placeholder="e.g. 40 mins" class="px-2 py-1 bg-white border border-gray-200 rounded text-xs w-24">
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Learner Activities</label>
                                        <textarea name="learning_activities" rows="5" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Resources</label>
                                            <textarea name="learning_resources" rows="2" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Assessment Queries / Feedback</label>
                                            <textarea name="learning_assessment" rows="2" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Phase 3 -->
                        <div class="bg-gray-50 p-5 rounded-2xl space-y-4">
                            <div class="flex justify-between items-center">
                                <h4 class="text-xs font-black text-gray-700 uppercase">Phase 3: Reflection & Conclusion</h4>
                                <div class="flex items-center gap-2">
                                    <label class="text-[10px] font-bold text-gray-400 uppercase">Duration</label>
                                    <input type="text" name="phase3_duration" placeholder="e.g. 10 mins" class="px-2 py-1 bg-white border border-gray-200 rounded text-xs w-24">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Reflection Activities</label>
                                        <textarea name="reflection_activities" rows="3" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Resources</label>
                                        <textarea name="reflection_resources" rows="2" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm"></textarea>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Homework / Assignment</label>
                                    <textarea name="homework" rows="6" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm" placeholder="List questions or tasks for home..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="submit_plan" class="w-full bg-green-600 text-white font-extrabold py-4 rounded-2xl hover:bg-green-700 transition shadow-lg shadow-green-200 flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Lesson Note to Supervisor
                    </button>
                    
                    <input type="hidden" name="objectives" value="See Detailed Structure"> <!-- Backward compatibility -->
                </form>
            </div>

            <!-- History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 class="font-bold text-gray-800 border-b border-gray-100 pb-3 mb-4">My Submitted Plans</h2>
                <div class="space-y-3">
                    <?php if($plans && $plans->num_rows > 0): while($p = $plans->fetch_assoc()): ?>
                        <div class="border border-gray-100 p-5 rounded-2xl bg-gray-50 group hover:bg-white hover:border-green-200 transition-all shadow-sm flex flex-col sm:flex-row justify-between items-start gap-4">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <span class="text-[10px] font-black bg-indigo-600 text-white px-2 py-0.5 rounded tracking-widest uppercase">Week <?= $p['week_number'] ?></span>
                                    <span class="text-[10px] font-black bg-gray-200 text-gray-600 px-2 py-0.5 rounded tracking-tighter"><?= htmlspecialchars($p['class_name']) ?></span>
                                    <span class="text-[10px] font-black bg-green-50 text-green-700 px-2 py-0.5 rounded border border-green-100"><?= htmlspecialchars($p['subject_name']) ?></span>
                                </div>
                                <h4 class="font-bold text-gray-900 mb-1"><?= htmlspecialchars($p['topic']) ?></h4>
                                <div class="flex items-center gap-3">
                                    <?php if($p['status'] === 'pending'): ?>
                                        <span class="text-[10px] font-bold text-yellow-600 flex items-center gap-1"><i class="fas fa-clock"></i> Pending Review</span>
                                    <?php elseif($p['status'] === 'approved'): ?>
                                        <span class="text-[10px] font-bold text-green-600 flex items-center gap-1"><i class="fas fa-check-circle"></i> Approved</span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold text-red-600 flex items-center gap-1"><i class="fas fa-times-circle"></i> Rejected</span>
                                    <?php endif; ?>
                                    <span class="text-[10px] text-gray-400 font-medium">Submitted <?= date('d M, Y', strtotime($p['created_at'])) ?></span>
                                </div>
                                
                                <?php if($p['supervisor_comments']): ?>
                                    <div class="mt-3 text-xs bg-white p-3 rounded-lg border-l-2 border-green-500 italic text-gray-600 shadow-sm">
                                        "<?= htmlspecialchars($p['supervisor_comments']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex flex-col gap-2 w-full sm:w-auto">
                                <a href="print_lesson_plan.php?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-full text-center px-4 py-2 bg-indigo-50 border border-indigo-100 text-indigo-700 text-xs font-bold rounded-xl hover:bg-indigo-100 transition flex items-center justify-center gap-2">
                                    <i class="fas fa-eye"></i> View Structured Note
                                </a>
                                <a href="print_lesson_plan.php?id=<?= $p['id'] ?>" target="_blank" class="w-full text-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-xs font-bold rounded-xl hover:bg-gray-50 transition flex items-center justify-center gap-2">
                                    <i class="fas fa-file-pdf text-red-500"></i> Download PDF
                                </a>

                                <?php if($p['status'] === 'pending'): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to unsubmit and delete this lesson plan?');" class="w-full">
                                        <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                        <button type="submit" name="delete_plan" class="w-full px-4 py-2 bg-red-50 text-red-600 text-xs font-bold rounded-xl hover:bg-red-100 transition flex items-center justify-center gap-2 border border-red-100">
                                            <i class="fas fa-trash-alt"></i> Delete / Unsubmit
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <div class="text-center py-6 text-gray-400 text-sm">No lesson plans submitted yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>

