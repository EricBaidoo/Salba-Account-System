<?php
session_start();
require_once '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$render_type = (isset($_GET['view']) && $_GET['view'] === 'html') ? 'html' : 'pdf';
if ($render_type === 'pdf' && ($_SESSION['role'] ?? '') !== 'admin') {
    die('Institutional Security: PDF generation is restricted to the Administrative role. Please use the digital preview only.');
}

$selected_class = $_GET['class'] ?? '';
$selected_assessment = $_GET['assessment_type'] ?? '';
if (!$selected_class || !$selected_assessment) {
    die('Missing required class or assessment selection.');
}

$current_year = getAcademicYear($conn);
$current_term = getCurrentSemester($conn);

$school_name = getSystemSetting($conn, 'school_name', 'SALBA MONTESSORI INTERNATIONAL SCHOOL');

$students = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE class = ? AND status = 'active' ORDER BY last_name, first_name");
$stmt->bind_param('s', $selected_class);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $students[$row['id']] = $row;
}
$stmt->close();

$subjects = [];
$grade_matrix = [];
$max_out_of = 0;

$valid_subjects = [];
$subject_abbreviations = [];
if ($selected_class) {
    $stmt = $conn->prepare("
        SELECT DISTINCT s.name, s.abbreviation
        FROM subjects s
        LEFT JOIN class_subjects cs ON s.id = cs.subject_id AND cs.class_name = ?
        LEFT JOIN teacher_allocations ta ON s.id = ta.subject_id AND ta.class_name = ? AND ta.year = ?
        WHERE cs.subject_id IS NOT NULL OR ta.subject_id IS NOT NULL
    ");
    if (!$stmt) {
        die("Database Error (fetching subjects): " . $conn->error);
    }
    $stmt->bind_param('sss', $selected_class, $selected_class, $current_year);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $valid_subjects[] = $r['name'];
        $subject_abbreviations[$r['name']] = !empty($r['abbreviation']) ? $r['abbreviation'] : $r['name'];
    }
    $stmt->close();
}

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
// Hardcoded abbreviations removed in favor of DB-driven aliases

if ($render_type === 'pdf') {
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($selected_assessment) ?> RESULTS - <?= htmlspecialchars($selected_class) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; margin: 0; padding: 0; }
        .page { width: 100%; padding: 1px; box-sizing: border-box; }
        .header { text-align: center; margin-bottom: 20px; }
        .logo { width: 100px; height: auto; display: block; margin: 0 auto 15px auto; }
        .school-name { font-size: 20px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; }
        .class-name { font-size: 18px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; }
        .assessment-name { font-size: 18px; font-weight: bold; text-transform: uppercase; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1.5px solid #000; padding: 8px 6px; font-size: 12px; }
        th { font-weight: bold; text-transform: uppercase; text-align: left; }
        .text-center { text-align: center; }
        
        .web-tools { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 12px 20px; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; font-family: sans-serif; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 8px; text-decoration: none; color: #ffffff; font-size: 12px; font-weight: bold; border: none; cursor: pointer; }
        .btn-primary { background: #2563eb; }
        .btn-secondary { background: #475569; }
    </style>
</head>
<body style="<?= ($render_type === 'html') ? 'background: #f1f5f9; padding-bottom: 60px;' : '' ?>">
    <?php if ($render_type === 'html'): ?>
        <div class="web-tools">
            <div><strong>Print Preview</strong></div>
            <div style="display: flex; gap: 8px;">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="?class=<?= urlencode($selected_class) ?>&assessment_type=<?= urlencode($selected_assessment) ?>" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Download PDF</a>
                <?php endif; ?>
                <button onclick="window.close()" class="btn btn-secondary"><i class="fas fa-times"></i> Close</button>
            </div>
        </div>
    <?php endif; ?>

    <div class="page" style="<?= ($render_type === 'html') ? 'max-width: 900px; margin: 40px auto; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-radius: 8px;' : '' ?>">
        <div class="header">
            <?php 
                $logo_path = '../../assets/img/salba_logo.jpg';
                if (file_exists($logo_path)): 
            ?>
                <img src="<?= $logo_path ?>" class="logo" alt="School Logo">
            <?php endif; ?>
            <div class="school-name"><?= htmlspecialchars($school_name) ?></div>
            <div class="class-name"><?= htmlspecialchars($selected_class) ?></div>
            <div class="term-year" style="font-size: 14px; font-weight: bold; margin-bottom: 10px;">TERM: <?= htmlspecialchars($current_term) ?> &nbsp;&nbsp;|&nbsp;&nbsp; ACADEMIC YEAR: <?= htmlspecialchars($current_year) ?></div>
            <div class="assessment-name"><?= htmlspecialchars($selected_assessment) ?> RESULTS</div>
        </div>

        <?php if (empty($subjects)): ?>
            <div style="text-align: center; padding: 40px; font-style: italic; border: 1px solid #ccc;">No assessment records found for this class and assessment type.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width: 20%; vertical-align: top;">NAME OF STUDENT</th>
                        <th colspan="<?= count($subjects) ?>" class="text-center">SCORE (OVER <?= $max_out_of ?: '—' ?>)</th>
                        <th rowspan="2" style="vertical-align: bottom;" class="text-center">TOTAL.</th>
                        <th rowspan="2" style="vertical-align: bottom;" class="text-center">POSITION</th>
                    </tr>
                    <tr>
                        <?php foreach ($subjects as $subject): ?>
                            <th class="text-center" style="font-size: 11px;"><?= htmlspecialchars($subject_abbreviations[$subject] ?? $subject) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student_id => $student): ?>
                        <tr>
                            <td><?= htmlspecialchars(strtoupper($student['first_name'] . ' ' . $student['last_name'])) ?></td>
                            <?php foreach ($subjects as $subject): ?>
                                <?php $mark = $grade_matrix[$student_id][$subject] ?? ''; ?>
                                <td class="text-center"><?= $mark !== '' ? htmlspecialchars($mark) : '' ?></td>
                            <?php endforeach; ?>
                            <td class="text-center" style="font-weight: bold;"><?= htmlspecialchars($student_totals[$student_id]) ?></td>
                            <td class="text-center" style="font-weight: bold;"><?= htmlspecialchars($student_positions[$student_id]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($render_type === 'pdf'): ?>
        <?php
            $html = ob_get_clean();
            $mpdf = new \Mpdf\Mpdf([
                'format' => 'A4', // Portrait format
                'margin_left' => 1,
                'margin_right' => 1,
                'margin_top' => 1,
                'margin_bottom' => 1,
            ]);
            $mpdf->WriteHTML($html);
            $filename = sprintf('%s_RESULTS_%s.pdf', preg_replace('/[^A-Za-z0-9_-]+/', '_', $selected_assessment), preg_replace('/[^A-Za-z0-9_-]+/', '_', $selected_class));
            $mpdf->Output($filename, 'D');
        ?>
    <?php endif; ?>
</body>
</html>
