<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/system_settings.php';
require_once __DIR__ . '/term_helpers.php';
require_once __DIR__ . '/student_balance_functions.php';

if (!function_exists('getDefaultTermInvoiceSettings')) {
function getDefaultTermInvoiceSettings() {
    return [
        'payment_plan' => [
            ['name' => 'First Installment', 'percent' => 50, 'due_date' => '12 Sept'],
            ['name' => 'Second Installment', 'percent' => 30, 'due_date' => '1st Oct'],
            ['name' => 'Final Installment', 'percent' => 20, 'due_date' => '3rd Nov'],
        ],
        'payment_modes' => [
            'bank' => [
                'title' => 'BANK DEPOSIT/TRANSFER',
                'account_name' => 'SALBA MONTESSORI INTERNATIONAL SCHOOL',
                'account_number' => '0010223853018',
                'bank_name' => 'OMNI BSIC - SPINTEX BRANCH',
            ],
            'momo' => [
                'title' => 'MOBILE MONEY (MOMO)',
                'number' => '0598872309',
                'name' => 'SALBA MONTESSORI INTERNATIONAL SCHOOL',
            ],
            'payment_reference' => "Please include the student's name and class as reference for all payments.",
        ],
        'notes' => [
            'All outstanding fees must be cleared before school reopens.',
            'All fees must be paid before the end of the semester.',
            'Please ensure all items are provided on time.',
            'Feeding fee is strictly weekly, monthly, or termly (NO DAILY FEEDING FEE).',
            'For any queries, contact the school administration.',
        ],
    ];
}
}

if (!function_exists('getSchoolLogoDataUri')) {
function getSchoolLogoDataUri() {
    static $data_uri = null;

    if ($data_uri !== null) {
        return $data_uri;
    }

    $logo_path = __DIR__ . '/../img/salba_logo.jpg';
    if (!file_exists($logo_path)) {
        $data_uri = '';
        return $data_uri;
    }

    $mime_type = function_exists('mime_content_type') ? mime_content_type($logo_path) : 'image/jpeg';
    $contents = file_get_contents($logo_path);
    if ($contents === false || $contents === '') {
        $data_uri = '';
        return $data_uri;
    }

    $data_uri = 'data:' . $mime_type . ';base64,' . base64_encode($contents);
    return $data_uri;
}
}

if (!function_exists('normalizeAcademicYearForKey')) {
function normalizeAcademicYearForKey($academic_year) {
    $year = trim((string)$academic_year);
    if ($year === '') {
        return '';
    }

    $parts = explode('/', $year);
    if (count($parts) !== 2) {
        return $year;
    }

    $start = trim($parts[0]);
    $end = trim($parts[1]);

    if (!preg_match('/^\d{4}$/', $start)) {
        return $year;
    }

    if (preg_match('/^\d{2}$/', $end)) {
        $century = substr($start, 0, 2);
        $end = $century . $end;
    }

    if (!preg_match('/^\d{4}$/', $end)) {
        return $year;
    }

    return $start . '/' . $end;
}
}

if (!function_exists('normalizeTermForKey')) {
function normalizeTermForKey($semester) {
    return trim((string)$semester);
}
}

if (!function_exists('getTermInvoiceSettingsKey')) {
function getTermInvoiceSettingsKey($semester, $academic_year) {
    $normalized_term = normalizeTermForKey($semester);
    $normalized_year = normalizeAcademicYearForKey($academic_year);
    $term_slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $normalized_term));
    $year_slug = preg_replace('/[^A-Za-z0-9]+/', '_', $normalized_year);
    return 'term_invoice_settings_' . trim($term_slug, '_') . '_' . trim($year_slug, '_');
}
}

if (!function_exists('getTermInvoiceSettings')) {
function getTermInvoiceSettings($conn, $semester, $academic_year) {
    $defaults = getDefaultTermInvoiceSettings();
    $normalized_term = normalizeTermForKey($semester);
    $normalized_year = normalizeAcademicYearForKey($academic_year);

    $candidate_keys = [
        getTermInvoiceSettingsKey($normalized_term, $normalized_year),
        getTermInvoiceSettingsKey($normalized_term, $academic_year),
        getTermInvoiceSettingsKey($semester, $normalized_year),
        getTermInvoiceSettingsKey($semester, $academic_year),
    ];
    $candidate_keys = array_values(array_unique($candidate_keys));

    $raw = '';
    foreach ($candidate_keys as $candidate_key) {
        $raw = getSystemSetting($conn, $candidate_key, '');
        if (is_string($raw) && trim($raw) !== '') {
            break;
        }
    }

    if (!is_string($raw) || trim($raw) === '') {
        return $defaults;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $defaults;
    }

    $settings = $defaults;

    if (isset($decoded['payment_plan']) && is_array($decoded['payment_plan'])) {
        $settings['payment_plan'] = [];
        foreach ($decoded['payment_plan'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $settings['payment_plan'][] = [
                'name' => trim((string)($row['name'] ?? 'Installment')),
                'percent' => floatval($row['percent'] ?? 0),
                'due_date' => trim((string)($row['due_date'] ?? '')),
            ];
        }
        if (empty($settings['payment_plan'])) {
            $settings['payment_plan'] = $defaults['payment_plan'];
        }
    }

    if (isset($decoded['payment_modes']) && is_array($decoded['payment_modes'])) {
        $settings['payment_modes']['bank']['title'] = trim((string)($decoded['payment_modes']['bank']['title'] ?? $defaults['payment_modes']['bank']['title']));
        $settings['payment_modes']['bank']['account_name'] = trim((string)($decoded['payment_modes']['bank']['account_name'] ?? $defaults['payment_modes']['bank']['account_name']));
        $settings['payment_modes']['bank']['account_number'] = trim((string)($decoded['payment_modes']['bank']['account_number'] ?? $defaults['payment_modes']['bank']['account_number']));
        $settings['payment_modes']['bank']['bank_name'] = trim((string)($decoded['payment_modes']['bank']['bank_name'] ?? $defaults['payment_modes']['bank']['bank_name']));

        $settings['payment_modes']['momo']['title'] = trim((string)($decoded['payment_modes']['momo']['title'] ?? $defaults['payment_modes']['momo']['title']));
        $settings['payment_modes']['momo']['number'] = trim((string)($decoded['payment_modes']['momo']['number'] ?? $defaults['payment_modes']['momo']['number']));
        $settings['payment_modes']['momo']['name'] = trim((string)($decoded['payment_modes']['momo']['name'] ?? $defaults['payment_modes']['momo']['name']));

        $settings['payment_modes']['payment_reference'] = trim((string)($decoded['payment_modes']['payment_reference'] ?? $defaults['payment_modes']['payment_reference']));
    }

    if (isset($decoded['notes']) && is_array($decoded['notes'])) {
        $notes = [];
        foreach ($decoded['notes'] as $note) {
            $txt = trim((string)$note);
            if ($txt !== '') {
                $notes[] = $txt;
            }
        }
        if (!empty($notes)) {
            $settings['notes'] = $notes;
        }
    }

    return $settings;
}
}

if (!function_exists('resolveTermInvoiceContext')) {
function resolveTermInvoiceContext($conn, array $criteria = []) {
    $invoice_id = intval($criteria['invoice_id'] ?? 0);
    $student_id = intval($criteria['student_id'] ?? 0);
    $semester = trim((string)($criteria['semester'] ?? ''));
    $academic_year = trim((string)($criteria['academic_year'] ?? ''));

    if ($semester === '') {
        $semester = null;
    }

    if ($academic_year === '') {
        $academic_year = null;
    }

    if ($academic_year === null && $semester !== null) {
        $academic_year = getAcademicYear($conn);
    }

    $invoice = null;

    if ($invoice_id > 0) {
        $sql = "SELECT ti.id AS invoice_id, ti.student_id, ti.semester, ti.academic_year, ti.generated_at,
                       s.first_name, s.last_name, s.class, s.status AS student_status
                FROM term_invoices ti
                LEFT JOIN students s ON ti.student_id = s.id
                WHERE ti.id = ?
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $invoice_id);
        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$invoice && $student_id > 0 && $semester !== null) {
        if ($academic_year !== null) {
            $sql = "SELECT ti.id AS invoice_id, ti.student_id, ti.semester, ti.academic_year, ti.generated_at,
                           s.first_name, s.last_name, s.class, s.status AS student_status
                    FROM term_invoices ti
                    LEFT JOIN students s ON ti.student_id = s.id
                    WHERE ti.student_id = ? AND ti.semester = ? AND (ti.academic_year = ? OR ti.academic_year IS NULL)
                    ORDER BY ti.id DESC
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('iss', $student_id, $semester, $academic_year);
        } else {
            $sql = "SELECT ti.id AS invoice_id, ti.student_id, ti.semester, ti.academic_year, ti.generated_at,
                           s.first_name, s.last_name, s.class, s.status AS student_status
                    FROM term_invoices ti
                    LEFT JOIN students s ON ti.student_id = s.id
                    WHERE ti.student_id = ? AND ti.semester = ?
                    ORDER BY ti.id DESC
                    LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('is', $student_id, $semester);
        }

        $stmt->execute();
        $invoice = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$invoice && $student_id > 0) {
        $sql = "SELECT id AS student_id, first_name, last_name, class, status AS student_status FROM students WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student) {
            $invoice = [
                'invoice_id' => null,
                'student_id' => intval($student['student_id']),
                'semester' => $semester,
                'academic_year' => $academic_year,
                'generated_at' => null,
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'class' => $student['class'],
                'student_status' => $student['student_status'],
            ];
        }
    }

    if (!$invoice) {
        return null;
    }

    $student_id = intval($invoice['student_id'] ?? 0);
    $semester = trim((string)($invoice['semester'] ?? $semester));
    $academic_year = trim((string)($invoice['academic_year'] ?? $academic_year));

    if ($semester === '') {
        $semester = trim((string)($criteria['semester'] ?? ''));
    }
    if ($academic_year === '') {
        $academic_year = trim((string)($criteria['academic_year'] ?? ''));
    }
    if ($academic_year === '') {
        $academic_year = getAcademicYear($conn);
    }

    if ($student_id <= 0 || $semester === '' || $academic_year === '') {
        return null;
    }

    ensureArrearsAssignment($conn, $student_id, $semester, $academic_year);

    $student_balance = getStudentBalance($conn, $student_id, $semester, $academic_year);
    if (!$student_balance) {
        return null;
    }

    $term_fees = getStudentTermFees($conn, $student_id, $semester, $academic_year);
    $payment_history = getStudentPaymentHistory($conn, $student_id, $semester, $academic_year);

    return [
        'invoice' => $invoice,
        'student_balance' => $student_balance,
        'term_fees' => $term_fees,
        'payment_history' => $payment_history,
        'school_name' => getSystemSetting($conn, 'school_name', 'Salba Montessori'),
        'display_academic_year' => formatAcademicYearDisplay($conn, $academic_year),
        'semester' => $semester,
        'academic_year' => $academic_year,
    ];
}
}

if (!function_exists('buildTermInvoiceHtml')) {
function buildTermInvoiceHtml(array $context, array $options = [], $conn = null) {
    $mode = $options['mode'] ?? 'web';
    $invoice = $context['invoice'];
    $student_balance = $context['student_balance'];
    $term_fees = $context['term_fees'];
    $payment_history = $context['payment_history'];
    $school_name = $context['school_name'];
    $display_academic_year = $context['display_academic_year'];
    $semester = $context['semester'];
    $academic_year = $context['academic_year'];

    $invoice_id = $invoice['invoice_id'] ? intval($invoice['invoice_id']) : 0;
    $invoice_code = $invoice_id > 0 ? 'TI-' . str_pad((string)$invoice_id, 6, '0', STR_PAD_LEFT) : 'DRAFT';
    $student_name = trim((string)($student_balance['student_name'] ?? trim(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))));
    $student_class = $student_balance['class'] ?? ($invoice['class'] ?? 'N/A');
    $student_status = $student_balance['student_status'] ?? ($invoice['student_status'] ?? 'active');
    $generated_at = $invoice['generated_at'] ? date('M j, Y g:i A', strtotime($invoice['generated_at'])) : date('M j, Y g:i A');
    $total_fees = (float)($student_balance['total_fees'] ?? 0);
    $total_payments = (float)($student_balance['total_payments'] ?? 0);
    $arrears = (float)($student_balance['arrears'] ?? 0);
    $balance = (float)($student_balance['net_balance'] ?? 0);
    $balance_class = $balance > 0 ? 'danger' : 'success';

    $parts = explode('/', (string)$academic_year);
    $start_year = intval($parts[0] ?? date('Y'));
    $end_part = $parts[1] ?? (string)($start_year + 1);
    if (strlen((string)$end_part) === 2) {
        $century = substr((string)$start_year, 0, 2);
        $end_year = intval($century . $end_part);
    } else {
        $end_year = intval($end_part);
    }
    $year_code = substr((string)$start_year, -2) . substr((string)$end_year, -2);
    $bill_no = strtoupper(substr((string)$semester, 0, 1)) . $year_code . str_pad((string)intval($student_balance['student_id']), 4, '0', STR_PAD_LEFT);
    $installment_50 = $total_fees * 0.50;
    $installment_30 = $total_fees * 0.30;
    $installment_20 = $total_fees * 0.20;
    if ($conn) {
        $invoice_settings = getTermInvoiceSettings($conn, $semester, $academic_year);
    } else {
        $invoice_settings = getDefaultTermInvoiceSettings();
    }
    $bank = $invoice_settings['payment_modes']['bank'];
    $momo = $invoice_settings['payment_modes']['momo'];

    if ($mode === 'pdf') {
        $logo_data_uri = getSchoolLogoDataUri();
        $pdf_html = '<html><head><meta charset="UTF-8"></head><body>';

        $pdf_html .= '<div class="bill-header">'
            . ($logo_data_uri !== '' ? '<img class="bill-logo" src="' . htmlspecialchars($logo_data_uri) . '" alt="School Logo">' : '')
            . '<h1>SALBA MONTESSORI SCHOOL</h1>'
            . '<p class="tagline">Surge illuminare</p>'
            . '<p class="contact">GC-051-0961 | Tel: 059 887 2309 | Email: info@salbamontessori.edu.gh</p>'
            . '</div>';

        $pdf_html .= '<div class="bill-title">SCHOOL FEES BILL - ' . htmlspecialchars(strtoupper((string)$semester)) . '</div>';

        $pdf_html .= '<table class="student-details-table">'
            . '<tr>'
            . '<td><span class="label">STUDENT NAME:</span> ' . htmlspecialchars(strtoupper($student_name)) . '</td>'
            . '<td><span class="label">CLASS:</span> ' . htmlspecialchars(strtoupper((string)$student_class)) . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td><span class="label">TERM:</span> ' . htmlspecialchars(strtoupper((string)$semester)) . '</td>'
            . '<td><span class="label">ACADEMIC YEAR:</span> ' . htmlspecialchars((string)$display_academic_year) . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td><span class="label">BILL NO:</span> ' . htmlspecialchars($bill_no) . '</td>'
            . '<td><span class="label">DATE ISSUED:</span> ' . htmlspecialchars(date('d M, Y')) . '</td>'
            . '</tr>'
            . '</table>';

        $pdf_html .= '<table class="fee-table"><thead><tr>'
            . '<th class="w-72">FEE DESCRIPTION</th>'
            . '<th class="w-28">AMOUNT (GH₵)</th>'
            . '</tr></thead><tbody>';

        if (!empty($term_fees)) {
            foreach ($term_fees as $fee) {
                $pdf_html .= '<tr>'
                    . '<td>' . htmlspecialchars(strtoupper((string)($fee['fee_name'] ?? ''))) . '</td>'
                    . '<td>' . number_format((float)($fee['amount'] ?? 0), 2) . '</td>'
                    . '</tr>';
            }
        } else {
            $pdf_html .= '<tr><td colspan="2">No fee assignments found for this semester.</td></tr>';
        }

        $pdf_html .= '</tbody></table>';

        $pdf_html .= '<table class="totals-table"><tr>'
            . '<td class="label">TOTAL:</td>'
            . '<td class="value">GH₵ ' . number_format($total_fees, 2) . '</td>'
            . '</tr></table>';

        $pdf_html .= '<div class="section"><h3>PAYMENT PLAN</h3>'
            . '<table><thead><tr>'
            . '<th class="w-40-pct">INSTALLMENT</th>'
            . '<th class="w-10-pct">%</th>'
            . '<th class="w-25-pct">AMOUNT (GHS)</th>'
            . '<th class="w-25-pct">DUE DATE</th>'
            . '</tr></thead><tbody>';

        foreach ($invoice_settings['payment_plan'] as $plan_row) {
            $percent = floatval($plan_row['percent'] ?? 0);
            $amount = ($total_fees * $percent) / 100;
            $pdf_html .= '<tr>'
                . '<td>' . htmlspecialchars((string)($plan_row['name'] ?? 'Installment')) . '</td>'
                . '<td>' . number_format($percent, 0) . '%</td>'
                . '<td>' . number_format($amount, 2) . '</td>'
                . '<td>' . htmlspecialchars((string)($plan_row['due_date'] ?? '')) . '</td>'
                . '</tr>';
        }

        $pdf_html .= '</tbody></table></div>';

        $bank = $invoice_settings['payment_modes']['bank'];
        $momo = $invoice_settings['payment_modes']['momo'];
        $pdf_html .= '<div class="section"><h3>PAYMENT MODES</h3>'
            . '<table class="payment-modes"><tr>'
            . '<td>'
            . '<strong>' . htmlspecialchars((string)$bank['title']) . '</strong><br>'
            . 'ACCOUNT NAME: ' . htmlspecialchars((string)$bank['account_name']) . '<br>'
            . 'ACCOUNT NUMBER: ' . htmlspecialchars((string)$bank['account_number']) . '<br>'
            . 'BANK NAME: ' . htmlspecialchars((string)$bank['bank_name'])
            . '</td>'
            . '<td>'
            . '<strong>' . htmlspecialchars((string)$momo['title']) . '</strong><br>'
            . 'NUMBER: ' . htmlspecialchars((string)$momo['number']) . '<br>'
            . 'NAME: ' . htmlspecialchars((string)$momo['name'])
            . '</td>'
            . '</tr></table>'
            . '<p>' . htmlspecialchars((string)$invoice_settings['payment_modes']['payment_reference']) . '</p>'
            . '</div>';

        $pdf_html .= '<div class="section"><h3>NOTE:</h3><ol class="notes">';
        foreach ($invoice_settings['notes'] as $note_item) {
            $pdf_html .= '<li>' . htmlspecialchars((string)$note_item) . '</li>';
        }
        $pdf_html .= '</ol></div>';

        $pdf_html .= '</body></html>';

        return $pdf_html;
    }

    $base_url = 'student_balance_details.php?id=' . intval($student_balance['student_id']) . '&semester=' . urlencode((string)$semester) . '&academic_year=' . urlencode((string)$academic_year);
    if ($invoice_id > 0) {
        $download_url = 'download_term_invoice.php?id=' . intval($invoice_id);
    } else {
        $download_url = 'download_term_invoice.php?student_id=' . intval($student_balance['student_id']) . '&semester=' . urlencode((string)$semester) . '&academic_year=' . urlencode((string)$academic_year);
    }

    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($invoice_code) . ' - ' . htmlspecialchars($student_name) . '</title>
    
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-top">
            <div>
                <div class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-gray-200 text-gray-700">Semester Invoice</div>
                <h1 class="school">' . htmlspecialchars($school_name) . '</h1>
                <p class="subtitle">Student billing statement for semester-based fees and payments.</p>
            </div>
            <div class="meta">
                <div><strong>Invoice No:</strong> ' . htmlspecialchars($invoice_code) . '</div>
                <div><strong>Generated:</strong> ' . htmlspecialchars($generated_at) . '</div>
                <div><strong>Semester:</strong> ' . htmlspecialchars((string)$semester) . '</div>
                <div><strong>Academic Year:</strong> ' . htmlspecialchars((string)$display_academic_year) . '</div>
            </div>
        </div>
    </div>
    <div class="content">';

    if ($mode === 'web') {
        $html .= '<div class="toolbar">
            <a class="px-3 py-2 rounded font-medium bg-gray-500 text-white hover:bg-gray-600" href="view_term_bills.php">Back to Bills</a>
            <a class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" href="' . htmlspecialchars($download_url) . '" target="_blank">Download PDF</a>
            <a class="px-3 py-2 rounded font-medium bg-green-600 text-white hover:bg-green-700" href="' . htmlspecialchars($base_url) . '">Open Student Balance</a>
        </div>';
    }

    $html .= '<div class="student-card">
        <div class="info-box">
            <div class="info-label">Student Name</div>
            <div class="info-value">' . htmlspecialchars($student_name) . '</div>
        </div>
        <div class="info-box">
            <div class="info-label">Class</div>
            <div class="info-value">' . htmlspecialchars((string)$student_class) . '</div>
        </div>
        <div class="info-box">
            <div class="info-label">Status</div>
            <div class="info-value ' . ($student_status === 'inactive' ? 'status-inactive' : 'status-active') . '">' . htmlspecialchars(ucfirst((string)$student_status)) . '</div>
        </div>
        <div class="info-box">
            <div class="info-label">Academic Year</div>
            <div class="info-value">' . htmlspecialchars((string)$display_academic_year) . '</div>
        </div>
    </div>';

    $html .= '<div class="summary">
        <div class="summary-card">
            <div class="summary-label">Total Fees</div>
            <div class="summary-value">GH₵' . number_format($total_fees, 2) . '</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Payments</div>
            <div class="summary-value success">GH₵' . number_format($total_payments, 2) . '</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Arrears Carry Forward</div>
            <div class="summary-value">GH₵' . number_format($arrears, 2) . '</div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Balance Due</div>
            <div class="summary-value ' . $balance_class . '">GH₵' . number_format($balance, 2) . '</div>
        </div>
    </div>';

    $html .= '<div class="section-title">Fee Breakdown</div>
    <table>
        <thead>
            <tr>
                <th class="w-10-pct">Type</th>
                <th class="w-30-pct">Fee Name</th>
                <th class="w-12-pct" class="right">Amount</th>
                <th style="width: 15%;">Due Date</th>
                <th class="w-10-pct">Status</th>
                <th>Description / Notes</th>
            </tr>
        </thead>
        <tbody>';

    if (!empty($term_fees)) {
        foreach ($term_fees as $fee) {
            $name = strtolower(trim((string)($fee['fee_name'] ?? '')));
            $is_ob_fee = ($name === 'outstanding balance' || $name === 'arrears carry forward');
            $status = ucfirst((string)($fee['status'] ?? 'pending'));
            $amount = number_format((float)($fee['amount'] ?? 0), 2);
            $due_date = !empty($fee['due_date']) ? date('M j, Y', strtotime($fee['due_date'])) : '-';
            $notes = trim((string)($fee['notes'] ?? ''));
            $html .= '<tr>
                <td>' . htmlspecialchars($is_ob_fee ? 'Carry' : 'Fee') . '</td>
                <td>' . htmlspecialchars((string)($fee['fee_name'] ?? '')) . '</td>
                <td class="right">GH₵' . $amount . '</td>
                <td>' . htmlspecialchars($due_date) . '</td>
                <td>' . htmlspecialchars($status) . '</td>
                <td>' . htmlspecialchars($notes !== '' ? $notes : '-') . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="6" class="muted">No fee assignments found for this semester.</td></tr>';
    }

    $html .= '</tbody>
    </table>';

    $html .= '<div class="section-title">Payment History</div>
    <table>
        <thead>
            <tr>
                <th class="w-18-pct">Date</th>
                <th class="w-18-pct">Receipt No</th>
                <th class="w-14-pct" class="right">Amount</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>';

    if (!empty($payment_history)) {
        foreach ($payment_history as $payment) {
            $payment_date = !empty($payment['payment_date']) ? date('M j, Y', strtotime($payment['payment_date'])) : '-';
            $receipt_no = trim((string)($payment['receipt_no'] ?? ''));
            $description = trim((string)($payment['description'] ?? ''));
            $html .= '<tr>
                <td>' . htmlspecialchars($payment_date) . '</td>
                <td>' . htmlspecialchars($receipt_no !== '' ? $receipt_no : '-') . '</td>
                <td class="right">GH₵' . number_format((float)($payment['amount'] ?? 0), 2) . '</td>
                <td>' . htmlspecialchars($description !== '' ? $description : '-') . '</td>
            </tr>';
        }
    } else {
        $html .= '<tr><td colspan="4" class="muted">No payments recorded for this semester.</td></tr>';
    }

    $html .= '</tbody>
    </table>';

    $html .= '<div class="section-title">Payment Plan</div>
    <table>
        <thead>
            <tr>
                <th class="w-40-pct">Installment</th>
                <th class="w-10-pct">%</th>
                <th class="w-25-pct" class="right">Amount (GHS)</th>
                <th class="w-25-pct">Due Date</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($invoice_settings['payment_plan'] as $plan_row) {
        $percent = floatval($plan_row['percent'] ?? 0);
        $amount = ($total_fees * $percent) / 100;
        $html .= '<tr>
            <td>' . htmlspecialchars((string)($plan_row['name'] ?? 'Installment')) . '</td>
            <td>' . number_format($percent, 0) . '%</td>
            <td class="right">' . number_format($amount, 2) . '</td>
            <td>' . htmlspecialchars((string)($plan_row['due_date'] ?? '')) . '</td>
        </tr>';
    }

    $html .= '</tbody>
    </table>';

    $html .= '<div class="section-title">Payment Modes</div>
    <div class="grid-2">
        <div class="panel">
            <h4>' . htmlspecialchars((string)$bank['title']) . '</h4>
            <div><strong>Account Name:</strong> ' . htmlspecialchars((string)$bank['account_name']) . '</div>
            <div><strong>Account Number:</strong> ' . htmlspecialchars((string)$bank['account_number']) . '</div>
            <div><strong>Bank Name:</strong> ' . htmlspecialchars((string)$bank['bank_name']) . '</div>
        </div>
        <div class="panel">
            <h4>' . htmlspecialchars((string)$momo['title']) . '</h4>
            <div><strong>Number:</strong> ' . htmlspecialchars((string)$momo['number']) . '</div>
            <div><strong>Name:</strong> ' . htmlspecialchars((string)$momo['name']) . '</div>
        </div>
    </div>
    <p class="note">' . htmlspecialchars((string)$invoice_settings['payment_modes']['payment_reference']) . '</p>';

    $html .= '<div class="section-title">Note</div>
    <div class="panel">
        <ol class="notes-list">';
    foreach ($invoice_settings['notes'] as $note_item) {
        $html .= '<li>' . htmlspecialchars((string)$note_item) . '</li>';
    }
    $html .= '</ol>
    </div>';

    $html .= '<div class="note">This invoice is generated from assigned fees and payment records for the selected semester. Arrears are carried forward automatically when applicable.</div>';
    $html .= '<div class="footer">Generated by the accounts module on ' . htmlspecialchars(date('M j, Y g:i A')) . '.</div>';
    $html .= '</div></div></body></html>';

    return $html;
}