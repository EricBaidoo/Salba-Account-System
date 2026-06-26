<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index'); exit;
}

$run_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$run = $conn->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
if (!$run) die("Payroll run not found.");

$period_name = date("F", mktime(0, 0, 0, $run['payroll_month'], 10)) . ' ' . $run['payroll_year'];
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

$res = $conn->query("
    SELECT pr.net_salary, sp.full_name,
           sss.bank_name, sss.account_number, sp.bank_details as sp_bank_details
    FROM payroll_records pr
    JOIN staff_profiles sp ON pr.staff_id = sp.id
    LEFT JOIN staff_salary_structures sss ON sp.id = sss.staff_id
    WHERE pr.payroll_run_id = $run_id
    ORDER BY sp.full_name ASC
");
$records = [];
while ($r = $res->fetch_assoc()) $records[] = $r;

// Build rows
$rows = '';
$sn = 1;
foreach ($records as $r) {
    $bank_str = '';
    if (!empty($r['bank_name']) || !empty($r['account_number'])) {
        $parts = array_filter([$r['bank_name'], $r['account_number']]);
        $bank_str = implode(', ', $parts);
    } elseif (!empty($r['sp_bank_details'])) {
        $bank_str = $r['sp_bank_details'];
    }
    $bg = ($sn % 2 === 0) ? 'background:#f8fafc;' : '';
    $rows .= '<tr style="' . $bg . '">
        <td class="center">' . $sn++ . '</td>
        <td class="bold upper">' . htmlspecialchars($r['full_name']) . '</td>
        <td>' . htmlspecialchars($bank_str ?: 'Cash / Cheque') . '</td>
        <td class="right bold">' . number_format($r['net_salary'], 2) . '</td>
    </tr>';
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; margin: 0; }
    .title-bar { background: #1e3a5f; color: #fff; text-align: center;
                 font-weight: 900; font-size: 13px; text-transform: uppercase;
                 letter-spacing: 1px; padding: 9px 8px; }
    table { width: 100%; border-collapse: collapse; }
    thead th { background: #f5c200; color: #000; font-weight: 900; font-size: 10px;
               text-transform: uppercase; letter-spacing: 0.5px; padding: 7px 8px;
               border: 1px solid #d4a800; }
    tbody td { padding: 6px 8px; border: 1px solid #ddd; font-size: 10px; }
    tfoot td { padding: 7px 8px; background: #f0f0f0; font-weight: 900;
               border: 1px solid #999; font-size: 10px; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .bold   { font-weight: 700; }
    .upper  { text-transform: uppercase; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 35px; }
    .sig-table td { text-align: center; font-size: 9px; color: #555;
                    padding-top: 6px; border-top: 1px solid #aaa; width: 33%; }
    .footer { text-align: center; font-size: 8px; color: #999; margin-top: 14px; }
</style>
</head>
<body>
<div class="title-bar">STAFF PAYROLL FOR <?= strtoupper($period_name) ?></div>

<table>
    <thead>
        <tr>
            <th class="center" style="width:40px;">S/N.</th>
            <th>STAFF NAME</th>
            <th>BANK ACC</th>
            <th class="right" style="width:90px;">SALARY</th>
        </tr>
    </thead>
    <tbody>
        <?= $rows ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="right">TOTAL</td>
            <td class="right"><?= number_format($run['total_net'], 2) ?></td>
        </tr>
    </tfoot>
</table>

<table class="sig-table">
    <tr>
        <td>Prepared by: _______________________</td>
        <td>Approved by: _______________________</td>
        <td>Date: _______________________</td>
    </tr>
</table>

<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Staff Payroll &middot; Generated <?= date('d M Y H:i') ?></div>
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
$filename = 'Payroll_' . str_replace(' ', '_', $period_name) . '.pdf';
$mpdf->Output($filename, 'D');
