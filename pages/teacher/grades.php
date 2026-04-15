<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];
$current_term = getCurrentTerm($conn);
$current_year = getAcademicYear($conn);

// Fetch Profile Data for Greeting
$prof_res = $conn->query("SELECT full_name, photo_path, job_title FROM staff_profiles WHERE user_id = $uid LIMIT 1");
$profile = $prof_res->fetch_assoc();
$display_name = $profile['full_name'] ?? $_SESSION['username'];
$job_title = $profile['job_title'] ?? 'Facilitator';
$photo = $profile['photo_path'] ?? '';


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
        // Teacher specific subjects in this class
        $res = $conn->query("
            SELECT s.id, s.name 
            FROM teacher_allocations ta 
            LEFT JOIN subjects s ON ta.subject_id = s.id 
            WHERE ta.teacher_id = $uid AND ta.class_name = '$selected_class' AND ta.year = '$current_year'
        ");
        while($r = $res->fetch_assoc()) {
            if($r['id']) $allocated_subjects[$r['id']] = $r['name'];
        }
        // If empty (e.g. Class Teacher role), pull all mapped subjects for the class
        if (empty($allocated_subjects)) {
            $res = $conn->query("SELECT s.id, s.name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_name = '$selected_class' ORDER BY s.name");
            while($r = $res->fetch_assoc()) $allocated_subjects[$r['id']] = $r['name'];
        }
    }
}
$selected_subject_id = $_GET['subject'] ?? (array_keys($allocated_subjects)[0] ?? '');
$selected_subject_name = $allocated_subjects[$selected_subject_id] ?? '';

// 3. Fetch Admin Assessment Configurations for Auto-scaling
$assessment_configs = [];
$conf_res = $conn->query("SELECT id, assessment_name, max_marks_allocation FROM assessment_configurations WHERE academic_year = '$current_year' AND term = '$current_term'");
while($c = $conf_res->fetch_assoc()) {
    $assessment_configs[$c['id']] = [
        'name' => $c['assessment_name'],
        'weight' => floatval($c['max_marks_allocation'])
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
                // Auto-Scaling Mathematical Logic!
                // Instead of saving 50/100, we save the geometrically scaled value into `marks` and `out_of` becomes the `max_marks_allocation`.
                // Example: Teacher graded 30 out of 50. Admin allocated weight: 15.
                // Math: (30 / 50) * 15 = 9 marks.
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

// Safe Migration: Ensure columns exist for class_name and assessment_type (MySQL 5.7+ compatible)
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'class_name' => "VARCHAR(50) AFTER student_id",
    'assessment_type' => "VARCHAR(100) AFTER out_of"
];

foreach ($cols_to_check as $col => $def) {
    $exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'grades' AND COLUMN_NAME = '$col'")->fetch_row()[0];
    if (!$exists) {
        $conn->query("ALTER TABLE grades ADD COLUMN `$col` $def");
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
    <title>Auto-Scaling Gradebook - Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class=" min-h-screen relative">
        <div class="bg-indigo-600 px-8 py-10 text-white relative overflow-hidden">
            <div class="absolute right-0 top-0 opacity-10 pointer-events-none p-4">
                <i class="fas fa-graduation-cap text-9xl"></i>
            </div>
            <div class="flex items-center gap-6 relative z-10">
                <div class="w-20 h-20 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 overflow-hidden shadow-xl">
                    <?php if($photo): ?>
                        <img src="../../<?= htmlspecialchars($photo) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fas fa-user-tie text-3xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-4xl font-extrabold tracking-tight">Welcome Back, <?= htmlspecialchars($display_name) ?>!</h1>
                    <p class="text-indigo-100 font-medium mt-1 flex items-center gap-2">
                        <span class="bg-white/20 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider backdrop-blur-sm"><?= htmlspecialchars($job_title) ?></span>
                        <span class="opacity-60">•</span>
                        <span>Official Gradebook Hub</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30 shadow-sm flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                <i class="fas fa-star text-yellow-500"></i> My Gradebook
            </h1>
            <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                Term: <?= $current_term ?> · Year: <?= $current_year ?>
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
                            <select name="assessment" class="w-full px-4 py-2 bg-yellow-50 border border-yellow-200 rounded-lg focus:ring-2 focus:ring-yellow-500 font-medium text-yellow-900" onchange="this.form.submit()">
                                <?php foreach($assessment_configs as $id => $conf): ?>
                                    <option value="<?= htmlspecialchars($id) ?>" <?= $selected_assessment_id == $id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($conf['name']) ?> (Max: <?= $conf['weight'] ?>)
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
                            <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-100 flex justify-between items-center">
                                <div>
                                    <h3 class="font-bold text-yellow-900 text-lg">
                                        <?= htmlspecialchars($selected_subject_name) ?> <i class="fas fa-arrow-right text-yellow-400 text-sm mx-1"></i> <?= htmlspecialchars($selected_assessment['name']) ?>
                                    </h3>
                                    <p class="text-xs text-yellow-700 font-medium mt-0.5">Admin Official Target Math Weight: <span class="bg-yellow-200 px-1 rounded text-black"><?= $selected_assessment['weight'] ?> points</span></p>
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
                                                    <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 text-[10px] rounded uppercase">Already Sent</span>
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
                            <button type="submit" name="save_grades" class="bg-yellow-500 text-white font-bold py-3 px-8 rounded-lg shadow border border-transparent hover:bg-yellow-600 transition flex items-center gap-2 text-lg">
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

