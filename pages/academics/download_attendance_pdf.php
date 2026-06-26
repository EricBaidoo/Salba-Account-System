<?php
require_once '../../vendor/autoload.php';
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';

$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$selected_class = $conn->real_escape_string($_GET['class'] ?? '');
$selected_date  = $conn->real_escape_string($_GET['date']  ?? date('Y-m-d'));

if (!$selected_class) die('Class required.');

// Fetch students in class with today's attendance
$students = [];
$res = $conn->prepare("
    SELECT s.id, s.first_name, s.last_name,
           a.status as att_status, a.remarks
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = ?
    WHERE s.class = ? AND s.status = 'active'
    ORDER BY s.last_name, s.first_name
");
$res->bind_param('ss', $selected_date, $selected_class);
$res->execute();
$r = $res->get_result();
while ($row = $r->fetch_assoc()) $students[] = $row;

$present = count(array_filter($students, fn($s) => in_array($s['att_status'] ?? '', ['present', 'late'])));
$absent  = count(array_filter($students, fn($s) => $s['att_status'] === 'absent'));
$total   = count($students);

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

$rows = '';
$i = 1;
foreach ($students as $s) {
    $status = $s['att_status'] ?? 'Not Taken';
    $color  = $status === 'present' ? '#059669' : ($status === 'late' ? '#d97706' : ($status === 'absent' ? '#e11d48' : '#94a3b8'));
    $bg     = ($i % 2 === 0) ? 'background:#f8fafc;' : '';
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) . '</td>
        <td class="center" style="color:' . $color . ';font-weight:700;">' . ucfirst($status) . '</td>
        <td>' . htmlspecialchars($s['remarks'] ?? '') . '</td>
    </tr>';
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
    .header { text-align: center; margin-bottom: 12px; }
    .school-name { font-size: 14px; font-weight: 900; text-transform: uppercase; }
    .report-title { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
    .stats { display: table; width: 100%; margin-bottom: 14px; }
    .stat { display: table-cell; text-align: center; padding: 8px; border: 1px solid #e2e8f0; }
    .stat-val { font-size: 18px; font-weight: 900; }
    .stat-lbl { font-size: 8px; color: #64748b; text-transform: uppercase; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 8px; font-size: 9px; text-transform: uppercase; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    .center { text-align: center; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 14px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Attendance Register — <?= htmlspecialchars($selected_class) ?></div>
    <div class="sub">Date: <?= date('l, d M Y', strtotime($selected_date)) ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($current_term) ?> <?= htmlspecialchars($current_year) ?></div>
</div>
<hr class="divider">

<div class="stats">
    <div class="stat"><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total</div></div>
    <div class="stat" style="color:#059669;"><div class="stat-val"><?= $present ?></div><div class="stat-lbl">Present</div></div>
    <div class="stat" style="color:#e11d48;"><div class="stat-val"><?= $absent ?></div><div class="stat-lbl">Absent</div></div>
    <div class="stat" style="color:#d97706;"><div class="stat-val"><?= $total - $present - $absent ?></div><div class="stat-lbl">Late/Other</div></div>
</div>

<table>
    <thead>
        <tr>
            <th class="center" style="width:35px;">#</th>
            <th>Student Name</th>
            <th class="center" style="width:80px;">Status</th>
            <th>Remarks</th>
        </tr>
    </thead>
    <tbody><?= $rows ?></tbody>
</table>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Attendance Register &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14]);
$mpdf->WriteHTML($html);
$mpdf->Output('Attendance_' . $selected_class . '_' . $selected_date . '.pdf', 'D');
