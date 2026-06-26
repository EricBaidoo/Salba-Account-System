<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) { header('Location: ' . BASE_URL . 'index'); exit; }

$current_term = getCurrentSemester($conn);
$current_year = getAcademicYear($conn);
$selected_term = isset($_GET['semester']) ? trim($_GET['semester']) : $current_term;
$selected_year = isset($_GET['year'])     ? trim($_GET['year'])     : $current_year;

$where = []; $params = []; $types = '';
if ($selected_term !== '') { $where[] = 'p.semester = ?'; $params[] = $selected_term; $types .= 's'; }
if ($selected_year !== '') { $where[] = 'p.academic_year = ?'; $params[] = $selected_year; $types .= 's'; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT p.id, s.first_name, s.last_name, s.class, p.amount, p.payment_date, p.receipt_no, p.description, p.semester, p.academic_year, p.payment_type
        FROM payments p LEFT JOIN students s ON p.student_id = s.id $where_sql ORDER BY p.payment_date DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$payments = [];
$total = 0;
while ($row = $res->fetch_assoc()) { $payments[] = $row; $total += (float)$row['amount']; }

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
$label = ($selected_term ?: 'All') . ' ' . ($selected_year ?: '');

$rows = '';
$i = 1;
foreach ($payments as $p) {
    $bg = ($i % 2 === 0) ? 'background:#f8fafc;' : '';
    $name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . htmlspecialchars($name ?: 'General') . '</td>
        <td>' . htmlspecialchars($p['class'] ?? '—') . '</td>
        <td>' . htmlspecialchars($p['receipt_no']) . '</td>
        <td>' . date('d M Y', strtotime($p['payment_date'])) . '</td>
        <td class="right bold">' . number_format($p['amount'], 2) . '</td>
    </tr>';
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; margin: 0; }
    .header { text-align: center; margin-bottom: 14px; }
    .school-name { font-size: 14px; font-weight: 900; text-transform: uppercase; }
    .report-title { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #1e293b; color: #fff; padding: 6px 8px; font-size: 9px; text-transform: uppercase; }
    tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    tfoot td { padding: 6px 8px; background: #f0fdf4; font-weight: 900; border-top: 2px solid #059669; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .bold   { font-weight: 700; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 14px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Payments Ledger — <?= htmlspecialchars($label) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; Total Records: <?= count($payments) ?></div>
</div>
<hr class="divider">
<table>
    <thead>
        <tr>
            <th class="center" style="width:30px;">#</th>
            <th>Student Name</th>
            <th style="width:70px;">Class</th>
            <th style="width:80px;">Receipt No.</th>
            <th style="width:75px;">Date</th>
            <th class="right" style="width:80px;">Amount (GHS)</th>
        </tr>
    </thead>
    <tbody><?= $rows ?></tbody>
    <tfoot>
        <tr>
            <td colspan="5" class="right">TOTAL (<?= count($payments) ?> records)</td>
            <td class="right">GHS <?= number_format($total, 2) ?></td>
        </tr>
    </tfoot>
</table>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Payments Ledger &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
$mpdf->WriteHTML($html);
$mpdf->Output('Payments_' . str_replace('/', '-', $selected_year) . '_' . $selected_term . '.pdf', 'D');
