<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) { header('Location: ' . BASE_URL . 'index'); exit; }

$current_term     = getCurrentSemester($conn);
$current_year     = getAcademicYear($conn);
$selected_term    = isset($_GET['semester'])  ? trim($_GET['semester'])  : $current_term;
$selected_year    = isset($_GET['year'])      ? trim($_GET['year'])      : $current_year;
$selected_cat     = isset($_GET['category'])  ? intval($_GET['category']) : 0;

$where = []; $params = []; $types = '';
if ($selected_term !== '') { $where[] = 'e.semester = ?'; $params[] = $selected_term; $types .= 's'; }
if ($selected_year !== '') { $where[] = 'e.academic_year = ?'; $params[] = $selected_year; $types .= 's'; }
if ($selected_cat > 0)     { $where[] = 'e.category_id = ?'; $params[] = $selected_cat; $types .= 'i'; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT e.*, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id $where_sql ORDER BY e.expense_date DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$expenses = [];
$total = 0;
while ($row = $res->fetch_assoc()) { $expenses[] = $row; $total += (float)$row['amount']; }

// Category summary
$summary = [];
foreach ($expenses as $e) {
    $cat = $e['category_name'] ?: 'Uncategorised';
    $summary[$cat] = ($summary[$cat] ?? 0) + (float)$e['amount'];
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
$label = ($selected_term ?: 'All') . ' ' . ($selected_year ?: '');

$rows = '';
$i = 1;
foreach ($expenses as $e) {
    $bg = ($i % 2 === 0) ? 'background:#fafafa;' : '';
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $i++ . '</td>
        <td>' . date('d M Y', strtotime($e['expense_date'])) . '</td>
        <td>' . htmlspecialchars($e['category_name'] ?? '—') . '</td>
        <td>' . htmlspecialchars($e['description'] ?? '') . '</td>
        <td class="right bold">' . number_format($e['amount'], 2) . '</td>
    </tr>';
}

$sum_rows = '';
foreach ($summary as $cat => $amt) {
    $sum_rows .= '<tr><td>' . htmlspecialchars($cat) . '</td><td class="right bold">' . number_format($amt, 2) . '</td></tr>';
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
    .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin: 12px 0 6px; }
    table.main { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    table.main thead th { background: #e11d48; color: #fff; padding: 6px 8px; font-size: 9px; text-transform: uppercase; }
    table.main tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    table.main tfoot td { padding: 6px 8px; background: #fff1f2; font-weight: 900; border-top: 2px solid #e11d48; }
    table.summary { width: 50%; border-collapse: collapse; }
    table.summary td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
    table.summary .total-row td { background: #1e293b; color: #fff; font-weight: 900; padding: 6px 8px; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .bold   { font-weight: 700; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 14px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Expenses Report — <?= htmlspecialchars($label) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; Total Records: <?= count($expenses) ?></div>
</div>
<hr class="divider">

<div class="section-title">Expense Details</div>
<table class="main">
    <thead>
        <tr>
            <th class="center" style="width:30px;">#</th>
            <th style="width:70px;">Date</th>
            <th style="width:110px;">Category</th>
            <th>Description</th>
            <th class="right" style="width:85px;">Amount (GHS)</th>
        </tr>
    </thead>
    <tbody><?= $rows ?></tbody>
    <tfoot>
        <tr>
            <td colspan="4" class="right">TOTAL (<?= count($expenses) ?> records)</td>
            <td class="right">GHS <?= number_format($total, 2) ?></td>
        </tr>
    </tfoot>
</table>

<div class="section-title">Summary by Category</div>
<table class="summary">
    <thead><tr style="background:#f8fafc;"><th style="text-align:left; padding:5px 8px; font-size:9px;">Category</th><th style="text-align:right; padding:5px 8px; font-size:9px;">Total (GHS)</th></tr></thead>
    <tbody><?= $sum_rows ?></tbody>
    <tbody><tr class="total-row"><td>GRAND TOTAL</td><td class="right">GHS <?= number_format($total, 2) ?></td></tr></tbody>
</table>

<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Expenses Report &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
$mpdf->WriteHTML($html);
$mpdf->Output('Expenses_' . str_replace('/', '-', $selected_year) . '_' . $selected_term . '.pdf', 'D');
