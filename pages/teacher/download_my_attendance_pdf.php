<?php
require_once '../../vendor/autoload.php';
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';

$uid = $_SESSION['user_id'];
$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);

// Fetch staff name
$staff_res = $conn->query("SELECT COALESCE(sp.full_name, u.username) as name FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $uid LIMIT 1");
$staff_name = $staff_res->fetch_assoc()['name'] ?? 'Staff';

$logs = $conn->query("SELECT * FROM staff_attendance WHERE user_id = $uid ORDER BY check_in_time DESC LIMIT 100");
$records = [];
while ($r = $logs->fetch_assoc()) $records[] = $r;

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

$rows = '';
$i = 1;
foreach ($records as $rec) {
    $bg = ($i % 2 === 0) ? 'background:#f8fafc;' : '';
    $sc = $rec['punctuality_status'] === 'Early' ? '#059669' : ($rec['punctuality_status'] === 'On Time' ? '#2563eb' : '#e11d48');
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . date('d M Y', strtotime($rec['check_in_time'])) . '</td>
        <td>' . date('H:i', strtotime($rec['check_in_time'])) . '</td>
        <td>' . ($rec['check_out_time'] ? date('H:i', strtotime($rec['check_out_time'])) : '—') . '</td>
        <td class="center" style="color:' . $sc . ';font-weight:700;">' . htmlspecialchars($rec['punctuality_status'] ?? '—') . '</td>
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
    .report-title { font-size: 11px; font-weight: 700; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
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
    <div class="report-title">My Attendance History — <?= htmlspecialchars($staff_name) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($current_term) ?> <?= htmlspecialchars($current_year) ?></div>
</div>
<hr class="divider">
<table>
    <thead>
        <tr>
            <th class="center" style="width:30px;">#</th>
            <th>Date</th>
            <th style="width:70px;">Check In</th>
            <th style="width:70px;">Check Out</th>
            <th class="center" style="width:85px;">Status</th>
        </tr>
    </thead>
    <tbody><?= $rows ?: '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:10px;">No records found</td></tr>' ?></tbody>
</table>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Attendance History &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14]);
$mpdf->WriteHTML($html);
$mpdf->Output('My_Attendance_' . preg_replace('/[^A-Za-z0-9]/', '_', $staff_name) . '.pdf', 'D');
