<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if ($payment_id <= 0) die('Invalid payment ID.');

$stmt = $conn->prepare("SELECT p.*, s.first_name, s.last_name, s.class FROM payments p LEFT JOIN students s ON p.student_id = s.id WHERE p.id = ?");
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();
if (!$payment) die('Payment not found.');

$allocations = [];
$a = $conn->prepare("SELECT pa.*, f.name AS fee_name, sf.semester AS sf_term, sf.academic_year AS sf_academic_year FROM payment_allocations pa LEFT JOIN student_fees sf ON pa.student_fee_id = sf.id LEFT JOIN fees f ON sf.fee_id = f.id WHERE pa.payment_id = ?");
$a->bind_param('i', $payment_id);
$a->execute();
$ar = $a->get_result();
while ($row = $ar->fetch_assoc()) $allocations[] = $row;

$outstanding = 0;
if ($payment['student_id']) {
    $fs = $conn->prepare("SELECT COALESCE(SUM(amount),0) as td FROM student_fees WHERE student_id = ? AND status != 'cancelled'");
    $fs->bind_param('i', $payment['student_id']);
    $fs->execute();
    $total_due = (float)$fs->get_result()->fetch_assoc()['td'];
    $ps = $conn->prepare("SELECT COALESCE(SUM(amount),0) as tp FROM payments WHERE student_id = ?");
    $ps->bind_param('i', $payment['student_id']);
    $ps->execute();
    $total_paid = (float)$ps->get_result()->fetch_assoc()['tp'];
    $outstanding = max(0, $total_due - $total_paid);
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
$school_addr = getSystemSetting($conn, 'school_address', '');

// Build allocation rows
$alloc_rows = '';
if (!empty($allocations)) {
    foreach ($allocations as $al) {
        $yr_disp = formatAcademicYearDisplay($conn, $al['sf_academic_year'] ?? '');
        $alloc_rows .= '<tr>
            <td>' . htmlspecialchars($al['fee_name']) . '</td>
            <td class="center">' . htmlspecialchars($al['sf_term'] ?? '') . ' &middot; ' . htmlspecialchars($yr_disp) . '</td>
            <td class="right bold">' . number_format($al['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $alloc_rows = '<tr>
        <td>' . htmlspecialchars($payment['description'] ?: 'Direct Fee Settlement') . '</td>
        <td class="center">' . htmlspecialchars($payment['semester'] ?? '') . ' &middot; ' . htmlspecialchars(formatAcademicYearDisplay($conn, $payment['academic_year'] ?? '')) . '</td>
        <td class="right bold">' . number_format($payment['amount'], 2) . '</td>
    </tr>';
}

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; }
    .header-bar { background: #059669; color: #fff; padding: 18px 20px; display: table; width: 100%; }
    .header-left { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .school-name { font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
    .school-sub  { font-size: 9px; opacity: .8; margin-top: 2px; }
    .receipt-no-label { font-size: 8px; font-weight: 700; text-transform: uppercase; opacity: .7; }
    .receipt-no { font-size: 14px; font-weight: 900; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 16px; margin: 14px 0; display: table; width: 100%; border-radius: 6px; }
    .info-col { display: table-cell; width: 50%; vertical-align: top; }
    .info-label { font-size: 8px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-value { font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 2px; }
    .section-title { font-size: 8px; font-weight: 700; color: #64748b; text-transform: uppercase;
                     letter-spacing: 1px; margin-bottom: 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
    table.alloc { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.alloc thead th { background: #1e293b; color: #fff; padding: 7px 10px; font-size: 9px; text-transform: uppercase; }
    table.alloc tbody td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
    .total-bar { background: #0f172a; color: #fff; padding: 12px 16px; display: table; width: 100%;
                 border-radius: 6px; margin-bottom: 8px; }
    .total-label { display: table-cell; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .total-amount { display: table-cell; text-align: right; font-size: 16px; font-weight: 900; }
    .outstanding-bar { background: #fff1f2; border: 1px solid #fecdd3; color: #e11d48; padding: 10px 16px;
                       display: table; width: 100%; border-radius: 6px; margin-bottom: 14px; }
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 30px; }
    .sig-table td { text-align: center; font-size: 9px; color: #94a3b8; padding-top: 6px;
                    border-top: 1px solid #cbd5e1; width: 50%; }
    .footer { text-align: center; font-size: 8px; color: #cbd5e1; margin-top: 20px; }
    .center { text-align: center; }
    .right  { text-align: right; }
    .bold   { font-weight: 700; }
</style>
</head>
<body>
<div class="header-bar">
    <div class="header-left">
        <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
        <?php if ($school_addr): ?>
        <div class="school-sub"><?= htmlspecialchars($school_addr) ?></div>
        <?php endif; ?>
        <div class="school-sub" style="margin-top:4px;">OFFICIAL FEE RECEIPT</div>
    </div>
    <div class="header-right">
        <div class="receipt-no-label">Receipt Number</div>
        <div class="receipt-no"><?= htmlspecialchars($payment['receipt_no']) ?></div>
    </div>
</div>

<div class="info-box">
    <div class="info-col">
        <div class="info-label">Student Name</div>
        <div class="info-value"><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?></div>
        <div style="margin-top:6px;">
            <div class="info-label">Class</div>
            <div class="info-value" style="color:#059669;"><?= htmlspecialchars($payment['class'] ?? '—') ?></div>
        </div>
    </div>
    <div class="info-col" style="text-align:right;">
        <div class="info-label">Payment Date</div>
        <div class="info-value"><?= date('M j, Y', strtotime($payment['payment_date'])) ?></div>
        <div style="margin-top:6px;">
            <div class="info-label">Payment Method</div>
            <div class="info-value"><?= htmlspecialchars($payment['payment_method'] ?? 'Cash') ?></div>
        </div>
    </div>
</div>

<div class="section-title">Allocation Breakdown</div>
<table class="alloc">
    <thead>
        <tr>
            <th style="text-align:left;">Fee Classification</th>
            <th style="text-align:center;">Semester / Year</th>
            <th style="text-align:right; width:100px;">Value (GHS)</th>
        </tr>
    </thead>
    <tbody><?= $alloc_rows ?></tbody>
</table>

<div class="total-bar">
    <div class="total-label">Aggregate Remittance</div>
    <div class="total-amount">GHS <?= number_format($payment['amount'], 2) ?></div>
</div>
<?php if ($payment['student_id'] && $outstanding > 0): ?>
<div class="outstanding-bar">
    <div style="display:table-cell; font-size:9px; font-weight:700; text-transform:uppercase;">Residual Liability</div>
    <div style="display:table-cell; text-align:right; font-size:12px; font-weight:900;">GHS <?= number_format($outstanding, 2) ?></div>
</div>
<?php endif; ?>

<table class="sig-table">
    <tr>
        <td>Accounts Department Signature / Stamp</td>
        <td>Date: _______________________________</td>
    </tr>
</table>

<div class="footer">
    Generated <?= date('d M Y H:i') ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($school_name) ?>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A5', 'margin_left' => 12, 'margin_right' => 12, 'margin_top' => 10, 'margin_bottom' => 10]);
$mpdf->WriteHTML($html);
$mpdf->Output('Receipt_' . $payment['receipt_no'] . '.pdf', 'D');
