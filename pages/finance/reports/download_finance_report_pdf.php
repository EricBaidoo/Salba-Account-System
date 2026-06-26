<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) { header('Location: ' . BASE_URL . 'index'); exit; }

$current_year  = getAcademicYear($conn);
$current_term  = getCurrentSemester($conn);
$selected_year = $_GET['academic_year'] ?? $current_year;
$selected_term = $_GET['semester']      ?? 'All';
$filter_term   = ($selected_term !== 'All' && $selected_term !== '');
$display_year  = formatAcademicYearDisplay($conn, $selected_year);
$school_name   = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

// Total Income
$inc_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM payments WHERE academic_year = ?" . ($filter_term ? " AND semester = ?" : ""));
if ($filter_term) $inc_stmt->bind_param('ss', $selected_year, $selected_term);
else              $inc_stmt->bind_param('s', $selected_year);
$inc_stmt->execute();
$total_income = (float)$inc_stmt->get_result()->fetch_assoc()['t'];

// Income by fee category (allocated)
$income_by_fee = [];
$fee_stmt = $conn->prepare("SELECT f.name AS cat, SUM(pa.amount) AS total FROM payment_allocations pa JOIN student_fees sf ON pa.student_fee_id = sf.id JOIN payments p ON pa.payment_id = p.id JOIN fees f ON sf.fee_id = f.id WHERE p.academic_year = ?" . ($filter_term ? " AND p.semester = ?" : "") . " GROUP BY f.id, f.name ORDER BY total DESC");
if ($filter_term) $fee_stmt->bind_param('ss', $selected_year, $selected_term);
else              $fee_stmt->bind_param('s', $selected_year);
$fee_stmt->execute();
$fres = $fee_stmt->get_result();
while ($r = $fres->fetch_assoc()) $income_by_fee[] = $r;

// Total Expenses
$exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM expenses WHERE academic_year = ?" . ($filter_term ? " AND semester = ?" : ""));
if ($filter_term) $exp_stmt->bind_param('ss', $selected_year, $selected_term);
else              $exp_stmt->bind_param('s', $selected_year);
$exp_stmt->execute();
$total_expenses = (float)$exp_stmt->get_result()->fetch_assoc()['t'];

// Expenses by category
$expense_by_cat = [];
$ecat_stmt = $conn->prepare("SELECT ec.name AS cat, SUM(e.amount) AS total FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.academic_year = ?" . ($filter_term ? " AND e.semester = ?" : "") . " GROUP BY ec.id ORDER BY total DESC");
if ($filter_term) $ecat_stmt->bind_param('ss', $selected_year, $selected_term);
else              $ecat_stmt->bind_param('s', $selected_year);
$ecat_stmt->execute();
$eres = $ecat_stmt->get_result();
while ($r = $eres->fetch_assoc()) $expense_by_cat[] = $r;

$net = $total_income - $total_expenses;

$inc_rows = '';
foreach ($income_by_fee as $r) {
    $inc_rows .= '<tr><td>' . htmlspecialchars($r['cat']) . '</td><td class="right">' . number_format($r['total'], 2) . '</td></tr>';
}
$exp_rows = '';
foreach ($expense_by_cat as $r) {
    $exp_rows .= '<tr><td>' . htmlspecialchars($r['cat'] ?? 'Uncategorised') . '</td><td class="right">' . number_format($r['total'], 2) . '</td></tr>';
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
    .summary-grid { display: table; width: 100%; margin-bottom: 18px; border-collapse: separate; border-spacing: 6px; }
    .summary-cell { display: table-cell; width: 33%; padding: 12px 14px; border-radius: 6px; }
    .s-label { font-size: 8px; font-weight: 700; text-transform: uppercase; opacity: .7; }
    .s-value { font-size: 18px; font-weight: 900; margin-top: 3px; }
    .col-table { display: table; width: 100%; border-spacing: 8px; border-collapse: separate; }
    .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
    .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px; }
    table.detail { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    table.detail thead th { padding: 6px 8px; font-size: 9px; text-transform: uppercase; }
    table.detail tbody td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; }
    table.detail tfoot td { padding: 6px 8px; font-weight: 900; border-top: 1px solid; }
    .net-box { padding: 12px 16px; border-radius: 6px; text-align: right; margin-top: 14px; }
    .right { text-align: right; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 16px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Financial Report — <?= htmlspecialchars($selected_term !== 'All' ? $selected_term . ' ' : '') ?><?= htmlspecialchars($display_year) ?></div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?></div>
</div>
<hr class="divider">

<div class="summary-grid">
    <div class="summary-cell" style="background:#f0fdf4; border:1px solid #bbf7d0;">
        <div class="s-label" style="color:#059669;">Total Income</div>
        <div class="s-value" style="color:#059669;">GHS <?= number_format($total_income, 2) ?></div>
    </div>
    <div class="summary-cell" style="background:#fff1f2; border:1px solid #fecdd3;">
        <div class="s-label" style="color:#e11d48;">Total Expenses</div>
        <div class="s-value" style="color:#e11d48;">GHS <?= number_format($total_expenses, 2) ?></div>
    </div>
    <div class="summary-cell" style="background:<?= $net >= 0 ? '#f0fdf4' : '#fff1f2' ?>; border:1px solid <?= $net >= 0 ? '#bbf7d0' : '#fecdd3' ?>;">
        <div class="s-label" style="color:<?= $net >= 0 ? '#059669' : '#e11d48' ?>;">Net <?= $net >= 0 ? 'Surplus' : 'Deficit' ?></div>
        <div class="s-value" style="color:<?= $net >= 0 ? '#059669' : '#e11d48' ?>;">GHS <?= number_format(abs($net), 2) ?></div>
    </div>
</div>

<div class="col-table">
    <div class="col">
        <div class="section-title">Income Breakdown</div>
        <table class="detail">
            <thead><tr style="background:#f0fdf4;"><th style="text-align:left;">Fee Type</th><th class="right" style="width:90px;">Amount (GHS)</th></tr></thead>
            <tbody><?= $inc_rows ?: '<tr><td colspan="2" style="color:#94a3b8;font-style:italic;padding:5px 8px;">No income data</td></tr>' ?></tbody>
            <tfoot><tr style="color:#059669;border-top-color:#059669;"><td>Total Income</td><td class="right">GHS <?= number_format($total_income, 2) ?></td></tr></tfoot>
        </table>
    </div>
    <div class="col">
        <div class="section-title">Expense Breakdown</div>
        <table class="detail">
            <thead><tr style="background:#fff1f2;"><th style="text-align:left;">Category</th><th class="right" style="width:90px;">Amount (GHS)</th></tr></thead>
            <tbody><?= $exp_rows ?: '<tr><td colspan="2" style="color:#94a3b8;font-style:italic;padding:5px 8px;">No expense data</td></tr>' ?></tbody>
            <tfoot><tr style="color:#e11d48;border-top-color:#e11d48;"><td>Total Expenses</td><td class="right">GHS <?= number_format($total_expenses, 2) ?></td></tr></tfoot>
        </table>
    </div>
</div>

<div class="net-box" style="background:<?= $net >= 0 ? '#f0fdf4' : '#fff1f2' ?>; border:1px solid <?= $net >= 0 ? '#bbf7d0' : '#fecdd3' ?>;">
    <span style="font-size:11px; font-weight:700; color:<?= $net >= 0 ? '#059669' : '#e11d48' ?>;">
        NET <?= $net >= 0 ? 'SURPLUS' : 'DEFICIT' ?>:&nbsp;&nbsp; GHS <?= number_format(abs($net), 2) ?>
    </span>
</div>

<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Financial Report &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14]);
$mpdf->WriteHTML($html);
$mpdf->Output('Finance_Report_' . str_replace('/', '-', $selected_year) . '.pdf', 'D');
