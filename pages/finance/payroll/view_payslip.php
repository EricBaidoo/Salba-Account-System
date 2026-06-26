<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Enforce admin/finance only
if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

$record_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$query = "
    SELECT pr.*, prun.payroll_month, prun.payroll_year, 
           sp.full_name, sp.job_title, sp.department, sp.ssnit_number, sp.phone_number,
           sss.bank_name, sss.account_number
    FROM payroll_records pr
    JOIN payroll_runs prun ON pr.payroll_run_id = prun.id
    JOIN staff_profiles sp ON pr.staff_id = sp.id
    LEFT JOIN staff_salary_structures sss ON sp.id = sss.staff_id
    WHERE pr.id = $record_id
";
$record = $conn->query($query)->fetch_assoc();

if (!$record) {
    die("Payslip record not found.");
}

$alws = json_decode($record['custom_allowances'] ?? '[]', true) ?: [];
$deds = json_decode($record['custom_deductions'] ?? '[]', true) ?: [];
$gtaxes = json_decode($record['global_taxes'] ?? '[]', true) ?: [];

$tot_alw = array_sum(array_column($alws, 'amount'));
$tot_ded = array_sum(array_column($deds, 'amount'));
$tot_gtax = array_sum(array_column($gtaxes, 'amount'));

$period_name = date("F", mktime(0, 0, 0, $record['payroll_month'], 10)) . ' ' . $record['payroll_year'];
$gross = $record['base_salary'] + $tot_alw;
$total_deductions = $tot_ded + $record['tier_1_employee'] + $record['tier_2_employee'] + $tot_gtax;

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '123 Edu Lane');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?= htmlspecialchars($record['full_name']) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 40px; }
        .payslip-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #1e293b; font-size: 24px; text-transform: uppercase; letter-spacing: 2px; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 14px; }
        .payslip-title { text-align: center; background: #f8fafc; padding: 10px; font-weight: bold; font-size: 18px; margin-bottom: 30px; border: 1px solid #e2e8f0; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-item { font-size: 13px; margin-bottom: 8px; }
        .info-label { font-weight: bold; color: #64748b; display: inline-block; width: 120px; }
        .info-value { color: #0f172a; font-weight: 600; }
        
        table { w-full; width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th { background: #1e293b; color: white; text-align: left; padding: 12px; font-size: 13px; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #334155; }
        .amount { text-align: right; font-family: monospace; font-size: 15px; }
        
        .net-pay { display: flex; justify-content: flex-end; align-items: center; background: #f8fafc; padding: 20px; border: 2px solid #e2e8f0; border-radius: 8px; }
        .net-pay-label { font-size: 18px; font-weight: bold; color: #0f172a; margin-right: 20px; }
        .net-pay-amount { font-size: 24px; font-weight: bold; color: #059669; }
        
        .footer { margin-top: 50px; display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8; }
        .signature-line { border-top: 1px solid #cbd5e1; width: 200px; text-align: center; padding-top: 10px; margin-top: 40px; }
        
        @media print {
            body { background: white; padding: 0; }
            .payslip-container { box-shadow: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px; max-width: 800px; margin: 0 auto 20px auto;">
        <a href="download_payslip_pdf.php?id=<?= $record_id ?>" class="no-print" style="padding: 10px 20px; background: #4f46e5; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; text-decoration:none; display:inline-block;">⬇ Download PDF</a>
    </div>

    <div class="payslip-container">
        <div class="header">
            <h1><?= htmlspecialchars($school_name) ?></h1>
            <p><?= htmlspecialchars($school_address) ?></p>
        </div>
        
        <div class="payslip-title">
            PAYSLIP FOR <?= strtoupper($period_name) ?>
        </div>

        <div class="info-grid">
            <div>
                <div class="info-item"><span class="info-label">Employee Name:</span> <span class="info-value"><?= htmlspecialchars($record['full_name']) ?></span></div>
                <div class="info-item"><span class="info-label">Designation:</span> <span class="info-value"><?= htmlspecialchars($record['job_title'] ?? 'Staff') ?></span></div>
                <div class="info-item"><span class="info-label">Department:</span> <span class="info-value"><?= htmlspecialchars($record['department'] ?? 'General') ?></span></div>
            </div>
            <div>
                <div class="info-item"><span class="info-label">Bank Name:</span> <span class="info-value"><?= htmlspecialchars($record['bank_name'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span class="info-label">Account No:</span> <span class="info-value"><?= htmlspecialchars($record['account_number'] ?? 'N/A') ?></span></div>
                <div class="info-item"><span class="info-label">SSNIT No:</span> <span class="info-value"><?= htmlspecialchars($record['ssnit_number'] ?? 'N/A') ?></span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">EARNINGS</th>
                    <th style="width: 50%;">DEDUCTIONS</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="vertical-align: top;">
                        <table style="margin: 0; border: none;">
                            <tr>
                                <td style="padding: 5px 0; border: none;">Basic Salary</td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($record['base_salary'], 2) ?></td>
                            </tr>
                            <?php foreach($alws as $alw): if($alw['amount'] > 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none;"><?= htmlspecialchars($alw['name']) ?></td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($alw['amount'], 2) ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </table>
                    </td>
                    <td style="vertical-align: top;">
                        <table style="margin: 0; border: none;">
                            <?php if($record['tier_1_employee'] > 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none;">SSNIT (Tier 1)</td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($record['tier_1_employee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if($record['tier_2_employee'] > 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none;">SSNIT (Tier 2)</td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($record['tier_2_employee'], 2) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach($gtaxes as $gtax): if($gtax['amount'] > 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none;"><?= htmlspecialchars($gtax['name']) ?></td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($gtax['amount'], 2) ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                            
                            <?php foreach($deds as $ded): if($ded['amount'] > 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none;"><?= htmlspecialchars($ded['name']) ?></td>
                                <td class="amount" style="padding: 5px 0; border: none;"><?= number_format($ded['amount'], 2) ?></td>
                            </tr>
                            <?php endif; endforeach; ?>
                            <?php if($total_deductions == 0): ?>
                            <tr>
                                <td style="padding: 5px 0; border: none; color: #94a3b8; font-style: italic;">No deductions</td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: bold;">
                    <td style="border-top: 2px solid #1e293b;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Gross Earnings</span>
                            <span class="amount"><?= number_format($gross, 2) ?></span>
                        </div>
                    </td>
                    <td style="border-top: 2px solid #1e293b;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Total Deductions</span>
                            <span class="amount"><?= number_format($total_deductions, 2) ?></span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="net-pay">
            <div class="net-pay-label">NET SALARY PAYABLE:</div>
            <div class="net-pay-amount">GHS <?= number_format($record['net_salary'], 2) ?></div>
        </div>

        <div style="display: flex; justify-content: space-between; margin-top: 60px;">
            <div class="signature-line">Employer Signature</div>
            <div class="signature-line">Employee Signature</div>
        </div>
        
        <div class="footer">
            <div>Generated on <?= date("d M Y H:i") ?></div>
            <div>Status: <?= strtoupper($record['status']) ?></div>
        </div>
    </div>
</body>
</html>
