<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];
$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);

// 1. Fetch Teacher's explicit class allocations
$allocated_classes = [];
if ($_SESSION['role'] === 'admin') {
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
    while($r = $res->fetch_assoc()) $allocated_classes[] = $r['class'];
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
$conf_res = $conn->query("SELECT id, assessment_name, max_marks_allocation, is_exam FROM assessment_configurations WHERE academic_year = '$current_year' AND term = '$current_term'");
while($c = $conf_res->fetch_assoc()) {
    $assessment_configs['sba_'.$c['id']] = [
        'name' => $c['assessment_name'],
        'weight' => floatval($c['max_marks_allocation']),
        'is_exam' => (bool)$c['is_exam']
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
            $raw_out_of = floatval($_POST['out_of'][$sid] ?? 100);
            $raw_marks = floatval($raw_marks);
            $comment = $conn->real_escape_string($_POST['comments'][$sid] ?? '');

            if ($raw_marks !== '' && $raw_out_of > 0) {
                // Auto-Scaling Mathematical Logic
                // Scale native score (e.g. 8/10) directly to the Internal Target Config (e.g. 30). Output = 24.
                // Exam native score (e.g. 80/100) scales directly to 100 Target Config. Output = 80.
                $scaled_mark = ($raw_marks / $raw_out_of) * $ass_weight;

                $check = $conn->query("SELECT id FROM grades WHERE student_id = $sid AND subject = '$selected_subject_name' AND assessment_type = '$ass_name' AND term = '$current_term' AND year = '$current_year'");
                
                if ($check->num_rows > 0) {
                    $stmt = $conn->prepare("UPDATE grades SET marks = ?, out_of = ?, comments = ? WHERE student_id = ? AND subject = ? AND assessment_type = ? AND term = ? AND year = ?");
                    $stmt->bind_param("ddssisss", $scaled_mark, $ass_weight, $comment, $sid, $selected_subject_name, $ass_name, $current_term, $current_year);
                    $stmt->execute();
                } else {
                    $stmt = $conn->prepare("INSERT INTO grades (student_id, class_name, subject, marks, out_of, assessment_type, term, year, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
        LEFT JOIN grades g ON s.id = g.student_id AND g.subject = ? AND g.assessment_type = ? AND g.term = ? AND g.year = ?
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
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar_admin.php'; ?>
    <?php if ($_SESSION['role'] !== 'admin') include '../../includes/sidebar.php'; // Only fallback if router fails ?>

    <main class="ml-72 min-h-screen relative">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30 shadow-sm">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="fas fa-star text-yellow-500"></i> My Gradebook
            </h1>
            <p class="text-gray-500 mt-2 text-sm">
                Enter your genuine raw scores. The system will mathematically auto-scale them internally to the defined Term Matrix limits.
            </p>
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
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8 flex items-center gap-4 relative overflow-hidden">
                    <div class="absolute right-0 top-0 opacity-5 pointer-events-none p-4">
                        <i class="fas fa-calculator text-9xl"></i>
                    </div>
                    <form method="GET" class="flex items-center gap-4 w-full relative z-10">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Target Class</label>
                            <select name="class" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 font-medium" onchange="this.form.submit()">
                                <?php foreach($allocated_classes as $cl): ?>
                                    <option value="<?= htmlspecialchars($cl) ?>" <?= $selected_class === $cl ? 'selected' : '' ?>><?= htmlspecialchars($cl) ?></option>
                                <?php endselect; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Target Subject</label>
                            <select name="subject" class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:ring-2 focus:ring-yellow-500 font-medium" onchange="this.form.submit()">
                                <?php foreach($allocated_subjects as $id => $name): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= $selected_subject_id == $id ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Assessment Type</label>
                            <select name="assessment" class="w-full px-4 py-2 bg-yellow-50 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-500 font-bold text-yellow-900" onchange="this.form.submit()">
                                <?php foreach($assessment_configs as $id => $conf): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= $selected_assessment_id === $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($conf['name']) ?> (Max Base: <?= $conf['weight'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="pt-5 hidden md:block">
                            <button type="submit" class="bg-gray-800 text-white px-6 py-2 rounded-lg font-bold hover:bg-gray-900 transition shadow-sm border border-transparent">Load Grid</button>
                        </div>
                    </form>
                </div>

                <!-- Grading Grid -->
                <?php if($selected_class && $selected_subject_name && $selected_assessment): ?>
                    <form method="POST">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                            <div class="px-6 py-4 border-b flex justify-between items-center <?= $selected_assessment['is_exam'] ? 'bg-red-50 border-red-100' : 'bg-blue-50 border-blue-100' ?>">
                                <div>
                                    <h3 class="font-bold text-lg <?= $selected_assessment['is_exam'] ? 'text-red-900' : 'text-blue-900' ?>">
                                        <?= htmlspecialchars($selected_subject_name) ?> <i class="fas fa-arrow-right text-opacity-50 text-sm mx-1"></i> <?= htmlspecialchars($selected_assessment['name']) ?>
                                    </h3>
                                    <p class="text-xs font-bold mt-0.5 <?= $selected_assessment['is_exam'] ? 'text-red-600' : 'text-blue-600' ?>">
                                        Internal Scaling Engine Output Matrix: <span class="bg-white px-1.5 py-0.5 rounded shadow-sm border border-opacity-50"><?= $selected_assessment['weight'] ?> points max</span>
                                    </p>
                                </div>
                            </div>

                            <table class="w-full text-left">
                                <thead class="bg-gray-50 text-gray-500 border-b border-gray-100 text-xs uppercase font-bold tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4">Student Name</th>
                                        <th class="px-6 py-4 text-center">Marks Awarded</th>
                                        <th class="px-6 py-4 text-center text-gray-400">/</th>
                                        <th class="px-6 py-4 text-center">Out Of (Total)</th>
                                        <th class="px-6 py-4">Teacher Remark</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach($students as $idx => $s): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4 font-bold text-gray-900">
                                                <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                                                <?php if($s['scaled_marks'] !== null): ?>
                                                    <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-[10px] rounded uppercase font-bold tracking-wide">Recorded (<span class="text-green-900"><?= round($s['scaled_marks'],1) ?>/<?= $selected_assessment['weight'] ?></span>)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="number" step="0.1" name="marks[<?= $s['id'] ?>]" placeholder="e.g. 8" class="w-24 px-3 py-2 border border-gray-300 rounded focus:ring-yellow-500 focus:border-yellow-500 text-center font-bold">
                                            </td>
                                            <td class="px-2 py-4 text-center font-bold text-gray-300">/</td>
                                            <td class="px-6 py-4">
                                                <input type="number" step="0.1" name="out_of[<?= $s['id'] ?>]" value="10" class="w-24 px-3 py-2 border border-gray-200 bg-gray-50 rounded text-gray-500 text-center font-bold" title="What did you grade this test out of natively?">
                                            </td>
                                            <td class="px-6 py-4">
                                                <input type="text" name="comments[<?= $s['id'] ?>]" value="<?= htmlspecialchars($s['comments'] ?? '') ?>" placeholder="Feedback..." class="w-full px-3 py-2 bg-transparent border-b border-gray-200 focus:border-yellow-500 focus:outline-none text-sm text-gray-600">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-between items-center bg-gray-50 p-6 rounded-xl border border-gray-100">
                            <div class="text-gray-500 text-sm font-medium">
                                <i class="fas fa-robot mr-1 text-gray-400"></i> The system will automatically compute the <code>(Marks / Out Of) * <?= $selected_assessment['weight'] ?></code> algorithm.
                            </div>
                            <button type="submit" name="save_grades" class="bg-gray-900 text-white font-bold py-3 px-8 rounded-lg shadow hover:bg-black transition flex items-center gap-2 text-lg">
                                <i class="fas fa-square-root-variable"></i> Auto-Scale & Save
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
