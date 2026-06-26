<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if ($_SESSION['role'] !== 'admin') { header('Location: ' . BASE_URL . 'index'); exit; }

$id = intval($_GET['id'] ?? 0);
if (!$id) die('ID required.');

$s = $conn->query("SELECT sp.*, u.username, u.role as user_role FROM staff_profiles sp LEFT JOIN users u ON u.staff_id = sp.id WHERE sp.id = $id LIMIT 1")->fetch_assoc();
if (!$s) die('Staff not found.');

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');

function row($label, $value) {
    return '<tr><td class="lbl">' . htmlspecialchars($label) . '</td><td class="val">' . htmlspecialchars($value ?: '—') . '</td></tr>';
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
    .report-title { font-size: 11px; font-weight: 700; text-transform: uppercase; margin-top: 4px; }
    .sub { font-size: 9px; color: #64748b; margin-top: 2px; }
    .divider { border: none; border-top: 2px solid #1e293b; margin: 8px 0; }
    .name-banner { background: #1e3a5f; color: #fff; padding: 14px 18px; margin-bottom: 14px; border-radius: 6px; }
    .name-title { font-size: 16px; font-weight: 900; }
    .name-sub { font-size: 10px; opacity: .8; margin-top: 3px; }
    .section-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin: 12px 0 5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; }
    .two-col { display: table; width: 100%; }
    .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
    .col2 { display: table-cell; width: 50%; vertical-align: top; padding-left: 4px; }
    table.info { width: 100%; border-collapse: collapse; }
    table.info td { padding: 4px 6px; border-bottom: 1px solid #f1f5f9; }
    .lbl { font-weight: 700; color: #64748b; width: 120px; font-size: 9px; text-transform: uppercase; }
    .val { color: #0f172a; font-weight: 600; }
    .footer { text-align: center; font-size: 8px; color: #94a3b8; margin-top: 20px; }
</style>
</head>
<body>
<div class="header">
    <div class="school-name"><?= htmlspecialchars(strtoupper($school_name)) ?></div>
    <div class="report-title">Staff Profile</div>
    <div class="sub">Generated: <?= date('d M Y H:i') ?></div>
</div>
<hr class="divider">

<div class="name-banner">
    <div class="name-title"><?= htmlspecialchars($s['full_name']) ?></div>
    <div class="name-sub"><?= htmlspecialchars(($s['job_title'] ?? '') . ($s['department'] ? ' — ' . $s['department'] : '')) ?></div>
</div>

<div class="two-col">
    <div class="col">
        <div class="section-title">Personal Details</div>
        <table class="info">
            <?= row('Gender', $s['gender']) ?>
            <?= row('Date of Birth', $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : '') ?>
            <?= row('Nationality', $s['nationality']) ?>
            <?= row('SSNIT No.', $s['ssnit_number']) ?>
        </table>
        <div class="section-title" style="margin-top:10px;">Contact</div>
        <table class="info">
            <?= row('Phone', $s['phone_number']) ?>
            <?= row('Email', $s['email']) ?>
            <?= row('Address', $s['address']) ?>
        </table>
    </div>
    <div class="col2">
        <div class="section-title">Employment</div>
        <table class="info">
            <?= row('Job Title', $s['job_title']) ?>
            <?= row('Department', $s['department']) ?>
            <?= row('Staff Type', $s['staff_type']) ?>
            <?= row('Employment Status', $s['employment_status']) ?>
            <?= row('Date Hired', $s['date_hired'] ? date('d M Y', strtotime($s['date_hired'])) : '') ?>
            <?= row('Qualification', $s['qualification']) ?>
        </table>
        <div class="section-title" style="margin-top:10px;">Bank Details</div>
        <table class="info">
            <?= row('Bank Details', $s['bank_details']) ?>
        </table>
        <?php if (!empty($s['emergency_name'])): ?>
        <div class="section-title" style="margin-top:10px;">Emergency Contact</div>
        <table class="info">
            <?= row('Name', $s['emergency_name']) ?>
            <?= row('Phone', $s['emergency_phone']) ?>
            <?= row('Relation', $s['emergency_relation']) ?>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="footer"><?= htmlspecialchars($school_name) ?> &middot; Staff Profile &middot; <?= date('Y') ?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4', 'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 14, 'margin_bottom' => 14]);
$mpdf->WriteHTML($html);
$name = preg_replace('/[^A-Za-z0-9]/', '_', $s['full_name']);
$mpdf->Output('Staff_Profile_' . $name . '.pdf', 'D');
