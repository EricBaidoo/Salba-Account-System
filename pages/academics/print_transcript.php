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

// Security Guard: Only admins can generate official PDFs. 
// Staff can only use the HTML viewer.
$render_type = (isset($_GET['view']) && $_GET['view'] == 'html') ? 'html' : 'pdf';
if ($render_type === 'pdf' && ($_SESSION['role'] ?? '') !== 'admin') {
    die("Institutional Security: PDF generation is restricted to the Administrative role. Please use the Digital Preview only.");
}

$is_bulk = isset($_GET['bulk']) && $_GET['bulk'] == 1;
$id = intval($_GET['student'] ?? ($_GET['id'] ?? 0));
$selected_class = $conn->real_escape_string($_GET['class'] ?? '');

if (!$is_bulk && !$id) die("Invalid Student ID.");
if ($is_bulk && !$selected_class) die("Invalid Class for bulk printing.");

$current_year = getAcademicYear($conn);
$current_term = getCurrentSemester($conn);

$reopening_raw = getSystemSetting($conn, 'next_semester_begins', '');
$reopening_date = $reopening_raw ? date('jS F Y', strtotime($reopening_raw)) : '—';

$vacation_raw = getSystemSetting($conn, 'semester_end_date', '');
$vacation_date = $vacation_raw ? date('jS F Y', strtotime($vacation_raw)) : '—';

$total_instructional_days = getInstructionalDaysCount($conn, $current_term, $current_year);

function ordinalSuffix($num) {
    if (!is_numeric($num)) return $num;
    if ($num % 100 >= 11 && $num % 100 <= 13) return $num . 'th';
    switch ($num % 10) {
        case 1: return $num . 'st';
        case 2: return $num . 'nd';
        case 3: return $num . 'rd';
        default: return $num . 'th';
    }
}

// Fetch Student Data
$target_students = [];
if ($is_bulk) {
    $res = $conn->query("SELECT * FROM students WHERE class = '$selected_class' AND status='active'");
    while($row = $res->fetch_assoc()) $target_students[] = $row;
    if (empty($target_students)) die("No active students found in this class.");
} else {
    $p = $conn->query("SELECT * FROM students WHERE id = $id")->fetch_assoc();
    if (!$p) die("Student not found.");
    $target_students[] = $p;
}

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
$student_transcripts = []; // $student_transcripts[student_id] = [...]
$all_remarks = [];

if ($selected_class) {
    $class_scores = []; 
    
    // Map Configs to Array for robust matching
    $oa_types = []; $exam_types = [];
    $total_max_oa = 0; $total_max_ex = 0;
    $type_res = $conn->query("SELECT assessment_name, is_exam, max_marks_allocation FROM assessment_configurations WHERE academic_year = '$current_year' AND semester = '$current_term'");
    while($r = $type_res->fetch_assoc()) {
        if ($r['is_exam']) {
            $exam_types[] = $r['assessment_name'];
            $total_max_ex += floatval($r['max_marks_allocation']);
        } else {
            $oa_types[] = $r['assessment_name'];
            $total_max_oa += floatval($r['max_marks_allocation']);
        }
    }

    // Valid subjects filter
    $valid_subjects = [];
    $v_stmt = $conn->prepare("
        SELECT DISTINCT s.name 
        FROM subjects s
        LEFT JOIN class_subjects cs ON s.id = cs.subject_id AND cs.class_name = ?
        LEFT JOIN teacher_allocations ta ON s.id = ta.subject_id AND ta.class_name = ? AND ta.year = ?
        WHERE cs.subject_id IS NOT NULL OR ta.subject_id IS NOT NULL
    ");
    $v_stmt->bind_param('sss', $selected_class, $selected_class, $current_year);
    $v_stmt->execute();
    $v_res = $v_stmt->get_result();
    while ($r = $v_res->fetch_assoc()) {
        $valid_subjects[] = $r['name'];
    }
    $v_stmt->close();

    // Fetch all raw scaled grades for active students in the class
    $g_res = $conn->query("
        SELECT g.student_id, g.subject, g.marks, g.assessment_type 
        FROM grades g
        JOIN students s ON g.student_id = s.id
        WHERE g.class_name = '$selected_class' 
        AND g.semester = '$current_term' 
        AND g.year = '$current_year'
        AND s.class = '$selected_class'
        AND s.status = 'active'
    ");
    
    while($row = $g_res->fetch_assoc()) {
        $sub = $row['subject'];
        if (!in_array($sub, $valid_subjects)) continue;

        $sid = $row['student_id'];
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
    
    // Calculate overall class positions
    $student_overall_totals = [];
    foreach ($class_scores as $sub => $scores_array) {
        foreach ($scores_array as $st_id => $st_data) {
            $final_oa = ($total_max_oa > 0) ? ($st_data['oa_raw'] / $total_max_oa) * $global_oa_weight : 0;
            $final_ex = ($total_max_ex > 0) ? ($st_data['ex_raw'] / $total_max_ex) * $global_exam_weight : 0;
            if (!isset($student_overall_totals[$st_id])) $student_overall_totals[$st_id] = 0;
            $student_overall_totals[$st_id] += ($final_oa + $final_ex);
        }
    }
    
    $ranked_overall_totals = array_values($student_overall_totals);
    rsort($ranked_overall_totals);
    $student_overall_positions = [];
    foreach ($student_overall_totals as $st_id => $total) {
        $student_overall_positions[$st_id] = array_search($total, $ranked_overall_totals) + 1;
    }
    
    // Calculate positions per subject
    foreach ($class_scores as $sub => $scores_array) {
        $all_totals = [];
        foreach ($scores_array as $st_id => $st_data) {
            $final_oa = ($total_max_oa > 0) ? ($st_data['oa_raw'] / $total_max_oa) * $global_oa_weight : 0;
            $final_ex = ($total_max_ex > 0) ? ($st_data['ex_raw'] / $total_max_ex) * $global_exam_weight : 0;
            $all_totals[$st_id] = $final_oa + $final_ex;
        }
        
        $ranked_scores = array_values($all_totals);
        rsort($ranked_scores);
        
        foreach ($scores_array as $st_id => $st_data) {
            $my_total = $all_totals[$st_id];
            $pos = array_search($my_total, $ranked_scores) + 1;
            
            $final_oa = ($total_max_oa > 0) ? ($st_data['oa_raw'] / $total_max_oa) * $global_oa_weight : 0;
            $final_ex = ($total_max_ex > 0) ? ($st_data['ex_raw'] / $total_max_ex) * $global_exam_weight : 0;
            
            $rounded_total = round($my_total, 1);
            $grade = ''; $remark = '';
            if ($rounded_total >= 80)      { $grade = '1'; $remark = 'Excellent'; }
            elseif ($rounded_total >= 70)  { $grade = '2'; $remark = 'Very Good'; }
            elseif ($rounded_total >= 65)  { $grade = '3'; $remark = 'Good'; }
            elseif ($rounded_total >= 60)  { $grade = '4'; $remark = 'Credit'; }
            elseif ($rounded_total >= 55)  { $grade = '5'; $remark = 'Credit'; }
            elseif ($rounded_total >= 50)  { $grade = '6'; $remark = 'Credit'; }
            elseif ($rounded_total >= 45)  { $grade = '7'; $remark = 'Pass'; }
            elseif ($rounded_total >= 40)  { $grade = '8'; $remark = 'Pass'; }
            else                           { $grade = '9'; $remark = 'Fail'; }
            
            if (!isset($student_transcripts[$st_id])) $student_transcripts[$st_id] = [];
            $student_transcripts[$st_id][] = [
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
    
    // Fetch Remarks for the whole class
    $rem_res = $conn->query("SELECT * FROM student_term_remarks WHERE academic_year = '$current_year' AND semester = '$current_term'");
    while($r = $rem_res->fetch_assoc()) {
        $all_remarks[$r['student_id']] = $r;
    }

    // Fetch Attendance for the whole class
    $all_attendance = [];
    $att_res = $conn->query("
        SELECT student_id, COUNT(*) as days_present 
        FROM attendance 
        WHERE academic_year = '$current_year' 
        AND semester = '$current_term' 
        AND status = 'present' 
        GROUP BY student_id
    ");
    if ($att_res) {
        while($r = $att_res->fetch_assoc()) {
            $all_attendance[$r['student_id']] = $r['days_present'];
        }
    }
}

// Get School Branding (Dynamic - No hardcoding)
$school_name    = getSystemSetting($conn, 'school_name', '');
$school_address = getSystemSetting($conn, 'school_address', '');
$school_phone   = getSystemSetting($conn, 'school_phone', '');
$school_email   = getSystemSetting($conn, 'school_email', '');
$school_circuit = getSystemSetting($conn, 'school_circuit', '');
$school_district= getSystemSetting($conn, 'school_district', '');
$school_region  = getSystemSetting($conn, 'school_region', '');

// Next semester dates (Dynamic from System Settings)
$raw_reopening = getSystemSetting($conn, 'next_semester_begins', '');
$raw_vacation   = getSystemSetting($conn, 'semester_end_date', '');

$reopening_date = $raw_reopening ? date('jS F Y', strtotime($raw_reopening)) : '—';
$vacation_date  = $raw_vacation  ? date('jS F Y', strtotime($raw_vacation))  : '—';

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
    <title><?= $is_bulk ? 'Class Transcripts - ' . htmlspecialchars($selected_class) : 'Transcript - ' . htmlspecialchars($target_students[0]['first_name'] . ' ' . $target_students[0]['last_name']) ?></title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; line-height: 1.3; color: #000; margin: 0; padding: 0; }
        .print-container { width: 100%; border: none; }
        .school-header { text-align: center; margin-bottom: 10px; }
        .school-name { font-size: 18px; font-weight: 800; color: #1e293b; margin: 3px 0 0; line-height: 1.1; text-transform: uppercase; }
        .school-info { font-size: 10px; color: #475569; margin: 2px 0 0; font-weight: normal; line-height: 1.2; }
        .doc-title { text-align: center; font-weight: bold; font-size: 12px; text-decoration: underline; margin-bottom: 8px; text-transform: uppercase; }
        
        .box-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 10px; }
        .box-table td { border: 1px solid #000; padding: 4px 6px; vertical-align: middle; }
        .label { font-weight: bold; font-size: 9px; text-transform: uppercase; margin-right: 5px; color: #333; }
        .content { font-weight: bold; font-size: 11px; }

        .grade-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; margin-bottom: 10px; }
        .grade-table th { border: 1px solid #000; padding: 4px; background-color: #f1f5f9; font-weight: bold; text-transform: uppercase; font-size: 9px; text-align: center; }
        .grade-table td { border: 1px solid #000; padding: 4px; vertical-align: middle; text-align: center; font-size: 10px; }
        .subject-name { text-align: left !important; font-weight: bold; text-transform: uppercase; font-size: 9px; }

        .remarks-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
        .remarks-table td { border: 1px solid #000; padding: 4px; vertical-align: top; }
        .remark-label { font-weight: bold; font-size: 9px; text-transform: uppercase; background-color: #f8fafc; width: 25%; }
        .remark-content { font-weight: bold; font-style: italic; color: #1e293b; font-size: 12px; }

        .footer { margin-top: 30px; }
    </style>
</head>
<body style="<?= ($render_type == 'html') ? 'background-color: #f1f5f9; padding-bottom: 60px;' : '' ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php if ($render_type == 'html'): ?>
        <div class="web-only-nav" style="background: #fff; padding: 10px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-weight: bold; color: #1e293b; font-size: 14px;"><i class="fas fa-certificate text-indigo-500 mr-2"></i> Official Transcript Preview <?= $is_bulk ? '(Bulk Mode)' : '' ?></div>
            <div style="display: flex; gap: 10px;">
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="?<?= $is_bulk ? 'bulk=1&' : 'student='.$id.'&' ?>class=<?= urlencode($selected_class) ?>" class="btn-action" style="background: #ef4444; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                <?php endif; ?>
                <button onclick="window.close()" class="btn-action" style="background: #64748b; color: #fff; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer; font-weight: bold; font-size: 12px;">
                    Close Window
                </button>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($target_students as $index => $p): 
        $id = $p['id'];
        $transcript_lines = $student_transcripts[$id] ?? [];
        $student_remarks = $all_remarks[$id] ?? null;
        $student_att = $all_attendance[$id] ?? 0;
        $v = fn($k) => htmlspecialchars($p[$k] ?? '-');
    ?>

    <div class="print-container" style="<?= ($render_type == 'html') ? 'max-width: 900px; margin: 40px auto; padding: 40px; background: white; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-radius: 12px;' : '' ?>">
        
        <div class="school-header">
            <?php 
                $logo_path = '../../assets/img/salba_logo.jpg';
                if (file_exists($logo_path)): 
            ?>
                <img src="<?= $logo_path ?>" style="width: 80px; height: auto; display: block; margin: 0 auto;">
            <?php endif; ?>
            <div class="school-name"><?= $school_name ?></div>
            <div class="school-info">
                <?= $school_address ?><br>
                Phone: <?= $school_phone ?> | Email: <?= $school_email ?>
                <?php if($school_circuit || $school_district || $school_region): ?>
                    <br>
                    <?php 
                        $locs = [];
                        if($school_circuit) $locs[] = "Circuit: " . htmlspecialchars($school_circuit);
                        if($school_district) $locs[] = "District: " . htmlspecialchars($school_district);
                        if($school_region) $locs[] = "Region: " . htmlspecialchars($school_region);
                        echo implode(" | ", $locs);
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="doc-title">END OF <?= strtoupper($current_term) ?> REPORT</div>

        <!-- Student Bio -->
        <table class="box-table">
            <tr>
                <td style="width: 60%;"><span class="label">Name of Student:</span> <span class="content"><?= strtoupper($v('first_name').' '.$v('last_name')) ?></span></td>
                <td style="width: 40%;"><span class="label">Academic Year:</span> <span class="content"><?= $current_year ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Class:</span> <span class="content"><?= strtoupper($selected_class) ?></span></td>
                <td><span class="label">Semester:</span> <span class="content"><?= strtoupper($current_term) ?></span></td>
            </tr>
            <tr>
                <?php $ov_pos = $student_overall_positions[$id] ?? '—'; ?>
                <td><span class="label">Position:</span> <span class="content"><?= ordinalSuffix($ov_pos) ?></span></td>
                <td><span class="label">No. on Roll:</span> <span class="content"><?= $class_size ?></span></td>
            </tr>
            <tr>
                <td><span class="label">Vacation Date:</span> <span class="content"><?= $vacation_date ?></span></td>
                <td><span class="label">Next Semester Begins:</span> <span class="content"><?= $reopening_date ?></span></td>
            </tr>
        </table>

        <!-- Grades -->
        <table class="grade-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Subject</th>
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
                    <tr><td colspan="7" style="padding: 20px; font-style: italic;">No academic records found for this semester.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pastoral Care & Remarks -->
        <table class="remarks-table">
            <tr>
                <td class="remark-label">Attendance</td>
                <td colspan="3" class="remark-content"><?= $student_att ?> out of a total of <?= $total_instructional_days ?></td>
            </tr>
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
                <td class="remark-label" style="padding: 6px 4px;">Class Teacher's Remarks</td>
                <td colspan="3" class="remark-content" style="padding: 6px 4px; font-size: 12px;"><?= htmlspecialchars($student_remarks['teacher_remarks'] ?? '—') ?></td>
            </tr>
            <tr>
                <td class="remark-label" style="padding: 6px 4px;">Principal's / Headteacher's / Supervisor's Remarks</td>
                <td colspan="3" class="remark-content" style="padding: 6px 4px; font-size: 12px; border-bottom: none;"><?= htmlspecialchars($student_remarks['supervisor_remarks'] ?? '—') ?></td>
            </tr>
        </table>

        <table style="width: 100%; margin-top: 25px; border: none;">
            <tr>
                <td style="width: 40%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="height: 60px;"></div>
                    <div style="border-top: 1px solid #000; padding-top: 8px; font-weight: bold; font-size: 10px; text-transform: uppercase; color: #1e293b;">
                        <?= htmlspecialchars(getSystemSetting($conn, 'transcript_left_signature_label', "Class Teacher's Signature")) ?>
                    </div>
                </td>
                <td style="width: 20%; border: none;"></td> <!-- Spacer -->
                <td style="width: 40%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="height: 60px;">
                        <?php $p_sig = getSystemSetting($conn, 'principal_signature', ''); ?>
                        <?php if ($p_sig): ?>
                            <img src="../../<?= htmlspecialchars($p_sig) ?>" style="max-height: 80px; max-width: 180px; margin-bottom: -5px;">
                        <?php endif; ?>
                    </div>
                    <div style="border-top: 1px solid #000; padding-top: 8px; font-weight: bold; font-size: 10px; text-transform: uppercase; color: #1e293b;">
                        <?= htmlspecialchars(getSystemSetting($conn, 'transcript_right_signature_label', "Principal's / Headteacher's / Supervisor's Signature")) ?>
                    </div>
                </td>
            </tr>
        </table>

    </div>
    
    <?php if ($index < count($target_students) - 1): ?>
        <?php if ($render_type == 'pdf'): ?>
            <pagebreak />
        <?php else: ?>
            <div style="page-break-after: always; height: 1px; margin-bottom: 40px;"></div>
        <?php endif; ?>
    <?php endif; ?>
    <?php endforeach; ?>

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
    
    if ($is_bulk) {
        $filename = "Bulk_Transcripts_" . str_replace(' ', '_', $selected_class) . ".pdf";
    } else {
        $filename = "Transcript_" . str_replace(' ', '_', $target_students[0]['first_name'] . "_" . $target_students[0]['last_name']) . ".pdf";
    }
    
    $mpdf->Output($filename, 'D');
}
?>
