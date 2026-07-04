<?php

if (!function_exists('paymentReceiptFormatMethod')) {
function paymentReceiptFormatMethod($method) {
    $method = strtolower(trim((string)$method));
    if ($method === '') {
        return 'Cash';
    }

    $map = [
        'momo' => 'Mobile Money',
        'check' => 'Cheque',
        'cash' => 'Cash',
        'transfer' => 'Bank Transfer',
    ];

    return $map[$method] ?? ucwords(str_replace(['_', '-'], ' ', $method));
}
}

if (!function_exists('paymentReceiptNumberToWords')) {
function paymentReceiptNumberToWords($number) {
    $number = floor($number);

    $ones = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen', 19 => 'nineteen'
    ];
    $tens = [2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty', 6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety'];
    $scales = [1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand', 100 => 'hundred'];

    if ($number < 20) {
        return $ones[$number];
    }

    if ($number < 100) {
        $ten = intdiv($number, 10);
        $remainder = $number % 10;
        return $tens[$ten] . ($remainder ? '-' . $ones[$remainder] : '');
    }

    foreach ($scales as $scale => $label) {
        if ($number >= $scale) {
            $major = intdiv($number, $scale);
            $remainder = $number % $scale;
            $text = paymentReceiptNumberToWords($major) . ' ' . $label;
            if ($remainder > 0) {
                $joiner = $remainder < 100 ? ' and ' : ' ';
                $text .= $joiner . paymentReceiptNumberToWords($remainder);
            }
            return $text;
        }
    }

    return (string)$number;
}
}

if (!function_exists('paymentReceiptAmountToWords')) {
function paymentReceiptAmountToWords($amount) {
    $amount = round((float)$amount, 2);
    $cedis = (int)floor($amount);
    $pesewas = (int)round(($amount - $cedis) * 100);

    $words = ucfirst(paymentReceiptNumberToWords($cedis)) . ' Ghana cedi' . ($cedis === 1 ? '' : 's');
    if ($pesewas > 0) {
        $words .= ' and ' . paymentReceiptNumberToWords($pesewas) . ' pesewa' . ($pesewas === 1 ? '' : 's');
    }

    return $words . ' only';
}
}

if (!function_exists('getPaymentReceiptContext')) {
function getPaymentReceiptContext(mysqli $conn, int $payment_id, string $publicLogoPrefix = '../../../') {
    if ($payment_id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT p.*, s.first_name, s.last_name, s.class FROM payments p LEFT JOIN students s ON p.student_id = s.id WHERE p.id = ?");
    $stmt->bind_param('i', $payment_id);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payment) {
        return null;
    }

    $allocations = [];
    $allocation_stmt = $conn->prepare("SELECT pa.amount, f.name AS fee_name, sf.semester AS sf_term, sf.academic_year AS sf_academic_year FROM payment_allocations pa LEFT JOIN student_fees sf ON pa.student_fee_id = sf.id LEFT JOIN fees f ON sf.fee_id = f.id WHERE pa.payment_id = ? ORDER BY pa.id ASC");
    $allocation_stmt->bind_param('i', $payment_id);
    $allocation_stmt->execute();
    $allocation_result = $allocation_stmt->get_result();
    while ($row = $allocation_result->fetch_assoc()) {
        $allocations[] = $row;
    }
    $allocation_stmt->close();

    $outstanding = 0.0;
    $student_id = (int)($payment['student_id'] ?? 0);
    if ($student_id > 0) {
        $fees_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_due FROM student_fees WHERE student_id = ? AND status != 'cancelled'");
        $fees_stmt->bind_param('i', $student_id);
        $fees_stmt->execute();
        $total_due = (float)($fees_stmt->get_result()->fetch_assoc()['total_due'] ?? 0);
        $fees_stmt->close();

        $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as total_paid FROM payments WHERE student_id = ?");
        $paid_stmt->bind_param('i', $student_id);
        $paid_stmt->execute();
        $total_paid = (float)($paid_stmt->get_result()->fetch_assoc()['total_paid'] ?? 0);
        $paid_stmt->close();

        $outstanding = max(0, $total_due - $total_paid);
    }

    $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori International School');
    $school_address = getSystemSetting($conn, 'school_address', '');
    $school_phone = getSystemSetting($conn, 'school_phone', '');
    $school_email = getSystemSetting($conn, 'school_email', '');
    $logo_setting = ltrim((string)getSystemLogo($conn), '/');
    $public_logo_path = $publicLogoPrefix . $logo_setting;
    $absolute_logo_path = realpath(__DIR__ . '/../' . $logo_setting);
    if (!$absolute_logo_path) {
        $absolute_logo_path = realpath(__DIR__ . '/../assets/img/salba_logo.jpg');
    }
    if (!$absolute_logo_path) {
        $absolute_logo_path = __DIR__ . '/../assets/img/salba_logo.jpg';
    }

    $student_name = trim((string)($payment['first_name'] ?? '') . ' ' . (string)($payment['last_name'] ?? ''));
    $is_student_payment = $student_id > 0 && $student_name !== '';
    $recipient_name = $is_student_payment ? $student_name : trim((string)($payment['description'] ?? 'General Payment'));
    if ($recipient_name === '') {
        $recipient_name = 'General Payment';
    }

    $payment_timestamp = strtotime((string)$payment['payment_date']);
    $payment_date_display = $payment_timestamp ? date('F j, Y', $payment_timestamp) : 'N/A';
    $payment_time_display = $payment_timestamp ? date('g:i A', $payment_timestamp) : '';
    $amount = (float)($payment['amount'] ?? 0);
    $display_year = formatAcademicYearDisplay($conn, (string)($payment['academic_year'] ?? ''));

    return [
        'payment_id' => $payment_id,
        'payment' => $payment,
        'allocations' => $allocations,
        'outstanding' => $outstanding,
        'school_name' => $school_name,
        'school_address' => $school_address,
        'school_phone' => $school_phone,
        'school_email' => $school_email,
        'logo_public_path' => $public_logo_path,
        'logo_file_path' => $absolute_logo_path,
        'student_name' => $student_name,
        'student_class' => (string)($payment['class'] ?? ''),
        'recipient_name' => $recipient_name,
        'receipt_no' => trim((string)($payment['receipt_no'] ?? '')),
        'payment_method_label' => paymentReceiptFormatMethod($payment['payment_method'] ?? 'cash'),
        'payment_date_display' => $payment_date_display,
        'payment_time_display' => $payment_time_display,
        'semester_label' => trim((string)($payment['semester'] ?? '')),
        'academic_year_label' => $display_year,
        'amount' => $amount,
        'amount_display' => number_format($amount, 2),
        'amount_words' => paymentReceiptAmountToWords($amount),
        'description' => trim((string)($payment['description'] ?? '')),
        'is_student_payment' => $is_student_payment,
    ];
}
}

if (!function_exists('buildPaymentReceiptPdfHtml')) {
function buildPaymentReceiptPdfHtml(array $context) {
    $receipt_no     = htmlspecialchars((string)$context['receipt_no']);
    $school_name    = htmlspecialchars((string)$context['school_name']);
    $school_address = htmlspecialchars((string)$context['school_address']);
    $school_phone   = htmlspecialchars((string)$context['school_phone']);
    $school_email   = htmlspecialchars((string)$context['school_email']);
    $recipient_name = htmlspecialchars((string)$context['recipient_name']);
    $student_class  = htmlspecialchars((string)$context['student_class']);
    $method_label   = htmlspecialchars((string)$context['payment_method_label']);
    $date_display   = htmlspecialchars((string)$context['payment_date_display']);
    $semester_label = htmlspecialchars((string)$context['semester_label']);
    $year_label     = htmlspecialchars((string)$context['academic_year_label']);
    $amount_display = htmlspecialchars((string)$context['amount_display']);
    $description    = htmlspecialchars((string)$context['description']);
    $outstanding    = number_format((float)($context['outstanding'] ?? 0), 2);
    $is_student     = !empty($context['is_student_payment']);
    $subject_label  = $is_student ? 'Student Name' : 'Received From';
    $logo_file      = (string)($context['logo_file_path'] ?? '');

    $logo_tag = '';
    if ($logo_file !== '' && file_exists($logo_file)) {
        $logo_tag = '<img src="' . htmlspecialchars($logo_file) . '" width="50" height="50" style="display:block;" />';
    }

    $contact_parts = array_filter([$school_address, $school_phone, $school_email], fn($v) => $v !== '');
    $contact_line  = htmlspecialchars(implode(' | ', $contact_parts));

    $alloc_rows = '';
    foreach (($context['allocations'] ?? []) as $al) {
        $fn  = htmlspecialchars((string)($al['fee_name'] ?? 'Payment'));
        $rs  = htmlspecialchars((string)($al['sf_term'] ?? ''));
        $ry  = htmlspecialchars(formatAcademicYearDisplay($GLOBALS['conn'], (string)($al['sf_academic_year'] ?? '')));
        $ra  = number_format((float)($al['amount'] ?? 0), 2);
        $alloc_rows .= '<tr>
            <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9pt;">' . $fn . '</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9pt;text-align:center;">' . trim($rs . ($ry ? ' · ' . $ry : '')) . '</td>
            <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9pt;text-align:right;font-weight:700;">' . $ra . '</td>
        </tr>';
    }
    if ($alloc_rows === '') {
        $fb  = $description !== '' ? $description : 'Direct Fee Settlement';
        $period = trim($semester_label . ($year_label ? ' · ' . $year_label : ''));
        $alloc_rows = '<tr>
            <td style="padding:6px 8px;font-size:9pt;">' . $fb . '</td>
            <td style="padding:6px 8px;font-size:9pt;text-align:center;">' . $period . '</td>
            <td style="padding:6px 8px;font-size:9pt;text-align:right;font-weight:700;">' . $amount_display . '</td>
        </tr>';
    }

    $balance_row = '';
    if ($is_student) {
        $balance_row = '<tr>
            <td style="padding:8px 10px;font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;background:#fff1f2;color:#9f1239;">Outstanding</td>
            <td style="padding:8px 10px;font-size:14pt;font-weight:800;text-align:right;background:#fff1f2;color:#9f1239;">GHS ' . $outstanding . '</td>
        </tr>';
    }

    $sig_row = '<tr>
        <td width="50%" style="padding-top:24px;font-size:8pt;color:#64748b;text-transform:uppercase;letter-spacing:0.1em;border-top:1px solid #94a3b8;">Accounts Office</td>
        <td width="50%" style="padding-top:24px;font-size:8pt;color:#64748b;text-transform:uppercase;letter-spacing:0.1em;border-top:1px solid #94a3b8;text-align:right;">Parent / Guardian</td>
    </tr>';

    return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size:9pt; color:#1f2937; margin:8mm; }
    table { width:100%; border-collapse:collapse; }
</style>
</head>
<body>
<table style="margin-bottom:12px;">
    <tr>
        <td width="58" style="vertical-align:top;">' . $logo_tag . '</td>
        <td style="vertical-align:top;padding-left:8px;">
            <div style="font-size:13pt;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;font-family:Georgia,serif;">' . $school_name . '</div>
            ' . ($contact_line !== '' ? '<div style="font-size:8pt;color:#64748b;margin-top:3px;">' . $contact_line . '</div>' : '') . '
            <div style="font-size:8pt;font-weight:700;text-transform:uppercase;letter-spacing:0.2em;color:#0f766e;margin-top:4px;">Official Receipt</div>
        </td>
        <td width="140" style="vertical-align:top;text-align:right;">
            <div style="border:1px solid #cbd5e1;background:#f8fafc;padding:8px 10px;display:inline-block;min-width:120px;">
                <div style="font-size:7pt;letter-spacing:0.2em;text-transform:uppercase;color:#64748b;font-weight:700;">Receipt Number</div>
                <div style="font-size:13pt;font-weight:800;color:#0f172a;margin-top:4px;">' . $receipt_no . '</div>
            </div>
        </td>
    </tr>
</table>

<table style="border:1px solid #e5e7eb;margin-bottom:10px;">
    <tr>
        <td width="50%" style="padding:9px 10px;border-bottom:1px solid #e5e7eb;border-right:1px solid #e5e7eb;vertical-align:top;">
            <div style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;">' . htmlspecialchars($subject_label) . '</div>
            <div style="font-size:11pt;font-weight:700;color:#111827;">' . $recipient_name . '</div>
            ' . ($student_class !== '' ? '<div style="font-size:9pt;color:#475569;margin-top:2px;">Class: ' . $student_class . '</div>' : '') . '
        </td>
        <td width="50%" style="padding:9px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;">
            <div style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;">Payment Date</div>
            <div style="font-size:11pt;font-weight:700;color:#111827;">' . $date_display . '</div>
        </td>
    </tr>
    <tr>
        <td style="padding:9px 10px;border-right:1px solid #e5e7eb;vertical-align:top;">
            <div style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;">Payment Method</div>
            <div style="font-size:11pt;font-weight:700;color:#111827;">' . $method_label . '</div>
        </td>
        <td style="padding:9px 10px;vertical-align:top;">
            <div style="font-size:7pt;letter-spacing:0.14em;text-transform:uppercase;color:#64748b;font-weight:700;margin-bottom:4px;">Billing Period</div>
            <div style="font-size:11pt;font-weight:700;color:#111827;">' . ($semester_label !== '' ? $semester_label : 'N/A') . '</div>
            ' . ($year_label !== '' ? '<div style="font-size:9pt;color:#475569;margin-top:2px;">Academic Year: ' . $year_label . '</div>' : '') . '
        </td>
    </tr>
</table>

<div style="border:1px solid #d6cbb8;background:#fffaf1;padding:9px 12px;margin-bottom:10px;">
    <div style="font-size:18pt;font-weight:800;color:#14532d;">GHS ' . $amount_display . '</div>
</div>

<div style="font-size:8pt;font-weight:800;text-transform:uppercase;letter-spacing:0.22em;border-bottom:1px solid #d7dce2;padding-bottom:5px;margin-bottom:8px;">Payment Breakdown</div>

<table style="margin-bottom:10px;">
    <thead>
        <tr>
            <th style="background:#1f2937;color:#fff;font-size:8pt;text-align:left;padding:7px 8px;text-transform:uppercase;letter-spacing:0.1em;">Description</th>
            <th style="background:#1f2937;color:#fff;font-size:8pt;text-align:center;padding:7px 8px;text-transform:uppercase;letter-spacing:0.1em;">Semester / Year</th>
            <th style="background:#1f2937;color:#fff;font-size:8pt;text-align:right;padding:7px 8px;width:80px;text-transform:uppercase;letter-spacing:0.1em;">Amount</th>
        </tr>
    </thead>
    <tbody>' . $alloc_rows . '</tbody>
</table>

<table style="margin-bottom:12px;">' . $balance_row . '</table>

<table><tr>' . $sig_row . '</tr></table>
</body>
</html>';
}
}

if (!function_exists('buildPaymentReceiptHtml')) {
function buildPaymentReceiptHtml(array $context, array $options = []) {
    $interactive = !empty($options['interactive']);
    $autoprint = !empty($options['autoprint']);
    $download_url = (string)($options['download_url'] ?? '');
    $back_url = (string)($options['back_url'] ?? 'view_payments.php');
    $logo_src = (string)($options['logo_src'] ?? ($context['logo_public_path'] ?? ''));

    $receipt_no = htmlspecialchars((string)$context['receipt_no']);
    $school_name = htmlspecialchars((string)$context['school_name']);
    $school_address = htmlspecialchars((string)$context['school_address']);
    $school_phone = htmlspecialchars((string)$context['school_phone']);
    $school_email = htmlspecialchars((string)$context['school_email']);
    $recipient_name = htmlspecialchars((string)$context['recipient_name']);
    $student_class = htmlspecialchars((string)$context['student_class']);
    $payment_method_label = htmlspecialchars((string)$context['payment_method_label']);
    $payment_date_display = htmlspecialchars((string)$context['payment_date_display']);
    $payment_time_display = htmlspecialchars((string)$context['payment_time_display']);
    $semester_label = htmlspecialchars((string)$context['semester_label']);
    $academic_year_label = htmlspecialchars((string)$context['academic_year_label']);
    $amount_display = htmlspecialchars((string)$context['amount_display']);
    $description = htmlspecialchars((string)$context['description']);
    $outstanding = (float)($context['outstanding'] ?? 0);
    $outstanding_display = number_format($outstanding, 2);
    $is_student_payment = !empty($context['is_student_payment']);
    $subject_label = $is_student_payment ? 'Student Name' : 'Received From';

    $allocation_rows = '';
    foreach (($context['allocations'] ?? []) as $allocation) {
        $fee_name = htmlspecialchars((string)($allocation['fee_name'] ?? 'Payment Allocation'));
        $row_semester = htmlspecialchars((string)($allocation['sf_term'] ?? ''));
        $row_year = htmlspecialchars(formatAcademicYearDisplay($GLOBALS['conn'], (string)($allocation['sf_academic_year'] ?? '')));
        $row_amount = number_format((float)($allocation['amount'] ?? 0), 2);
        $allocation_rows .= '<tr><td>' . $fee_name . '</td><td class="center">' . trim($row_semester . ' ' . ($row_year !== '' ? '&middot; ' . $row_year : '')) . '</td><td class="right">' . $row_amount . '</td></tr>';
    }

    if ($allocation_rows === '') {
        $fallback_label = $description !== '' ? $description : 'Direct Fee Settlement';
        $allocation_rows = '<tr><td>' . $fallback_label . '</td><td class="center">' . trim($semester_label . ' ' . ($academic_year_label !== '' ? '&middot; ' . $academic_year_label : '')) . '</td><td class="right">' . $amount_display . '</td></tr>';
    }

    $contact_bits = [];
    if ($school_address !== '') {
        $contact_bits[] = $school_address;
    }
    if ($school_phone !== '') {
        $contact_bits[] = $school_phone;
    }
    if ($school_email !== '') {
        $contact_bits[] = $school_email;
    }
    $contact_line = implode(' &nbsp;&bull;&nbsp; ', $contact_bits);

    $toolbar_html = '';
    if ($interactive) {
        $download_button = '';
        if ($download_url !== '') {
            $download_button = '<a href="' . htmlspecialchars($download_url) . '" target="_blank" rel="noopener" class="toolbar-btn toolbar-btn-primary">Open PDF</a>';
        }

        $toolbar_html = '<div class="toolbar no-print"><div class="toolbar-inner"><a href="' . htmlspecialchars($back_url) . '" class="toolbar-link">Back to Payment Ledger</a><div class="toolbar-actions">' . $download_button . '<button type="button" class="toolbar-btn" onclick="window.print()">Print Receipt</button></div></div></div>';
    }

    $autoprint_script = '';
    if ($interactive && $autoprint) {
        $autoprint_script = '<script>window.addEventListener("load", function () { window.print(); });</script>';
    }

    $logo_html = '';
    if ($logo_src !== '') {
        $logo_html = '<img src="' . htmlspecialchars($logo_src) . '" alt="School Logo" class="brand-logo">';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt | #' . $receipt_no . '</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #ece8df; color: #1f2937; font-family: "Segoe UI", Arial, sans-serif; }
        .toolbar { background: #14213d; padding: 14px 18px; position: sticky; top: 0; z-index: 20; }
        .toolbar-inner { max-width: 920px; margin: 0 auto; display: table; width: 100%; }
        .toolbar-link, .toolbar-btn { display: inline-block; text-decoration: none; border-radius: 999px; padding: 10px 16px; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .toolbar-link { color: #dbeafe; }
        .toolbar-actions { display: table-cell; text-align: right; vertical-align: middle; }
        .toolbar-inner > a { display: table-cell; vertical-align: middle; }
        .toolbar-btn { background: #ffffff; color: #0f172a; border: 1px solid #cbd5e1; margin-left: 8px; }
        .toolbar-btn-primary { background: #0f766e; color: #ffffff; border-color: #0f766e; }
        .page-shell { padding: 18px 10px 26px; }
        .receipt-sheet { width: 105mm; min-height: 148mm; margin: 0 auto; background: #fffdf9; border: 1px solid #d9d1c3; box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12); position: relative; }
        .receipt-sheet::before { content: ""; position: absolute; inset: 12px; border: 1px solid #ebe2d3; pointer-events: none; }
        .receipt-body { position: relative; padding: 18px 16px 16px; }
        .top-strip { height: 8px; background: linear-gradient(90deg, #0f766e 0%, #14532d 100%); margin: -18px -16px 14px; }
        .brand-table, .summary-table, .meta-table, .signature-table { width: 100%; border-collapse: collapse; }
        .brand-logo { width: 52px; height: 52px; object-fit: contain; display: block; }
        .brand-table td { vertical-align: top; }
        .brand-logo-cell { width: 62px; }
        .school-name { font-family: Georgia, "Times New Roman", serif; font-size: 16px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #0f172a; margin: 0; }
        .school-contact { font-size: 10px; color: #64748b; margin-top: 4px; }
        .receipt-badge { border: 1px solid #cbd5e1; background: #f8fafc; padding: 10px 12px; text-align: right; min-width: 128px; }
        .receipt-badge-label { font-size: 8px; letter-spacing: 0.22em; text-transform: uppercase; color: #64748b; font-weight: 700; }
        .receipt-badge-value { font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 5px; }
        .meta-card { margin-top: 14px; border: 1px solid #e5e7eb; }
        .meta-table td { width: 50%; padding: 9px 10px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .meta-table tr:last-child td { border-bottom: none; }
        .meta-table td:nth-child(odd) { border-right: 1px solid #e5e7eb; }
        .meta-label { font-size: 8px; letter-spacing: 0.16em; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 5px; }
        .meta-value { font-size: 12px; font-weight: 700; color: #111827; }
        .meta-subvalue { font-size: 10px; color: #475569; margin-top: 3px; }
        .section-title { margin: 14px 0 8px; padding-bottom: 6px; border-bottom: 1px solid #d7dce2; font-size: 9px; letter-spacing: 0.22em; text-transform: uppercase; color: #0f172a; font-weight: 800; }
        .amount-box { border: 1px solid #d6cbb8; background: #fffaf1; padding: 10px 12px; }
        .amount-value { font-size: 18px; font-weight: 800; color: #14532d; }
        .alloc-table { width: 100%; border-collapse: collapse; }
        .alloc-table th { background: #1f2937; color: #ffffff; font-size: 8px; text-transform: uppercase; letter-spacing: 0.12em; padding: 7px 8px; }
        .alloc-table td { padding: 8px; border-bottom: 1px solid #eceff3; font-size: 10px; }
        .alloc-table .center { text-align: center; }
        .alloc-table .right { text-align: right; font-weight: 700; }
        .summary-table { margin-top: 10px; }
        .summary-table td { padding: 10px 12px; }
        .summary-label { background: #0f172a; color: #ffffff; font-size: 9px; text-transform: uppercase; letter-spacing: 0.16em; font-weight: 700; }
        .summary-value { background: #0f172a; color: #ffffff; text-align: right; font-size: 16px; font-weight: 800; }
        .balance-row td { background: #fff4f2; color: #9f1239; border-top: 8px solid #fffdf9; }
        .signature-table { margin-top: 18px; }
        .signature-table td { width: 50%; padding-top: 18px; font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.1em; }
        .signature-line { border-top: 1px solid #94a3b8; padding-top: 6px; }
        @media print {
            @page { size: A6 portrait; margin: 0; }
            body { background: #ffffff; }
            .no-print { display: none !important; }
            .page-shell { padding: 0; }
            .receipt-sheet { box-shadow: none; border: none; width: 105mm; min-height: 148mm; }
            .receipt-sheet::before { inset: 0; }
        }
    </style>
</head>
<body>
    ' . $toolbar_html . '
    <div class="page-shell">
        <div class="receipt-sheet">
            <div class="receipt-body">
                <div class="top-strip"></div>

                <table class="brand-table">
                    <tr>
                        <td class="brand-logo-cell">' . $logo_html . '</td>
                        <td>
                            <h1 class="school-name">' . $school_name . '</h1>
                            ' . ($contact_line !== '' ? '<div class="school-contact">' . $contact_line . '</div>' : '') . '
                        </td>
                        <td style="width: 180px; text-align: right;">
                            <div class="receipt-badge">
                                <div class="receipt-badge-label">Receipt Number</div>
                                <div class="receipt-badge-value">' . $receipt_no . '</div>
                            </div>
                        </td>
                    </tr>
                </table>

                <div class="meta-card">
                    <table class="meta-table">
                        <tr>
                            <td>
                                <div class="meta-label">' . htmlspecialchars($subject_label) . '</div>
                                <div class="meta-value">' . $recipient_name . '</div>
                                ' . ($student_class !== '' ? '<div class="meta-subvalue">Class: ' . $student_class . '</div>' : '') . '
                            </td>
                            <td>
                                <div class="meta-label">Payment Date</div>
                                <div class="meta-value">' . $payment_date_display . '</div>
                                ' . ($payment_time_display !== '' ? '<div class="meta-subvalue">Time: ' . $payment_time_display . '</div>' : '') . '
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="meta-label">Payment Method</div>
                                <div class="meta-value">' . $payment_method_label . '</div>
                            </td>
                            <td>
                                <div class="meta-label">Billing Period</div>
                                <div class="meta-value">' . ($semester_label !== '' ? $semester_label : 'Not Specified') . '</div>
                                ' . ($academic_year_label !== '' ? '<div class="meta-subvalue">Academic Year: ' . $academic_year_label . '</div>' : '') . '
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="amount-box">
                    <div class="amount-value">GHS ' . $amount_display . '</div>
                </div>

                <div class="section-title">Payment Breakdown</div>
                <table class="alloc-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Description</th>
                            <th class="center">Semester / Year</th>
                            <th style="text-align:right; width:120px;">Amount (GHS)</th>
                        </tr>
                    </thead>
                    <tbody>' . $allocation_rows . '</tbody>
                </table>

                <table class="summary-table">
                    ' . ($is_student_payment ? '<tr class="balance-row"><td class="summary-label" style="background:#fff4f2;color:#9f1239;">Outstanding</td><td class="summary-value" style="background:#fff4f2;color:#9f1239;font-size:16px;">GHS ' . $outstanding_display . '</td></tr>' : '') . '
                </table>

                <table class="signature-table">
                    <tr>
                        <td><div class="signature-line">Accounts Office</div></td>
                        <td style="text-align:right;"><div class="signature-line">Parent / Guardian</div></td>
                    </tr>
                </table>

            </div>
        </div>
    </div>
    ' . $autoprint_script . '
</body>
</html>';
}
}