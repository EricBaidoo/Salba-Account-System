<?php
/**
 * Attendance Check-In Poster — mPDF Download
 */
require_once __DIR__ . '/vendor/autoload.php';

// ── Logo ─────────────────────────────────────────────────────────────
$logo_path = __DIR__ . '/assets/img/salba_logo.jpg';
$logo_src  = '';
if (file_exists($logo_path)) {
    $ext  = strtolower(pathinfo($logo_path, PATHINFO_EXTENSION));
    $mime = match($ext) { 'png' => 'image/png', 'gif' => 'image/gif', default => 'image/jpeg' };
    $logo_src = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logo_path));
}

// ── QR Code (use 400x400 source — plenty for mPDF at 150mm) ─────────
$check_in_url = 'https://smis.e7techlab.com/pages/teacher/check_in';
$qr_api = 'https://api.qrserver.com/v1/create-qr-code/'
    . '?size=400x400'
    . '&data=' . urlencode($check_in_url)
    . '&color=1e1b6e&bgcolor=ffffff&margin=6&qzone=1&format=png&ecc=H';

// Fetch with timeout
$ctx    = stream_context_create(['http' => ['timeout' => 10]]);
$qr_raw = @file_get_contents($qr_api, false, $ctx);
$qr_src = $qr_raw ? 'data:image/png;base64,' . base64_encode($qr_raw) : '';

// ── HTML ──────────────────────────────────────────────────────────────
$html = '
<html>
<head>
<style>
body {
    font-family: DejaVu Sans, sans-serif;
    margin: 0; padding: 0;
    text-align: center;
    color: #0f172a;
}
table { border-collapse: collapse; }
.top-rule  { width: 100%; height: 8mm; background: #4338ca; }
.bot-rule  { width: 100%; height: 6mm; background: #059669; }
.section   { width: 100%; padding: 0 14mm; }
.lbl {
    font-size: 7pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.1875rem;
    color: #6366f1;
}
.sname {
    font-size: 20pt; font-weight: bold;
    text-transform: uppercase;
    color: #0f172a;
    line-height: 1.1;
}
.stag {
    font-size: 7.5pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.1875rem;
    color: #94a3b8;
}
.rule-line {
    width: 18mm; height: 0.125rem;
    background: #6366f1;
    margin: 0 auto;
}
.act-title {
    font-size: 9pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.25rem;
    color: #4f46e5;
}
.qr-border {
    border: 0.3125rem solid #4338ca;
    border-radius: 0.5rem;
    padding: 0.3125rem;
    display: inline-block;
    background: #fff;
}
.hint {
    font-size: 8pt; font-weight: bold;
    text-transform: uppercase; letter-spacing: 0.25rem;
    color: #6366f1;
}
.footer {
    font-size: 6pt;
    color: #cbd5e1;
    text-transform: uppercase;
    letter-spacing: 0.125rem;
}
</style>
</head>
<body>

<!-- TOP BAR -->
<table width="100%"><tr><td class="top-rule"></td></tr></table>

<!-- LOGO -->
<br>
' . ($logo_src ? '<img src="' . $logo_src . '" width="18mm" height="18mm" style="border-radius:50%;border:0.1875rem solid #e0e7ff;">' : '') . '
<br><br>

<!-- SCHOOL NAME -->
<p class="lbl">Salba Montessori School</p>
<br>
<p class="sname">Staff Attendance Check-In</p>
<br>
<p class="stag">Scan &bull; Verify &bull; Done</p>
<br>

<!-- DIVIDER -->
<table width="100%"><tr><td align="center"><table width="18mm"><tr><td style="height:0.125rem;background:#6366f1;"></td></tr></table></td></tr></table>
<br>

<!-- ACTION TITLE -->
<p class="act-title">Scan to Clock In / Out</p>
<br>

<!-- QR CODE -->
' . ($qr_src
    ? '<table width="100%"><tr><td align="center">
        <table style="border:0.3125rem solid #4338ca;border-radius:0.5rem;padding:0.3125rem;background:#fff;">
            <tr><td><img src="' . $qr_src . '" width="145mm" height="145mm"></td></tr>
        </table>
      </td></tr></table>'
    : '<p style="color:#ef4444;font-size:9pt;">QR code unavailable. Check server internet access.</p>'
) . '
<br>

<!-- HINT -->
<p class="hint">Point phone camera at code</p>
<br>

<!-- FOOTER -->
<p class="footer">Salba Montessori &bull; Institutional Attendance System &bull; 2026</p>
<br>

<!-- BOTTOM BAR -->
<table width="100%"><tr><td class="bot-rule"></td></tr></table>

</body>
</html>';

// ── mPDF output ───────────────────────────────────────────────────────
$mpdf = new \Mpdf\Mpdf([
    'mode'          => 'utf-8',
    'format'        => 'A4',
    'margin_top'    => 0,
    'margin_bottom' => 0,
    'margin_left'   => 0,
    'margin_right'  => 0,
]);

$mpdf->SetTitle('Staff Attendance Poster');
$mpdf->WriteHTML($html);
$mpdf->Output('Attendance_Poster.pdf', 'D');
