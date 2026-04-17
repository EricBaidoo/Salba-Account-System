<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// 1. Fetch Teacher's explicit class allocations
$allocated_classes = [];
if ($_SESSION['role'] === 'admin') {
    $res = $conn->query("SELECT name as class_name FROM classes ORDER BY name");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
    
    // Fallback if classes table is empty
    if(empty($allocated_classes)) {
        $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
        while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
    }
} else {
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class_name'];
}
$selected_class = $_GET['class'] ?? ($allocated_classes[0] ?? '');

// 2. Fetch specific subjects assigned to this teacher for the selected class OR mapped class subjects
$allocated_subjects = [];
if ($selected_class) {
    if ($_SESSION['role'] === 'admin') {
        $res = $conn->query("SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_name = '$selected_class' ORDER BY s.name");
        while($r = $res->fetch_assoc()) $allocated_subjects[$r['id']] = $r['name'];
    } else {
        // Teacher Logic:
        // A: Check if they are the Class Teacher (Generalist)
        $is_class_head_res = $conn->query("SELECT id FROM teacher_allocations WHERE teacher_id = $uid AND class_name = '$selected_class' AND year = '$current_year' AND is_class_teacher = 1 LIMIT 1");
        
        if ($is_class_head_res && $is_class_head_res->num_rows > 0) {
            // They are the Class Teacher. They get ALL subjects assigned to this class.
            $res = $conn->query("SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_name = '$selected_class' ORDER BY s.name");
            while($r = $res->fetch_assoc()) $allocated_subjects[$r['id']] = $r['name'];
        } else {
            // They are NOT the Class Teacher. Check specific subject allocations.
            $res = $conn->query("
                SELECT s.id, s.name 
                FROM teacher_allocations ta 
                JOIN subjects s ON ta.subject_id = s.id 
                WHERE ta.teacher_id = $uid AND ta.class_name = '$selected_class' AND ta.year = '$current_year' AND ta.is_subject_teacher = 1
            ");
            while($r = $res->fetch_assoc()) {
                if($r['id']) $allocated_subjects[$r['id']] = $r['name'];
            }
        }
    }
}
$selected_subject_id = $_GET['subject'] ?? (array_keys($allocated_subjects)[0] ?? '');
$selected_subject_name = $allocated_subjects[$selected_subject_id] ?? '';

// 3. Fetch Admin Assessment Configurations for Auto-scaling
$assessment_configs = [];
// Internal Rules (Scale to their respective weights)
$conf_res = $conn->query("SELECT id, assessment_name, max_marks_allocation, is_exam, is_locked FROM assessment_configurations WHERE academic_year = '$current_year' AND semester = '$current_term'");
while($c = $conf_res->fetch_assoc()) {
    $assessment_configs['sba_'.$c['id']] = [
        'name' => $c['assessment_name'],
        'weight' => floatval($c['max_marks_allocation']),
        'is_exam' => (bool)$c['is_exam'],
        'is_locked' => (bool)$c['is_locked']
    ];
}

$selected_assessment_id = $_GET['assessment'] ?? (array_keys($assessment_configs)[0] ?? '');
$selected_assessment = $assessment_configs[$selected_assessment_id] ?? null;

// PROCESS GRADES
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grades'])) {
    if ($selected_class && $selected_subject_name && $selected_assessment) {
        $count = 0;
        $ass_name = $selected_assessment['name'];
        $ass_weight = $selected_assessment['weight'];

        foreach ($_POST['marks'] as $student_id => $raw_marks) {
            $sid = intval($student_id);
            $raw_out_of = $ass_weight; // System-Defined Base
            $raw_marks = floatval($raw_marks);
            $comment = $conn->real_escape_string($_POST['comments'][$sid] ?? '');

            if ($raw_marks !== '') {
                // Validation: Prevent entering figure higher than assessment max
                if ($raw_marks > $ass_weight) {
                    $error = "Institutional Security: Student #$sid cannot have marks ($raw_marks) exceeding assessment maximum ($ass_weight).";
                    continue; 
                }

                // Mathematical Logic: Entering raw points directly out of the Weight
                $scaled_mark = $raw_marks; 

                $check = $conn->query("SELECT id FROM grades WHERE student_id = $sid AND subject = '$selected_subject_name' AND assessment_type = '$ass_name' AND semester = '$current_term' AND year = '$current_year'");
                
                if ($check->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE grades SET marks = ?, out_of = ?, comments = ? WHERE student_id = ? AND subject = ? AND assessment_type = ? AND semester = ? AND year = ?");
                    $stmt->bind_param("ddsissss", $scaled_mark, $ass_weight, $comment, $sid, $selected_subject_name, $ass_name, $current_term, $current_year);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO grades (student_id, class_name, subject, marks, out_of, assessment_type, semester, year, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issddssss", $sid, $selected_class, $selected_subject_name, $scaled_mark, $ass_weight, $ass_name, $current_term, $current_year, $comment);
                    $stmt->execute();
                }
                $count++;
            }
        }
        $success = "Successfully auto-scaled and saved grades for $count students!";
    } else {
        $error = "Missing configuration boundaries. Ensure class, subject, and assessment type are selected.";
    }
}

// Fetch students for Gradebook iteration
$students = [];
if ($selected_class && $selected_subject_name && $selected_assessment) {
    $ass_name = $selected_assessment['name'];
    $stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, g.marks as scaled_marks, g.comments 
        FROM students s 
        LEFT JOIN grades g ON s.id = g.student_id AND g.subject = ? AND g.assessment_type = ? AND g.semester = ? AND g.year = ?
        WHERE s.class = ? AND s.status = 'active'
        ORDER BY s.first_name ASC
    ");
    $stmt->bind_param("sssss", $selected_subject_name, $ass_name, $current_term, $current_year, $selected_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $students[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto-Scaling Gradebook</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .glass-header { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-bottom: 1px solid rgba(0,0,0,0.05); }
        .grade-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(0,0,0,0.05); }
        .grade-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.1); border-color: rgba(245, 158, 11, 0.2); }
        .input-active { border-bottom: 2px solid #f59e0b !important; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php 
    if ($_SESSION['role'] === 'admin') {
        include '../../includes/sidebar.php';
    } else {
        include '../../includes/top_nav.php';
    }
    ?>

    <main class="admin-main-content <?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : 'w-full' ?> p-4 md:p-8 min-h-screen bg-white">
        <!-- Modern Header -->
        <div class="glass-header px-10 py-8 sticky top-0 z-40 bg-white/80">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div>
                    <div class="flex items-center gap-2 text-amber-500 font-bold text-xs uppercase tracking-widest mb-2">
                        <i class="fas fa-graduation-cap text-[10px]"></i> Academic Records
                    </div>
                    <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">
                        Auto-Scaling <span class="text-amber-600">Gradebook</span>
                    </h1>
                </div>
                <div class="flex gap-3">
                    <a href="dashboard.php" class="bg-gray-50 text-gray-500 px-5 py-2.5 rounded-xl font-bold text-sm hover:bg-gray-100 transition flex items-center gap-2 border border-gray-100">
                        <i class="fas fa-arrow-left text-xs"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto p-8">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if(empty($allocated_classes)): ?>
                <div class="bg-white p-12 text-center rounded-xl shadow-sm border border-gray-100">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center text-4xl text-gray-300 mx-auto mb-4">
                        <i class="fas fa-link-slash"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">No Classes Assigned</h3>
                    <p class="text-gray-500">You have no active teaching assignments.</p>
                </div>
            <?php else: ?>
                <!-- Smart Filters -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8 flex items-center gap-4 relative overflow-hidden group">
                    <div class="absolute -right-4 -top-4 opacity-[0.03] text-gray-900 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-calculator text-9xl"></i>
                    </div>
                    <form method="GET" class="flex flex-wrap md:flex-nowrap items-center gap-6 w-full relative z-10">
                        <div class="flex-1 min-w-[150px]">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Target Class</label>
                            <select name="class" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-amber-500 font-bold text-gray-700 shadow-inner appearance-none transition-all" onchange="this.form.submit()">
                                <?php foreach($allocated_classes as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Subject Selection</label>
                            <select name="subject" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-amber-500 font-bold text-gray-700 shadow-inner appearance-none transition-all" onchange="this.form.submit()">
                                <?php foreach($allocated_subjects as $id => $name): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= $selected_subject_id == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-[1.5] min-w-[250px]">
                            <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Assessment Context</label>
                            <div class="relative">
                                <select name="assessment" class="w-full px-5 py-3 bg-amber-50 border border-amber-100 rounded-xl focus:ring-2 focus:ring-amber-500 font-black text-amber-900 shadow-sm appearance-none transition-all" onchange="this.form.submit()">
                                    <?php foreach($assessment_configs as $id => $conf): ?>
                                        <option value="<?= htmlspecialchars($id) ?>" <?= $selected_assessment_id === $id ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($conf['name']) ?> (Max Base: <?= $conf['weight'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-amber-400 opacity-50">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Grading Grid -->
                <?php if($selected_class && $selected_subject_name && $selected_assessment): ?>
                    <form method="POST">
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                            <div class="px-8 py-5 border-b flex justify-between items-center <?= $selected_assessment['is_exam'] ? 'bg-red-50/50 border-red-100' : 'bg-amber-50/50 border-amber-100' ?>">
                                <div>
                                    <h3 class="font-bold text-gray-900 text-lg flex items-center gap-3">
                                        <?= htmlspecialchars($selected_subject_name) ?> <i class="fas fa-arrow-right-long text-gray-300"></i> <?= htmlspecialchars($selected_assessment['name']) ?>
                                        <?php if($selected_assessment['is_locked'] ?? false): ?>
                                            <span class="bg-red-50 text-red-600 text-[10px] px-2 py-1 rounded border border-red-100 flex items-center gap-1">
                                                <i class="fas fa-lock text-[9px]"></i> LOCKED FOR TEACHERS
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-[10px] font-black uppercase tracking-widest mt-1 <?= $selected_assessment['is_exam'] ? 'text-red-500' : 'text-amber-600' ?>">
                                        Internal Scaling Engine Output Matrix: <span class="bg-white px-2 py-0.5 rounded shadow-sm border border-opacity-50 ml-1"><?= $selected_assessment['weight'] ?> points max</span>
                                    </p>
                                </div>
                                <div class="bg-white/50 px-3 py-1.5 rounded-lg border border-opacity-20 flex items-center gap-2">
                                     <i class="fas fa-robot text-gray-400 text-xs"></i>
                                     <span class="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Auto-Scale Enabled</span>
                                </div>
                            </div>

                            <table class="w-full text-left">
                                <thead class="bg-gray-50/50 text-gray-400 border-b border-gray-100 text-[10px] uppercase font-black tracking-widest text-center">
                                    <tr>
                                        <th class="px-8 py-5 text-left">Student Identity</th>
                                        <th class="px-8 py-5">Marks Awarded</th>
                                        <th class="px-4 py-5 text-gray-300">/</th>
                                        <th class="px-8 py-5">Target Weight</th>
                                        <th class="px-8 py-5 text-left">Evaluation Comments</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50/50">
                                    <?php foreach($students as $idx => $s): ?>
                                        <tr class="hover:bg-gray-50/30 transition group">
                                            <td class="px-8 py-5 font-bold text-gray-900">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 border border-gray-100 group-hover:bg-amber-50 group-hover:text-amber-600 group-hover:border-amber-100 transition-colors">
                                                        <?= substr($s['first_name'], 0, 1) . substr($s['last_name'], 0, 1) ?>
                                                    </div>
                                                    <div>
                                                        <div class="font-bold tracking-tight"><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></div>
                                                        <?php if($s['scaled_marks'] !== null): ?>
                                                            <div class="text-[9px] font-black text-emerald-600 uppercase tracking-wide flex items-center gap-1 mt-0.5">
                                                                <i class="fas fa-check-circle"></i> Scaled: <?= round($s['scaled_marks'],1) ?> / <?= $selected_assessment['weight'] ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5 text-center">
                                                <input type="number" step="0.1" name="marks[<?= $s['id'] ?>]" value="<?= $s['scaled_marks'] !== null ? round($s['scaled_marks'], 1) : '' ?>" max="<?= $selected_assessment['weight'] ?>" min="0" placeholder="e.g. <?= floor($selected_assessment['weight']*0.8) ?>" class="w-24 px-3 py-2.5 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-amber-500 text-center font-black text-lg shadow-inner transition-all">
                                            </td>
                                            <td class="px-2 py-5 text-center font-black text-gray-200">/</td>
                                            <td class="px-8 py-5 text-center">
                                                <div class="w-20 mx-auto px-3 py-2.5 bg-gray-100/50 rounded-xl text-gray-400 font-black text-sm border border-gray-100 shadow-sm flex items-center justify-center">
                                                    <?= $selected_assessment['weight'] ?>
                                                </div>
                                            </td>
                                            <td class="px-8 py-5">
                                                <div class="relative">
                                                     <i class="far fa-comment-dots absolute left-0 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                                                     <input type="text" name="comments[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['comments'] ?? '') ?>" placeholder="Add remark..." class="w-full pl-6 bg-transparent border-b border-gray-100 focus:border-amber-400 focus:outline-none text-sm py-1.5 text-gray-500 transition-all">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex flex-col md:flex-row justify-between items-center bg-gray-50 p-8 rounded-2xl border border-gray-100 gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 bg-white rounded-xl shadow-sm border border-gray-100 flex items-center justify-center text-amber-500 text-xl">
                                    <i class="fas fa-square-root-variable"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-gray-900 text-sm">System Math Engine</h4>
                                    <p class="text-xs text-gray-500">Validation: <code>Entered Marks ≤ <?= $selected_assessment['weight'] ?> (Official Max)</code></p>
                                </div>
                            </div>
                            <button type="submit" name="save_grades" class="bg-gray-900 text-white font-black py-4 px-10 rounded-2xl shadow-xl hover:bg-black hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-3 text-lg">
                                <i class="fas fa-bolt-lightning"></i> Process & Save Grades
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
