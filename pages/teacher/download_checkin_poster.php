<?php
require_once '../../../vendor/autoload.php';
include '../../../includes/auth_functions.php';
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../login'); exit;
}
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

$school_name    = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$school_address = getSystemSetting($conn, 'school_address', '');
$school_phone   = getSystemSetting($conn, 'school_phone', '');
$logo_path      = getSystemLogo($conn);

// Full URL to the check-in page
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST']
      . rtrim(BASE_URL, '/') . '/pages/teacher/check_in';

// QR code using Google Charts API (no composer needed)
$qr_size = 300;
$qr_url  = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $qr_size . 'x' . $qr_size
         . '&chl=' . urlencode($base)
         . '&choe=UTF-8&chld=H|1';

// Encode logo to base64 for embedding
$logo_full_path = realpath(dirname(__FILE__) . '/../../../' . ltrim($logo_path, '/'));
$logo_b64 = '';
$logo_mime = 'image/png';
if ($logo_full_path && file_exists($logo_full_path)) {
    $logo_b64 = base64_encode(file_get_contents($logo_full_path));
    $ext = strtolower(pathinfo($logo_full_path, PATHINFO_EXTENSION));
    $logo_mime = match($ext) { 'jpg','jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', default => 'image/png' };
}

// Fetch QR image for embedding
$qr_data = @file_get_contents($qr_url);
$qr_b64  = $qr_data ? base64_encode($qr_data) : '';

ob_start();
?><!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 0; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        margin: 0; padding: 0;
        background: #fff;
        color: #1e293b;
    }
    .page {
        width: 210mm;
        min-height: 297mm;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20mm 18mm;
        text-align: center;
        position: relative;
    }

    /* Corner accents */
    .corner-tl, .corner-tr, .corner-bl, .corner-br {
        position: absolute; width: 30mm; height: 30mm;
        border-color: #4f46e5;
        border-style: solid;
    }
    .corner-tl { top: 10mm; left: 10mm; border-width: 0.1875rem 0 0 0.1875rem; }
    .corner-tr { top: 10mm; right: 10mm; border-width: 0.1875rem 0.1875rem 0 0; }
    .corner-bl { bottom: 10mm; left: 10mm; border-width: 0 0 0.1875rem 0.1875rem; }
    .corner-br { bottom: 10mm; right: 10mm; border-width: 0 0.1875rem 0.1875rem 0; }

    .header-label {
        font-size: 0.5rem; font-weight: 900;
        color: #6366f1;
        text-transform: uppercase;
        letter-spacing: 0.25rem;
        margin-bottom: 0.625rem;
    }
    .school-name {
        font-size: 1.625rem; font-weight: 900;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.0625rem;
        line-height: 1.1;
        margin-bottom: 0.25rem;
    }
    .school-sub {
        font-size: 0.625rem; color: #64748b; font-weight: 700;
        margin-bottom: 0.375rem;
        letter-spacing: 0.0625rem;
    }
    .divider {
        width: 60mm; height: 0.125rem;
        background: linear-gradient(90deg, transparent, #6366f1, transparent);
        margin: 10mm auto;
    }
    .scan-title {
        font-size: 0.8125rem; font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.1875rem;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    .scan-sub {
        font-size: 0.5625rem; color: #64748b; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.125rem;
        margin-bottom: 8mm;
    }

    /* Logo ring */
    .logo-ring {
        width: 32mm; height: 32mm;
        border: 0.1875rem solid #e0e7ff;
        border-radius: 50%;
        margin: 0 auto 6mm;
        display: flex; align-items: center; justify-content: center;
        background: #f8fafc;
        overflow: hidden;
        box-shadow: 0 0 0 0.375rem #eef2ff;
    }
    .logo-ring img { width: 28mm; height: 28mm; object-fit: contain; }

    /* QR container */
    .qr-outer {
        border: 0.1875rem solid #e0e7ff;
        border-radius: 8mm;
        padding: 4mm;
        background: #fff;
        box-shadow: 0 0.5rem 1.875rem rgba(99,102,241,0.12);
        display: inline-block;
        margin-bottom: 6mm;
    }
    .qr-outer img { width: 65mm; height: 65mm; display: block; }

    .url-box {
        background: #f1f5f9;
        border: 0.0625rem solid #e2e8f0;
        border-radius: 4mm;
        padding: 4mm 8mm;
        font-size: 0.5rem;
        font-weight: 900;
        color: #4f46e5;
        letter-spacing: 0.0625rem;
        word-break: break-all;
        margin-bottom: 8mm;
        max-width: 140mm;
    }

    /* Steps */
    .steps-box {
        background: #f8fafc;
        border: 0.0625rem solid #e2e8f0;
        border-radius: 5mm;
        padding: 6mm 10mm;
        text-align: left;
        max-width: 145mm;
        margin-bottom: 8mm;
    }
    .steps-title {
        font-size: 0.5rem; font-weight: 900; color: #4f46e5;
        text-transform: uppercase; letter-spacing: 0.1875rem;
        margin-bottom: 4mm;
        text-align: center;
    }
    .step {
        display: flex; align-items: flex-start; gap: 3mm;
        margin-bottom: 3mm;
    }
    .step-num {
        width: 5mm; height: 5mm; border-radius: 50%;
        background: #4f46e5; color: white;
        font-size: 0.4375rem; font-weight: 900;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0; margin-top: 0.5mm;
    }
    .step-text { font-size: 0.53125rem; color: #374151; font-weight: 700; line-height: 1.4; }
    .step-sub  { font-size: 0.46875rem; color: #94a3b8; font-weight: 600; }

    .badge-row {
        display: flex; gap: 3mm; justify-content: center;
        flex-wrap: wrap; margin-bottom: 6mm;
    }
    .badge {
        background: #eef2ff; color: #4f46e5;
        border: 0.0625rem solid #c7d2fe;
        border-radius: 10mm;
        font-size: 0.4375rem; font-weight: 900;
        padding: 2mm 4mm;
        text-transform: uppercase; letter-spacing: 0.0625rem;
    }
    .footer {
        font-size: 0.4375rem; color: #cbd5e1; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.125rem;
        margin-top: 4mm;
    }
</style>
</head>
<body>
<div class="page">
    <!-- Corner Accents -->
    <div class="corner-tl"></div>
    <div class="corner-tr"></div>
    <div class="corner-bl"></div>
    <div class="corner-br"></div>

    <!-- School Logo -->
    <?php if ($logo_b64): ?>
    <div class="logo-ring">
        <img src="data:<?= $logo_mime ?>;base64,<?= $logo_b64 ?>">
    </div>
    <?php endif; ?>

    <div class="header-label">Attendance System</div>
    <div class="school-name"><?= htmlspecialchars($school_name) ?></div>
    <?php if ($school_address): ?><div class="school-sub"><?= htmlspecialchars($school_address) ?></div><?php endif; ?>

    <div class="divider"></div>

    <div class="scan-title">Staff Clock-In Portal</div>
    <div class="scan-sub">Scan with your phone camera to record attendance</div>

    <!-- QR Code -->
    <?php if ($qr_b64): ?>
    <div class="qr-outer">
        <img src="data:image/png;base64,<?= $qr_b64 ?>">
    </div>
    <?php else: ?>
    <div class="qr-outer" style="width:65mm;height:65mm;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:0.5rem;color:#94a3b8;">QR Code Unavailable</div>
    <?php endif; ?>

    <div class="url-box"><?= htmlspecialchars($base) ?></div>

    <!-- How to steps -->
    <div class="steps-box">
        <div class="steps-title">&#9654; How to Clock In</div>
        <div class="step">
            <div class="step-num">1</div>
            <div>
                <div class="step-text">Scan the QR code using your phone camera</div>
                <div class="step-sub">Point your camera at the code — no app needed</div>
            </div>
        </div>
        <div class="step">
            <div class="step-num">2</div>
            <div>
                <div class="step-text">Allow location access when prompted</div>
                <div class="step-sub">GPS is required to verify you are on campus</div>
            </div>
        </div>
        <div class="step">
            <div class="step-num">3</div>
            <div>
                <div class="step-text">Tap "Record My Attendance" on the page</div>
                <div class="step-sub">Your time and location are recorded instantly</div>
            </div>
        </div>
        <div class="step">
            <div class="step-num">4</div>
            <div>
                <div class="step-text">Scan again at end of day to clock out</div>
                <div class="step-sub">The same link handles both clock-in and clock-out</div>
            </div>
        </div>
    </div>

    <!-- Security Badges -->
    <div class="badge-row">
        <span class="badge">&#128512; GPS Verified</span>
        <span class="badge">&#128274; Geofenced</span>
        <span class="badge">&#9989; Auto Logged</span>
        <span class="badge">&#128203; Audit Trail</span>
    </div>

    <div class="footer">
        <?= htmlspecialchars($school_name) ?> &bull; Institutional Attendance System &bull; GPS Radius: <?= $allowed_radius_meters ?? 300 ?>m
        <?php if ($school_phone): ?> &bull; <?= htmlspecialchars($school_phone) ?><?php endif; ?>
    </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();
$allowed_radius_meters = getSystemSetting($conn, 'attendance_radius', '300');

$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_left'   => 0,
    'margin_right'  => 0,
    'margin_top'    => 0,
    'margin_bottom' => 0,
]);
$mpdf->WriteHTML($html);
$mpdf->Output('Staff_ClockIn_Poster.pdf', 'D');
