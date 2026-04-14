<?php
session_start();
require_once '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php'); exit;
}

$id = intval($_GET['student'] ?? ($_GET['id'] ?? 0));
$selected_class = $_GET['class'] ?? '';
if (!$id) die("Invalid Student ID.");

$current_year = getAcademicYear($conn);
$current_term = getCurrentTerm($conn);

// Fetch Student Data
$p = $conn->query("SELECT * FROM students WHERE id = $id")->fetch_assoc();
if (!$p) die("Student not found.");

// Fetch Class Size
$class_size = 0;
$class_teacher_name = "__________________________";
if ($selected_class) {
    $size_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE class = '$selected_class' AND status='active'");
    $class_size = $size_res->fetch_assoc()['total'] ?? 0;
    
    // Fetch Class Teacher Name
    $ct_res = $conn->query("
        SELECT u.username as name 
        FROM teacher_allocations ta 
        JOIN users u ON ta.teacher_id = u.id 
        WHERE ta.class_name = '$selected_class' AND ta.year = '$current_year' AND ta.is_class_teacher = 1 
        LIMIT 1
    ");
    if ($ct_res && $row = $ct_res->fetch_assoc()) {
        $class_teacher_name = $row['name'];
    }
}

// Global Transcript Settings
$global_oa_weight = floatval(getSystemSetting($conn, 'term_oa_weight', 30));
$global_exam_weight = floatval(getSystemSetting($conn, 'term_exam_weight', 70));

// Compile Transcript Engine (Ranking & Math)
$transcript_lines = [];
$student_remarks = null;

if ($selected_class && $id) {
    $class_scores = []; 
    
    // Map Configs to Array for robust matching
    $oa_types = []; $exam_types = [];
    $type_res = $conn->query("SELECT assessment_name, is_exam FROM assessment_configurations WHERE academic_year = '$current_year' AND term = '$current_term'");
    while($r = $type_res->fetch_assoc()) {
        if ($r['is_exam']) $exam_types[] = $r['assessment_name'];
        else $oa_types[] = $r['assessment_name'];
    }

    // Fetch all raw scaled grades for class
    $g_res = $conn->query("
        SELECT student_id, subject, marks, assessment_type 
        FROM grades 
        WHERE class_name = '$selected_class' AND term = '$current_term' AND year = '$current_year'
    ");
    
    while($row = $g_res->fetch_assoc()) {
        $sid = $row['student_id'];
        $sub = $row['subject'];
        $type = $row['assessment_type'];
        $m = floatval($row['marks']);
        
        if (!isset($class_scores[$sub][$sid])) {
            $class_scores[$sub][$sid] = ['oa_raw' => 0, 'ex_raw' => 0];
        }
        
        if (in_array($type, $exam_types)) {
            $class_scores[$sub][$sid]['ex_raw'] += $m; 
        } else if (in_array($type, $oa_types)) {
            $class_scores[$sub][$sid]['oa_raw'] += $m; 
        }
    }
    
    // Calculate positions per subject
    foreach ($class_scores as $sub => $scores_array) {
        $all_totals = [];
        foreach ($scores_array as $st_id => $st_data) {
            $final_oa = ($st_data['oa_raw'] * ($global_oa_weight / 100));
            $final_ex = ($st_data['ex_raw'] * ($global_exam_weight / 100));
            $all_totals[$st_id] = $final_oa + $final_ex;
        }
        
        $ranked_scores = array_values($all_totals);
        rsort($ranked_scores);
        
        if (isset($all_totals[$id])) {
            $my_total = $all_totals[$id];
            $pos = array_search($my_total, $ranked_scores) + 1;
            
            $st_data = $scores_array[$id];
            $final_oa = ($st_data['oa_raw'] * ($global_oa_weight / 100));
            $final_ex = ($st_data['ex_raw'] * ($global_exam_weight / 100));
            
            $grade = ''; $remark = '';
            if ($my_total >= 80)      { $grade = 'A'; $remark = 'Advance'; }
            elseif ($my_total >= 70)  { $grade = 'B'; $remark = 'Proficient'; }
            elseif ($my_total >= 60)  { $grade = 'C'; $remark = 'Basic'; }
            elseif ($my_total >= 50)  { $grade = 'D'; $remark = 'Pass'; }
            else                      { $grade = 'F'; $remark = 'Below Basic'; }
            
            $transcript_lines[] = [
                'subject' => $sub,
                'oa' => round($final_oa, 1),
                'ex' => round($final_ex, 1),
                'total' => round($my_total, 1),
                'pos' => $pos,
                'grade' => $grade,
                'remark' => $remark
            ];
        }
    }
    
    // Fetch Remarks
    $rem_res = $conn->query("SELECT * FROM student_term_remarks WHERE student_id = $id AND academic_year = '$current_year' AND term = '$current_term'");
    if($rem_res->num_rows > 0) $student_remarks = $rem_res->fetch_assoc();
}

// Get School Branding (Dynamic - No hardcoding)
$school_name    = getSystemSetting($conn, 'school_name', '');
$school_address = getSystemSetting($conn, 'school_address', '');
$school_phone   = getSystemSetting($conn, 'school_phone', '');
$school_email   = getSystemSetting($conn, 'school_email', '');

// Next term dates (Optional dynamic)
$reopening_date = getSystemSetting($conn, 'next_term_reopening', '—');
$vacation_date  = getSystemSetting($conn, 'current_term_vacation', '—');

$v = fn($k) => htmlspecialchars($p[$k] ?? '-');

if (isset($_GET['view']) && $_GET['view'] == 'html') {
    $render_type = 'html';
} else {
    $render_type = 'pdf';
    ob_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transcript - <?= $v('first_name') ?> <?= $v('last_name') ?></title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .print-container { width: 100%; border: none; }
        .school-header { text-align: center; margin-bottom: 20px; }
        .school-name { font-size: 22px; font-weight: 800; color: #1e293b; margin: 5px 0 0; line-height: 1.2; text-transform: uppercase; }
        .school-info { font-size: 11px; color: #475569; margin: 3px 0 0; font-weight: normal; }
        .doc-title { text-align: center; font-weight: bold; font-size: 14px; text-decoration: underline; margin-bottom: 15px; text-transform: uppercase; }
        
        .box-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 15px; }
        .box-table td { border: 1px solid #000; padding: 6px 8px; vertical-align: middle; }
        .label { font-weight: bold; font-size: 10px; text-transform: uppercase; margin-right: 5px; color: #333; }
        .content { font-weight: bold; font-size: 12px; }

        .grade-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 15px; }
        .grade-table th { border: 1px solid #000; padding: 8px; background-color: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 10px; text-align: center; }
        .grade-table td { border: 1px solid #000; padding: 8px; vertical-align: middle; text-align: center; font-size: 11px; }
        .subject-name { text-align: left !important; font-weight: bold; text-transform: uppercase; }

        .remarks-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
        .remarks-table td { border: 1px solid #000; padding: 8px; vertical-align: top; }
        .remark-label { font-weight: bold; font-size: 10px; text-transform: uppercase; background-color: #f8fafc; width: 25%; }
        .remark-content { font-weight: bold; font-style: italic; color: #1e293b; font-size: 12px; }

        .footer { margin-top: 30px; }
        .sig-box { width: 45%; border-top: 1px solid #000; text-align: center; padding-top: 5px; font-weight: bold; font-size: 11px; margin-top: 40px; }
    </style>
</head>
<body style="<?= ($render_type == 'html') ? 'background-color: #f1f5f9; padding-bottom: 60px;' : '' ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if ($render_type == 'html'): ?>
        <div class="web-only-nav" style="background: #fff; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-weight: bold; color: #1e293b; font-size: 14px;"><i class="fas fa-certificate text-indigo-500 mr-2"></i> Official Transcript Preview</div>
            <div style="display: flex; gap: 10px;">
                <a href="?student=<?= $id ?>&class=<?= urlencode($selected_class) ?>" class="btn-action" style="background: #ef4444; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </a>
                <button onclick="window.close()" class="btn-action" style="background: #64748b; color: #fff; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 12px;">
                    Close Window
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div class="print-container" style="<?= ($render_type == 'html') ? 'max-width: 900px; margin: 40px auto; padding: 40px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-radius: 12px;' : '' ?>">
        
        <div class="school-header">
            <?php 
                $logo_path = '../../assets/img/salba_logo.jpg';
                if (file_exists($logo_path)): 
            ?>
                <img src="<?= $logo_path ?>" style="width: 110px; height: auto; display: block; margin: 0 auto;">
            <?php endif; ?>
            <div class="school-name"><?= $school_name ?></div>
            <div class="school-info">
                <?= $school_address ?><br>
                Phone: <?= $school_phone ?> | Email: <?= $school_email ?>
            </div>
        </div>

        <div class="doc-title">Terminal Progress Report</div>

        <!-- Student Bio -->
        <table class="box-table">
            <tr>
                <td style="width: 60%;"><span class="label">Name of Student:</span> <span class="content"><?= strtoupper($v('first_name').' '.$v('last_name')) ?></span></td>
                <td style="width: 40%;"><span class="label">Academic Year:</span> <span class="content"><?= $current_year ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Class:</span> <span class="content"><?= strtoupper($selected_class) ?></span></td>
                <td><span class="label">Term:</span> <span class="content"><?= strtoupper($current_term) ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Position:</span> <span class="content">—</span></td> <!-- Master rank can be calculated later -->
                <td><span class="label">No. on Roll:</span> <span class="content"><?= $class_size ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Vacation Date:</span> <span class="content"><?= $vacation_date ?></span></td>
                <td><span class="label">Re-opening Date:</span> <span class="content"><?= $reopening_date ?></span></td>
            </tr>
        </table>

        <!-- Grades -->
        <table class="grade-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Subject / Learning Area</th>
                    <th style="width: 12%;">OA (<?= $global_oa_weight ?>%)</th>
                    <th style="width: 12%;">Exam (<?= $global_exam_weight ?>%)</th>
                    <th style="width: 15%;">Total (100%)</th>
                    <th style="width: 10%;">Grade</th>
                    <th style="width: 10%;">Pos.</th>
                    <th style="width: 11%;">Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transcript_lines)): ?>
                    <?php foreach ($transcript_lines as $l): ?>
                        <tr>
                            <td class="subject-name"><?= $l['subject'] ?></td>
                            <td><?= $l['oa'] ?></td>
                            <td><?= $l['ex'] ?></td>
                            <td style="font-weight: 800; font-size: 13px;"><?= $l['total'] ?></td>
                            <td style="font-weight: bold;"><?= $l['grade'] ?></td>
                            <td><?= $l['pos'] ?></td>
                            <td style="font-size: 9px; text-transform: uppercase;"><?= $l['remark'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="padding: 20px; font-style: italic;">No academic records found for this term.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pastoral Care & Remarks -->
        <table class="remarks-table">
            <tr>
                <td class="remark-label">Attitude</td>
                <td class="remark-content"><?= htmlspecialchars($student_remarks['attitude'] ?? '—') ?></td>
                <td class="remark-label">Conduct</td>
                <td class="remark-content"><?= htmlspecialchars($student_remarks['conduct'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="remark-label">Talent & Interest</td>
                <td colspan="3" class="remark-content"><?= htmlspecialchars($student_remarks['talent_and_interest'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="remark-label" style="padding: 15px 8px;">Class Teacher's Remarks</td>
                <td colspan="3" class="remark-content" style="padding: 15px 8px; font-size: 14px;"><?= htmlspecialchars($student_remarks['teacher_remarks'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="remark-label" style="padding: 15px 8px;">Headmaster's Remarks</td>
                <td colspan="3" class="remark-content" style="padding: 15px 8px; font-size: 14px; border-bottom: none;"><?= htmlspecialchars($student_remarks['supervisor_remarks'] ?? '—') ?></td>
            </tr>
        </table>

        <div style="display: flex; justify-content: space-between;">
            <div class="sig-box">
                <div style="font-weight: normal; margin-bottom: 5px;"><?= strtoupper($class_teacher_name) ?></div>
                Class Teacher's Signature
            </div>
            <div class="sig-box">
                <div style="font-weight: normal; margin-bottom: 5px;"><?= strtoupper(getSystemSetting($conn, 'head_teacher_name', '__________________________')) ?></div>
                Headmaster's Signature
            </div>
        </div>

    </div>

</body>
</html>
<?php
if ($render_type == 'pdf') {
    $html = ob_get_clean();
    $mpdf = new \Mpdf\Mpdf([
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
    ]);
    $mpdf->WriteHTML($html);
    $filename = "Transcript_" . str_replace(' ', '_', $p['first_name'] . "_" . $p['last_name']) . ".pdf";
    $mpdf->Output($filename, 'D');
}
?>
