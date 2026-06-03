<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$current_year = getAcademicYear($conn);
$current_term = getCurrentSemester($conn);
$user_role = $_SESSION['role'] ?? 'staff';
$uid = $_SESSION['user_id'];

$allocated_classes = [];
if ($user_role === 'admin' || $user_role === 'supervisor') {
    $res = $conn->query("SELECT DISTINCT class FROM students WHERE status='active' ORDER BY class");
    while ($r = $res->fetch_assoc()) {
        $allocated_classes[] = $r['class'];
    }
} else {
    $res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $uid AND year = '$current_year'");
    while ($r = $res->fetch_assoc()) {
        $allocated_classes[] = $r['class_name'];
    }
}

$selected_class = $_GET['class'] ?? ($allocated_classes[0] ?? '');
$selected_assessment = $_GET['assessment_type'] ?? '';

// Fetch available assessment types for the class
$available_assessments = [];
if ($selected_class) {
    $stmt = $conn->prepare("SELECT DISTINCT assessment_type FROM grades WHERE class_name = ? AND semester = ? AND year = ? ORDER BY assessment_type");
    $stmt->bind_param('sss', $selected_class, $current_term, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $available_assessments[] = $r['assessment_type'];
    }
    $stmt->close();
}

if (!$selected_assessment && !empty($available_assessments)) {
    $selected_assessment = $available_assessments[0];
}

$students = [];
if ($selected_class) {
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class = ? AND status = 'active' ORDER BY last_name, first_name");
    $stmt->bind_param('s', $selected_class);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $students[$r['id']] = $r;
    }
    $stmt->close();
}

$subjects = [];
$grade_matrix = [];
$max_out_of = 0;

$valid_subjects = [];
if ($selected_class) {
    $stmt = $conn->prepare("
        SELECT DISTINCT s.name 
        FROM subjects s
        LEFT JOIN class_subjects cs ON s.id = cs.subject_id AND cs.class_name = ?
        LEFT JOIN teacher_allocations ta ON s.id = ta.subject_id AND ta.class_name = ? AND ta.year = ?
        WHERE cs.subject_id IS NOT NULL OR ta.subject_id IS NOT NULL
    ");
    $stmt->bind_param('sss', $selected_class, $selected_class, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $valid_subjects[] = $r['name'];
    }
    $stmt->close();
}

if ($selected_class && $selected_assessment) {
    $stmt = $conn->prepare("SELECT student_id, subject, marks, out_of FROM grades WHERE class_name = ? AND assessment_type = ? AND semester = ? AND year = ?");
    $stmt->bind_param('ssss', $selected_class, $selected_assessment, $current_term, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        if (in_array($row['subject'], $valid_subjects)) {
            $subjects[$row['subject']] = true;
            $grade_matrix[$row['student_id']][$row['subject']] = floatval($row['marks']);
            if (floatval($row['out_of']) > $max_out_of) {
                $max_out_of = floatval($row['out_of']);
            }
        }
    }
    $stmt->close();
}

$subjects = array_keys($subjects);
sort($subjects);

// Calculate totals and positions
$student_totals = [];
foreach ($students as $student_id => $student) {
    $total = 0;
    foreach ($subjects as $subject) {
        $total += $grade_matrix[$student_id][$subject] ?? 0;
    }
    $student_totals[$student_id] = $total;
}

$ranked_scores = array_unique(array_values($student_totals));
rsort($ranked_scores);
$student_positions = [];
foreach ($student_totals as $student_id => $total) {
    $pos = array_search($total, $ranked_scores) + 1;
    $suffix = 'TH';
    if ($pos % 10 == 1 && $pos % 100 != 11) $suffix = 'ST';
    elseif ($pos % 10 == 2 && $pos % 100 != 12) $suffix = 'ND';
    elseif ($pos % 10 == 3 && $pos % 100 != 13) $suffix = 'RD';
    $student_positions[$student_id] = $pos . $suffix;
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');

function abbr_subject($subject) {
    $upper = strtoupper($subject);
    if (strpos($upper, 'ENGLISH') !== false) return 'ENGLISH';
    if (strpos($upper, 'RELIGIOUS') !== false) return 'R.M.E';
    if (strpos($upper, 'COMPUTING') !== false) return 'COMPUTING';
    if (strpos($upper, 'CREATIVE ARTS') !== false) return 'C. ARTS';
    if (strpos($upper, 'OUR WORLD') !== false) return 'O.W.O.P';
    if (strpos($upper, 'INFORMATION') !== false) return 'I.C.T';
    if (strpos($upper, 'PHYSICAL EDUCATION') !== false) return 'P.E';
    if (strpos($upper, 'SOCIAL STUDIES') !== false) return 'SOC. STUD';
    if (strpos($upper, 'INTEGRATED SCIENCE') !== false) return 'INT. SCI';
    if (strpos($upper, 'MATHEMATICS') !== false) return 'MATHS';
    if (strpos($upper, 'GHANAIAN LANGUAGE') !== false) return 'GH. LANG';
    if (strpos($upper, 'SCIENCE') !== false) return 'SCIENCE';
    return $subject;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Assessment Breakdown</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50">
    <?php 
    if ($_SESSION['role'] === 'admin') {
        include '../../includes/sidebar.php';
    } else {
        include '../../includes/top_nav.php';
    }
    ?>

    <main class="admin-main-content <?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : 'w-full' ?> p-4 md:p-8 min-h-screen">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 mb-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <p class="text-sm uppercase tracking-[0.3em] text-slate-400 font-bold mb-2">Academic Module</p>
                        <h1 class="text-3xl font-extrabold text-slate-900">Class Assessment Breakdown</h1>
                        <p class="max-w-2xl text-sm text-slate-600 mt-2">View class-level assessment scores by subject and test. Use the print link to create a noticeboard-friendly output.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="print_transcript_breakdown.php?class=<?= urlencode($selected_class) ?>&assessment_type=<?= urlencode($selected_assessment) ?>&view=html" target="_blank" class="inline-flex items-center gap-2 rounded-full bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-700 transition">
                            <i class="fas fa-eye"></i> Preview Print
                        </a>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="print_transcript_breakdown.php?class=<?= urlencode($selected_class) ?>&assessment_type=<?= urlencode($selected_assessment) ?>" class="inline-flex items-center gap-2 rounded-full bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700 transition">
                                <i class="fas fa-file-pdf"></i> Download PDF
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 mb-6">
                <form method="GET" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Select Class</label>
                        <select name="class" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm" onchange="this.form.submit()">
                            <?php foreach ($allocated_classes as $class_name): ?>
                                <option value="<?= htmlspecialchars($class_name) ?>" <?= $selected_class === $class_name ? 'selected' : '' ?>><?= htmlspecialchars($class_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-[0.2em] text-slate-500 font-bold">Select Assessment Type</label>
                        <select name="assessment_type" class="mt-2 w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm" onchange="this.form.submit()">
                            <?php foreach ($available_assessments as $assessment_type): ?>
                                <option value="<?= htmlspecialchars($assessment_type) ?>" <?= $selected_assessment === $assessment_type ? 'selected' : '' ?>><?= htmlspecialchars($assessment_type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>

            <?php if (!$selected_class): ?>
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-3xl p-6">No class assignment found for your account. Please contact the administrator.</div>
            <?php elseif (!$selected_assessment): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-3xl p-6">No assessments found for this class in the current term.</div>
            <?php else: ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-slate-500 font-semibold">Class</p>
                            <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($selected_class) ?></h2>
                        </div>
                        <div>
                            <p class="text-sm uppercase tracking-[0.25em] text-slate-500 font-semibold">Assessment Type</p>
                            <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($selected_assessment) ?></h2>
                        </div>
                    </div>

                    <?php if (empty($subjects)): ?>
                        <div class="bg-slate-50 border border-slate-200 rounded-3xl p-8 text-center text-slate-600">
                            <p class="font-bold mb-2">No grades recorded yet.</p>
                            <p>Start by entering test marks in the Grades module, then return here to print the breakdown.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse text-sm">
                                <thead>
                                    <tr>
                                        <th rowspan="2" class="border border-slate-200 px-4 py-3 bg-slate-100 text-left text-slate-700 uppercase tracking-[0.1em] text-[0.7rem] align-bottom">Name of Student</th>
                                        <th colspan="<?= count($subjects) ?>" class="border border-slate-200 px-4 py-2 bg-slate-100 text-center text-slate-700 uppercase tracking-[0.1em] text-[0.7rem]">Score (Over <?= $max_out_of ?: '—' ?>)</th>
                                        <th rowspan="2" class="border border-slate-200 px-4 py-3 bg-slate-100 text-center text-slate-700 uppercase tracking-[0.1em] text-[0.7rem] align-bottom">Total</th>
                                        <th rowspan="2" class="border border-slate-200 px-4 py-3 bg-slate-100 text-center text-slate-700 uppercase tracking-[0.1em] text-[0.7rem] align-bottom">Position</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($subjects as $subject): ?>
                                            <th class="border border-slate-200 px-4 py-2 bg-slate-100 text-center text-slate-700 uppercase tracking-[0.1em] text-[0.7rem]"><?= htmlspecialchars(abbr_subject($subject)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student_id => $student): ?>
                                        <tr class="even:bg-slate-50 hover:bg-slate-100 transition">
                                            <td class="border border-slate-200 px-4 py-3 whitespace-nowrap font-medium text-slate-900"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                            <?php foreach ($subjects as $subject): ?>
                                                <?php $mark = $grade_matrix[$student_id][$subject] ?? ''; ?>
                                                <td class="border border-slate-200 px-4 py-3 text-center"><?= $mark !== '' ? htmlspecialchars($mark) : '—' ?></td>
                                            <?php endforeach; ?>
                                            <td class="border border-slate-200 px-4 py-3 text-center font-bold text-slate-900"><?= htmlspecialchars($student_totals[$student_id]) ?></td>
                                            <td class="border border-slate-200 px-4 py-3 text-center font-bold text-indigo-600"><?= htmlspecialchars($student_positions[$student_id]) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
