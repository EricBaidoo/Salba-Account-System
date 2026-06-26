<?php
require_once '../../vendor/autoload.php';
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';

if ($_SESSION['role'] !== 'admin') { header('Location: ' . BASE_URL . 'index'); exit; }

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) die('User ID required.');

$staff = $conn->query("SELECT u.username, u.role, sp.* FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $user_id LIMIT 1")->fetch_assoc();
if (!$staff) die('Staff not found.');

$logs = $conn->query("SELECT * FROM staff_attendance WHERE user_id = $user_id ORDER BY check_in_time DESC LIMIT 100");
$records = [];
while ($r = $logs->fetch_assoc()) $records[] = $r;

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

$rows = '';
$i = 1;
foreach ($records as $rec) {
    $bg = ($i % 2 === 0) ? 'background:#f8fafc;' : '';
    $status_color = $rec['punctuality_status'] === 'Early' ? '#059669' : ($rec['punctuality_status'] === 'On Time' ? '#2563eb' : '#e11d48');
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . date('d M Y', strtotime($rec['check_in_time'])) . '</td>
        <td>' . date('H:i', strtotime($rec['check_in_time'])) . '</td>
        <td>' . ($rec['check_out_time'] ? date('H:i', strtotime($rec['check_out_time'])) : '—') . '</td>
        <td class="center" style="color:' . $status_color . ';font-weight:700;">' . htmlspecialchars($rec['punctuality_status'] ?? '—') . '</td>
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
    .info-row { display: table; width: 100%; margin-bottom: 12px; background: #f8fafc; padding: 10px 14px; }
    .info-cell { display: table-cell; width: 33%; }
    .lbl { font-size: 8px; color: #64748b; text-transform: uppercase; }
    .val { font-size: 11px; font-weight: 700; }
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
    <div class="report-title">Staff Attendance History — <?= htmlspecialchars($staff['full_name'] ?? $staff['username']) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; Showing last <?= count($records) ?> records</div>
</div>
<hr class="divider">
<div class="info-row">
    <div class="info-cell"><div class="lbl">Staff Name</div><div class="val"><?= htmlspecialchars($staff['full_name'] ?? $staff['username']) ?></div></div>
    <div class="info-cell"><div class="lbl">Department</div><div class="val"><?= htmlspecialchars($staff['department'] ?? '—') ?></div></div>
    <div class="info-cell"><div class="lbl">Role</div><div class="val"><?= htmlspecialchars($staff['job_title'] ?? $staff['role']) ?></div></div>
</div>

<table>
    <thead>
        <tr>
            <th class="center" style="width:30px;">#</th>
            <th>Date</th>
            <th style="width:65px;">Check In</th>
            <th style="width:65px;">Check Out</th>
            <th class="center" style="width:80px;">Status</th>
        </tr>
    </thead>
    <tbody><?= $rows ?: '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:10px;">No records found</td></tr>' ?></tbody>
</table>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Staff History &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14]);
$mpdf->WriteHTML($html);
$name = preg_replace('/[^A-Za-z0-9]/', '_', $staff['full_name'] ?? 'Staff');
$mpdf->Output('Staff_History_' . $name . '.pdf', 'D');
