<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) { header('Location: ' . BASE_URL . 'index'); exit; }

$current_term  = getCurrentSemester($conn);
$current_year  = getAcademicYear($conn);
$selected_term = $_GET['semester']      ?? $current_term;
$selected_year = $_GET['academic_year'] ?? $current_year;
$filter_all    = ($selected_term === 'all' || $selected_term === '');
$display_year  = formatAcademicYearDisplay($conn, $selected_year);
$school_name   = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

if (!$filter_all) {
    $sem_subquery = "(SELECT j.id FROM journal_entries j JOIN student_fees sf ON j.reference_type='StudentBill' AND j.reference_id=sf.id WHERE sf.semester=? AND sf.academic_year=?
                     UNION SELECT j.id FROM journal_entries j JOIN payments p ON j.reference_type='Payment' AND j.reference_id=p.id WHERE p.semester=? AND p.academic_year=?
                     UNION SELECT j.id FROM journal_entries j JOIN expenses e ON j.reference_type='Expense' AND j.reference_id=e.id WHERE e.semester=? AND e.academic_year=?)";
    $id_filter = "AND l.journal_entry_id IN $sem_subquery";
    $sem_params = [$selected_term, $selected_year, $selected_term, $selected_year, $selected_term, $selected_year];
} else {
    $id_filter = '';
    $sem_params = [];
}

$sql = "SELECT a.name, a.type, a.account_code, COALESCE(SUM(l.debit),0) AS dr, COALESCE(SUM(l.credit),0) AS cr FROM accounts a LEFT JOIN journal_lines l ON a.id = l.account_id $id_filter GROUP BY a.id ORDER BY a.account_code";
$stmt = $conn->prepare($sql);
if (!$filter_all) { $stmt->bind_param('ssssss', ...$sem_params); }
$stmt->execute();
$result = $stmt->get_result();

$balances = ['asset' => [], 'liability' => [], 'equity' => [], 'revenue' => [], 'expense' => []];
$totals   = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0];
while ($row = $result->fetch_assoc()) {
    $dr = (float)$row['dr']; $cr = (float)$row['cr'];
    $type = strtolower($row['type']);
    $bal = ($type === 'asset' || $type === 'expense') ? ($dr - $cr) : ($cr - $dr);
    if (abs($bal) < 0.005) continue;
    $balances[$type][] = ['name' => $row['name'], 'code' => $row['account_code'], 'balance' => $bal];
    $totals[$type] += $bal;
}
$net_income   = $totals['revenue'] - $totals['expense'];
$total_equity = $totals['equity'] + $net_income;

function build_section($items, $color) {
    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr><td>' . htmlspecialchars($item['code']) . '</td><td>' . htmlspecialchars($item['name']) . '</td><td class="right">' . number_format($item['balance'], 2) . '</td></tr>';
    }
    return $rows;
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
    .header { text-align: center; margin-bottom: 14px; }
    .school-name { font-size: 15px; font-weight: 900; text-transform: uppercase; }
    .report-title { font-size: 12px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
    .two-col { display: table; width: 100%; }
    .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
    .col-right { display: table-cell; width: 50%; vertical-align: top; padding-left: 4px; }
    .section-heading { font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 10px; margin-bottom: 0; color: #fff; }
    table.acct { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.acct td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; font-size: 9px; }
    table.acct .total-row td { background: #f8fafc; font-weight: 900; border-top: 1px solid #e2e8f0; padding: 6px 8px; }
    .right { text-align: right; }
    .net-box { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 12px 16px; border-radius: 6px; text-align: right; margin-top: 14px; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 16px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Financial Statements — <?= htmlspecialchars($filter_all ? $display_year : "$selected_term $display_year") ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?></div>
</div>
<hr class="divider">

<div class="two-col">
    <!-- LEFT: Balance Sheet -->
    <div class="col">
        <div class="section-heading" style="background:#1e3a5f;">BALANCE SHEET</div>
        <table class="acct">
            <thead><tr style="background:#f1f5f9;"><th style="text-align:left;padding:5px 8px;font-size:9px;">Code</th><th style="text-align:left;padding:5px 8px;font-size:9px;">Account</th><th style="text-align:right;padding:5px 8px;font-size:9px;">GHS</th></tr></thead>
            <tbody>
                <tr><td colspan="3" style="font-weight:700;padding:6px 8px;background:#eff6ff;color:#1d4ed8;">ASSETS</td></tr>
                <?= build_section($balances['asset'], '#1d4ed8') ?>
                <tr class="total-row"><td colspan="2">Total Assets</td><td class="right"><?= number_format($totals['asset'], 2) ?></td></tr>
                <tr><td colspan="3" style="font-weight:700;padding:6px 8px;background:#fff7ed;color:#c2410c;">LIABILITIES</td></tr>
                <?= build_section($balances['liability'], '#c2410c') ?>
                <tr class="total-row"><td colspan="2">Total Liabilities</td><td class="right"><?= number_format($totals['liability'], 2) ?></td></tr>
                <tr><td colspan="3" style="font-weight:700;padding:6px 8px;background:#f0fdf4;color:#059669;">EQUITY</td></tr>
                <?= build_section($balances['equity'], '#059669') ?>
                <tr><td colspan="2" style="padding:5px 8px;color:#64748b;">Retained Earnings</td><td class="right" style="color:<?= $net_income >= 0 ? '#059669' : '#e11d48' ?>;"><?= number_format($net_income, 2) ?></td></tr>
                <tr class="total-row"><td colspan="2">Total Equity</td><td class="right"><?= number_format($total_equity, 2) ?></td></tr>
            </tbody>
        </table>
    </div>
    <!-- RIGHT: Income Statement -->
    <div class="col-right">
        <div class="section-heading" style="background:#065f46;">INCOME STATEMENT</div>
        <table class="acct">
            <thead><tr style="background:#f1f5f9;"><th style="text-align:left;padding:5px 8px;font-size:9px;">Code</th><th style="text-align:left;padding:5px 8px;font-size:9px;">Account</th><th style="text-align:right;padding:5px 8px;font-size:9px;">GHS</th></tr></thead>
            <tbody>
                <tr><td colspan="3" style="font-weight:700;padding:6px 8px;background:#f0fdf4;color:#059669;">REVENUE</td></tr>
                <?= build_section($balances['revenue'], '#059669') ?>
                <tr class="total-row"><td colspan="2">Total Revenue</td><td class="right"><?= number_format($totals['revenue'], 2) ?></td></tr>
                <tr><td colspan="3" style="font-weight:700;padding:6px 8px;background:#fff1f2;color:#e11d48;">EXPENSES</td></tr>
                <?= build_section($balances['expense'], '#e11d48') ?>
                <tr class="total-row"><td colspan="2">Total Expenses</td><td class="right"><?= number_format($totals['expense'], 2) ?></td></tr>
            </tbody>
        </table>
        <div class="net-box" style="background:<?= $net_income >= 0 ? '#f0fdf4' : '#fff1f2' ?>; border-color:<?= $net_income >= 0 ? '#bbf7d0' : '#fecdd3' ?>;">
            <span style="font-weight:900;color:<?= $net_income >= 0 ? '#059669' : '#e11d48' ?>;">
                NET <?= $net_income >= 0 ? 'INCOME' : 'LOSS' ?>:&nbsp; GHS <?= number_format(abs($net_income), 2) ?>
            </span>
        </div>
    </div>
</div>
<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Financial Statements &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 12, 'margin_bottom' => 12]);
$mpdf->WriteHTML($html);
$mpdf->Output('Financials_' . str_replace('/', '-', $selected_year) . '.pdf', 'D');
