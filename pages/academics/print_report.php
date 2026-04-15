<?php
session_start();
require_once '../../vendor/autoload.php';
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php'); exit;
}

$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);

// Get academic statistics
$total_students = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE status='active'");
if($res) $total_students = $res->fetch_assoc()['cnt'];

$total_classes = 0;
$res = $conn->query("SELECT COUNT(DISTINCT class) as cnt FROM students WHERE status='active' AND class IS NOT NULL");
if($res) $total_classes = $res->fetch_assoc()['cnt'];

// Calculate average attendance for the current term!
$avg_attendance = 0;
$att_res = $conn->prepare("
    SELECT AVG(presence_rate) as avg_attendance 
    FROM (
        SELECT (SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100 as presence_rate
        FROM attendance
        WHERE term = ? AND academic_year = ?
        GROUP BY student_id
    ) as attendance_rates
");
if($att_res) {
    $att_res->bind_param("ss", $current_term, $academic_year);
    $att_res->execute();
    $avg_attendance = $att_res->get_result()->fetch_assoc()['avg_attendance'] ?? 0;
    $att_res->close();
}

// Get class-wise performance for current term
$class_performance = $conn->prepare("
    SELECT s.class, 
           COUNT(DISTINCT g.student_id) as students_graded,
           AVG(CASE WHEN g.out_of > 0 THEN (g.marks / g.out_of) * 100 ELSE 0 END) as avg_grade_pct
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE g.term = ? AND g.year = ?
    GROUP BY s.class
    ORDER BY s.class
");
$performance_data = [];
if($class_performance) {
    $class_performance->bind_param("ss", $current_term, $academic_year);
    $class_performance->execute();
    $res = $class_performance->get_result();
    while($row = $res->fetch_assoc()) {
        $performance_data[] = $row;
    }
    $class_performance->close();
}

$school_name    = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 11px; margin: 0; padding: 40px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
        .school-name { font-size: 20px; font-weight: 900; text-transform: uppercase; }
        .report-title { font-size: 14px; font-weight: 900; text-align: center; margin: 30px 0; text-transform: uppercase; letter-spacing: 0.2em; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 40px; }
        .stat-card { border: 1px solid #e2e8f0; padding: 15px; text-align: center; }
        .stat-label { font-size: 8px; font-weight: 900; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .stat-value { font-size: 18px; font-weight: 900; }
        .performance-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .performance-table th, .performance-table td { border: 1px solid #000; padding: 10px; text-align: center; }
        .performance-table th { background: #f8fafc; font-size: 8px; font-weight: 900; text-transform: uppercase; }
        .text-left { text-align: left !important; }
        .footer { margin-top: 50px; text-align: center; font-size: 8px; font-weight: 900; color: #cbd5e1; text-transform: uppercase; letter-spacing: 0.2em; }
    </style>
</head>
<body>
    <div class="header">
        <div class="school-name"><?= $school_name ?></div>
        <div style="font-size: 9px; margin-top: 5px;"><?= $school_address ?></div>
    </div>

    <div class="report-title">Academic Intelligence Summary</div>

    <div style="margin-bottom: 20px;">
        <strong>Cycle:</strong> <?= $current_term ?> &middot; <?= $academic_year ?>
    </div>

    <table width="100%" style="margin-bottom: 30px;">
        <tr>
            <td width="25%">
                <div class="stat-card">
                    <div class="stat-label">Active Census</div>
                    <div class="stat-value"><?= $total_students ?></div>
                </div>
            </td>
            <td width="25%">
                <div class="stat-card">
                    <div class="stat-label">Instructional Nodes</div>
                    <div class="stat-value"><?= $total_classes ?></div>
                </div>
            </td>
            <td width="25%">
                <div class="stat-card">
                    <div class="stat-label">Attendance Precision</div>
                    <div class="stat-value"><?= round($avg_attendance, 1) ?>%</div>
                </div>
            </td>
            <td width="25%">
                <div class="stat-card">
                    <div class="stat-label">Term Cycle</div>
                    <div class="stat-value"><?= $current_term ?></div>
                </div>
            </td>
        </tr>
    </table>

    <div style="font-size: 10px; font-weight: 900; text-transform: uppercase; margin-bottom: 15px; border-left: 4px solid #000; padding-left: 10px;">
        Class-Level Performance Matrix
    </div>

    <table class="performance-table">
        <thead>
            <tr>
                <th class="text-left">Institutional Level (Class)</th>
                <th>Active Graded Units</th>
                <th>Scholastic Mean (%)</th>
                <th>Performance Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($performance_data as $row): 
                $avg = $row['avg_grade_pct'];
                $status = 'OPTIMAL';
                if ($avg < 50) $status = 'CRITICAL INTERVENTION';
                elseif ($avg < 70) $status = 'STABLE';
            ?>
                <tr>
                    <td class="text-left"><?= strtoupper($row['class']) ?></td>
                    <td><?= $row['students_graded'] ?></td>
                    <td style="font-weight: 900;"><?= number_format($avg, 1) ?>%</td>
                    <td style="font-size: 8px;"><?= $status ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Institutional Summary &middot; Automated Analytics Engine
    </div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 15,
]);
$mpdf->WriteHTML($html);
$filename = "Academic_Report_" . date('Y_m_d') . ".pdf";
$mpdf->Output($filename, 'D');
?>
