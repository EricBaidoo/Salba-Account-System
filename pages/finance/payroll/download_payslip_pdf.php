<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index'); exit;
}

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$record = $conn->query("
    SELECT pr.*, prun.payroll_month, prun.payroll_year,
           sp.full_name, sp.job_title, sp.department, sp.ssnit_number, sp.phone_number,
           COALESCE(sss.bank_name, '') as bank_name,
           COALESCE(sss.account_number, sp.bank_details) as account_detail
    FROM payroll_records pr
    JOIN payroll_runs prun ON pr.payroll_run_id = prun.id
    JOIN staff_profiles sp ON pr.staff_id = sp.id
    LEFT JOIN staff_salary_structures sss ON sp.id = sss.staff_id
    WHERE pr.id = $record_id
")->fetch_assoc();

if (!$record) die("Record not found.");

$alws   = json_decode($record['custom_allowances'] ?? '[]', true) ?: [];
$deds   = json_decode($record['custom_deductions']  ?? '[]', true) ?: [];
$gtaxes = json_decode($record['global_taxes']       ?? '[]', true) ?: [];

$tot_alw  = array_sum(array_column($alws,   'amount'));
$tot_gtax = array_sum(array_column($gtaxes, 'amount'));
$total_deductions = (float)$record['deductions'] + (float)$record['tier_1_employee'] + (float)$record['tier_2_employee'] + $tot_gtax;

$period_name  = date("F", mktime(0, 0, 0, $record['payroll_month'], 10)) . ' ' . $record['payroll_year'];
$gross        = $record['base_salary'] + $tot_alw;
$school_name  = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
$school_addr  = getSystemSetting($conn, 'school_address', '');

// Build earnings rows
$earnings_rows = '<tr><td>Basic Salary</td><td class="amt">' . number_format($record['base_salary'], 2) . '</td></tr>';
foreach ($alws as $a) {
    if ((float)$a['amount'] > 0)
        $earnings_rows .= '<tr><td>' . htmlspecialchars($a['name']) . '</td><td class="amt">' . number_format($a['amount'], 2) . '</td></tr>';
}
$earnings_rows .= '<tr class="subtotal"><td>Gross Earnings</td><td class="amt">' . number_format($gross, 2) . '</td></tr>';

// Build deductions rows
$ded_rows = '';
if ($record['tier_1_employee'] > 0)
    $ded_rows .= '<tr><td>SSNIT Tier 1 (Employee)</td><td class="amt">' . number_format($record['tier_1_employee'], 2) . '</td></tr>';
if ($record['tier_2_employee'] > 0)
    $ded_rows .= '<tr><td>SSNIT Tier 2 (Employee)</td><td class="amt">' . number_format($record['tier_2_employee'], 2) . '</td></tr>';
foreach ($gtaxes as $g) {
    if ((float)$g['amount'] > 0)
        $ded_rows .= '<tr><td>' . htmlspecialchars($g['name']) . '</td><td class="amt">' . number_format($g['amount'], 2) . '</td></tr>';
}
foreach ($deds as $d) {
    if ((float)$d['amount'] > 0)
        $ded_rows .= '<tr><td>' . htmlspecialchars($d['name']) . '</td><td class="amt">' . number_format($d['amount'], 2) . '</td></tr>';
}
if ($total_deductions == 0)
    $ded_rows .= '<tr><td style="color:#94a3b8;font-style:italic;">No deductions</td><td></td></tr>';
$ded_rows .= '<tr class="subtotal"><td>Total Deductions</td><td class="amt">' . number_format($total_deductions, 2) . '</td></tr>';

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
    .header { text-align: center; padding-bottom: 12px; margin-bottom: 18px; border-bottom: 2px solid #1e293b; }
    .school-name { font-size: 15px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
    .school-addr { font-size: 9px; color: #64748b; margin-top: 2px; }
    .payslip-title { text-align: center; background: #f1f5f9; padding: 7px; font-weight: 900;
                     font-size: 13px; text-transform: uppercase; letter-spacing: 1px;
                     border: 1px solid #e2e8f0; margin-bottom: 16px; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .info-grid td { padding: 3px 6px; font-size: 10px; width: 50%; vertical-align: top; }
    .lbl { font-weight: 700; color: #64748b; display: inline-block; width: 90px; }
    .val { font-weight: 600; color: #0f172a; }
    .split-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .split-table th { background: #1e293b; color: #fff; padding: 8px 10px; font-size: 10px;
                      text-transform: uppercase; letter-spacing: 0.5px; }
    .split-table td { padding: 5px 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px;
                      vertical-align: top; width: 50%; }
    .split-table .amt { text-align: right; font-weight: 600; }
    .split-table .subtotal td { background: #f8fafc; font-weight: 900; border-top: 2px solid #1e293b; }
    .net-box { background: #f0fdf4; border: 2px solid #bbf7d0; padding: 12px 16px;
               text-align: right; margin-bottom: 20px; }
    .net-label { font-size: 12px; font-weight: 700; color: #0f172a; }
    .net-amount { font-size: 20px; font-weight: 900; color: #059669; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 40px; }
    .sig-table td { text-align: center; font-size: 10px; color: #64748b; padding-top: 8px;
                    border-top: 1px solid #cbd5e1; width: 33%; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 20px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <?php if ($school_addr): ?>
    <div class="school-addr"><?= htmlspecialchars($school_addr) ?></div>
    <?php endif; ?>
</div>

<div class="payslip-title">PAYSLIP FOR <?= strtoupper($period_name) ?></div>

<table class="info-grid">
    <tr>
        <td>
            <div><span class="lbl">Employee:</span> <span class="val"><?= htmlspecialchars($record['full_name']) ?></span></div>
            <div style="margin-top:4px"><span class="lbl">Designation:</span> <span class="val"><?= htmlspecialchars($record['job_title'] ?? 'Staff') ?></span></div>
            <div style="margin-top:4px"><span class="lbl">Department:</span> <span class="val"><?= htmlspecialchars($record['department'] ?? '—') ?></span></div>
        </td>
        <td>
            <div><span class="lbl">Bank Name:</span> <span class="val"><?= htmlspecialchars($record['bank_name'] ?: '—') ?></span></div>
            <div style="margin-top:4px"><span class="lbl">Account No:</span> <span class="val"><?= htmlspecialchars($record['account_detail'] ?: '—') ?></span></div>
            <div style="margin-top:4px"><span class="lbl">SSNIT No:</span> <span class="val"><?= htmlspecialchars($record['ssnit_number'] ?: '—') ?></span></div>
        </td>
    </tr>
</table>

<table class="split-table">
    <thead>
        <tr>
            <th style="width:50%; border-right:1px solid #334155;">EARNINGS</th>
            <th style="width:50%;">DEDUCTIONS</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td style="vertical-align:top; border-right:1px solid #e2e8f0;">
                <table style="width:100%; border-collapse:collapse;">
                    <?= $earnings_rows ?>
                </table>
            </td>
            <td style="vertical-align:top;">
                <table style="width:100%; border-collapse:collapse;">
                    <?= $ded_rows ?>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<div class="net-box">
    <span class="net-label">NET SALARY PAYABLE: </span>
    <span class="net-amount">GHS <?= number_format($record['net_salary'], 2) ?></span>
</div>

<table class="sig-table">
    <tr>
        <td>Employer Signature</td>
        <td>Employee Signature</td>
        <td>Date</td>
    </tr>
</table>

<div class="footer">
    Generated on <?= date("d M Y H:i") ?> &nbsp;&middot;&nbsp;
    Status: <?= strtoupper($record['status']) ?> &nbsp;&middot;&nbsp;
    <?= htmlspecialchars($school_name) ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 15,
    'margin_right'  => 15,
    'margin_top'    => 15,
    'margin_bottom' => 15,
]);
$mpdf->WriteHTML($html);
$filename = 'Payslip_' . preg_replace('/[^A-Za-z0-9]/', '_', $record['full_name']) . '_' . $period_name . '.pdf';
$mpdf->Output($filename, 'D');
