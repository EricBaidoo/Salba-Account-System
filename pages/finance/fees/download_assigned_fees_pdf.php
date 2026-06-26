<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) { header('Location: ' . BASE_URL . 'index'); exit; }

$class_filter  = $_GET['class']  ?? '';
$status_filter = $_GET['status'] ?? '';
$year_filter   = $_GET['year']   ?? '';

$where = []; $params = []; $types = '';
if ($class_filter  !== '') { $where[] = 'v.class = ?';           $params[] = $class_filter;  $types .= 's'; }
if ($status_filter !== '') { $where[] = 'v.payment_status = ?';  $params[] = $status_filter; $types .= 's'; }
if ($year_filter   !== '') { $where[] = 'sf.academic_year = ?';  $params[] = $year_filter;   $types .= 's'; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT v.*, sf.academic_year FROM v_fee_assignments v JOIN student_fees sf ON sf.id = v.assignment_id $where_sql ORDER BY v.due_date DESC, v.student_name";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$records = [];
$total = 0;
while ($r = $res->fetch_assoc()) { $records[] = $r; $total += (float)$r['amount']; }

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
$label_parts = array_filter([$class_filter, $status_filter, $year_filter]);
$label = $label_parts ? implode(' · ', $label_parts) : 'All Assignments';

$rows = '';
$i = 1;
foreach ($records as $r) {
    $bg = ($i % 2 === 0) ? 'background:#f8fafc;' : '';
    $sc = $r['payment_status'] === 'paid' ? '#059669' : ($r['payment_status'] === 'overdue' ? '#e11d48' : '#d97706');
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . htmlspecialchars($r['student_name']) . '</td>
        <td>' . htmlspecialchars($r['class'] ?? '—') . '</td>
        <td>' . htmlspecialchars($r['fee_name']) . '</td>
        <td>' . htmlspecialchars($r['semester'] ?? '—') . '</td>
        <td>' . ($r['due_date'] ? date('d M Y', strtotime($r['due_date'])) : '—') . '</td>
        <td class="right bold">' . number_format($r['amount'], 2) . '</td>
        <td class="center" style="color:' . $sc . ';font-weight:700;">' . ucfirst($r['payment_status'] ?? '—') . '</td>
    </tr>';
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; margin: 0; }
    .header { text-align: center; margin-bottom: 12px; }
    .school-name { font-size: 14px; font-weight: 900; text-transform: uppercase; }
    .report-title { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 7px; font-size: 8px; text-transform: uppercase; }
    tbody td { padding: 4px 7px; border-bottom: 1px solid #f1f5f9; }
    tfoot td { padding: 6px 7px; background: #f8fafc; font-weight: 900; border-top: 2px solid #1e293b; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .bold   { font-weight: 700; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 14px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Fee Assignments — <?= htmlspecialchars($label) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; <?= count($records) ?> records</div>
</div>
<hr class="divider">
<table>
    <thead>
        <tr>
            <th class="center" style="width:28px;">#</th>
            <th>Student</th>
            <th style="width:55px;">Class</th>
            <th>Fee</th>
            <th style="width:80px;">Semester</th>
            <th style="width:65px;">Due Date</th>
            <th class="right" style="width:75px;">Amount</th>
            <th class="center" style="width:60px;">Status</th>
        </tr>
    </thead>
    <tbody><?= $rows ?: '<tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:10px;">No records</td></tr>' ?></tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="right">TOTAL (<?= count($records) ?> assignments)</td>
            <td class="right">GHS <?= number_format($total, 2) ?></td>
            <td></td>
        </tr>
    </tfoot>
</table>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Fee Assignments &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
$mpdf->WriteHTML($html);
$mpdf->Output('Fee_Assignments_' . date('Y-m-d') . '.pdf', 'D');
