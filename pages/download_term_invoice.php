<?php
// Production-safe error settings (avoid PDF corruption)
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent any accidental output
ob_start();

require_once '../includes/db_connect.php';
require_once '../includes/auth_functions.php';
require_once '../includes/system_settings.php';
require_once '../includes/student_balance_functions.php';
require_once '../includes/term_helpers.php';

if (!is_logged_in()) {
    ob_end_clean();
    header('Location: login.php');
    exit;
}

// Check if mPDF is installed
if (!file_exists('../vendor/autoload.php')) {
    ob_end_clean();
    die('Error: mPDF library not installed. Please run: composer require mpdf/mpdf');
}

require_once '../vendor/autoload.php';

// Get parameters
$term = isset($_GET['term']) ? $_GET['term'] : '';
$class_filter = isset($_GET['class']) ? $_GET['class'] : 'all';
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if (empty($term)) {
    ob_end_clean();
    die('Error: Term is required');
}

// Get system settings
$current_term = getSystemSetting($conn, 'current_term', 'First Term');
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
// Academic year override via GET
$selected_academic_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== ''
    ? $_GET['academic_year']
    : $default_academic_year;
$strict_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== '';
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Fetch students based on mode (single or bulk)
$students = [];

if ($student_id > 0) {
    // SINGLE STUDENT MODE
    $student_sql = "SELECT * FROM students WHERE id = ? AND status = 'active'";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param('i', $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    
    if ($student) {
        $students[] = $student;
    }
} else {
    // BULK MODE - Multiple students
    $student_query = "
        SELECT DISTINCT s.id, s.first_name, s.last_name, s.class, s.parent_contact
        FROM students s
        INNER JOIN student_fees sf ON s.id = sf.student_id
        WHERE s.status = 'active' AND sf.term = ? AND ".($strict_year ? "sf.academic_year = ?" : "(sf.academic_year = ? OR sf.academic_year IS NULL)")." AND sf.status != 'cancelled'
    ";

    if ($class_filter !== 'all') {
        $student_query .= " AND s.class = ?";
    }

    $student_query .= " ORDER BY s.class, s.last_name, s.first_name";

    $student_stmt = $conn->prepare($student_query);
    if ($class_filter !== 'all') {
        $student_stmt->bind_param('sss', $term, $selected_academic_year, $class_filter);
    } else {
        $student_stmt->bind_param('ss', $term, $selected_academic_year);
    }
    $student_stmt->execute();
    $students_result = $student_stmt->get_result();
    
    while ($student = $students_result->fetch_assoc()) {
        $students[] = $student;
    }
    $student_stmt->close();
}

error_log('=== FETCHED ' . count($students) . ' STUDENTS ===');
if (count($students) > 0) {
    foreach ($students as $s) {
        error_log('Student: ' . $s['first_name'] . ' ' . $s['last_name'] . ' (ID: ' . $s['id'] . ')');
    }
}

// Process each student's fees and calculations
foreach ($students as &$student) {
    // Ensure arrears is materialized as a fee in the current term/year
    ensureArrearsAssignment($conn, $student['id'], $term, $selected_academic_year);

    // Fetch current term fees (includes arrears carry-forward row if any)
    $fees_sql = "
        SELECT sf.*, f.name as fee_name, f.fee_type,
               (sf.amount - sf.amount_paid) as balance_remaining
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE sf.student_id = ? AND sf.term = ? AND ".($strict_year ? "sf.academic_year = ?" : "(sf.academic_year = ? OR sf.academic_year IS NULL)")." AND sf.status != 'cancelled'
        ORDER BY f.name
    ";
    $fees_stmt = $conn->prepare($fees_sql);
    $fees_stmt->bind_param('iss', $student['id'], $term, $selected_academic_year);
    $fees_stmt->execute();
    $fees_result = $fees_stmt->get_result();
    
    $student['fees'] = [];
    $student['current_term_total'] = 0;
    $student['due_date'] = null;
    
    while ($fee = $fees_result->fetch_assoc()) {
        $student['fees'][] = $fee;
        $student['current_term_total'] += $fee['amount'];
        if (!$student['due_date'] && $fee['due_date']) {
            $student['due_date'] = $fee['due_date'];
        }
    }
    
    // Arrears is embedded as a fee; don't compute separately
    $student['arrears'] = 0.0;
    
    // Get total paid for THIS term/year only
    $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE student_id = ? AND term = ? AND ".($strict_year ? "academic_year = ?" : "(academic_year = ? OR academic_year IS NULL)"));
    $paid_stmt->bind_param('iss', $student['id'], $term, $selected_academic_year);
    $paid_stmt->execute();
    $student['total_paid'] = $paid_stmt->get_result()->fetch_assoc()['total'];
    $paid_stmt->close();
    
    // Invoice total is the sum of current term fees (including arrears fee row)
    $student['total_bill'] = $student['current_term_total'];
    $student['balance_due'] = max(0, $student['total_bill'] - $student['total_paid']);
}unset($student); // CRITICAL: Unset reference to prevent array corruption

error_log('=== AFTER PROCESSING FEES, STUDENTS ARRAY ===');
foreach ($students as $s) {
    error_log('Student: ' . $s['first_name'] . ' ' . $s['last_name'] . ' (ID: ' . $s['id'] . '), Balance: ' . $s['balance_due']);
}
// Create a temporary directory for PDFs
$temp_dir = sys_get_temp_dir() . '/invoices_' . uniqid();
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0777, true);
}

$pdf_files = [];

// Generate individual PDF for each student
foreach ($students as $student) {
    error_log('=== GENERATING PDF FOR: ' . $student['first_name'] . ' ' . $student['last_name'] . ' (ID: ' . $student['id'] . ') ===');
    // Build invoice number with academic year code (e.g., F2425####)
    $parts = explode('/', $selected_academic_year);
    $startYear = intval($parts[0] ?? date('Y'));
    $endPart = $parts[1] ?? (string)($startYear + 1);
    if (strlen($endPart) === 2) {
        $century = substr((string)$startYear, 0, 2);
        $endYear = intval($century . $endPart);
    } else {
        $endYear = intval($endPart);
    }
    $yearCode = substr((string)$startYear, -2) . substr((string)$endYear, -2);
    $invoice_number = strtoupper($term[0]) . $yearCode . str_pad($student['id'], 4, '0', STR_PAD_LEFT);
    
    // Build individual student HTML
    $student_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body class="pdf-document">
        <div class="bill-header">
            <img src="C:/xampp/htdocs/ACCOUNTING/img/salba_logo.jpg" alt="Salba Montessori School" class="school-logo">
            <div class="school-info">
                <h1>SALBA MONTESSORI SCHOOL</h1>
                <p class="tagline">Surge illuminare</p>
                <p class="contact">GC-051-0961 | Tel: 059 887 2309 | Email: info@salbamontessori.edu.gh</p>
            </div>
        </div>

        <div class="bill-title">SCHOOL FEES BILL - ' . strtoupper($term) . '</div>

        <div class="student-details">
            <table class="student-details-table">
                <tr>
                    <td>
                        <span class="label">STUDENT NAME:</span>
                        <span>' . strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'])) . '</span>
                    </td>
                    <td>
                        <span class="label">CLASS:</span>
                        <span>' . strtoupper(htmlspecialchars($student['class'])) . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">TERM:</span>
                        <span>' . strtoupper(htmlspecialchars($term)) . '</span>
                    </td>
                    <td>
                        <span class="label">ACADEMIC YEAR:</span>
                        <span>' . htmlspecialchars($display_academic_year) . '</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <span class="label">BILL NO:</span>
                        <span>' . $invoice_number . '</span>
                    </td>
                    <td>
                        <span class="label">DATE ISSUED:</span>
                        <span>' . date('d M, Y') . '</span>
                    </td>
                </tr>
            </table>
        </div>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>FEE DESCRIPTION</th>
                    <th class="fee-amount-col">AMOUNT (GH₵)</th>
                </tr>
            </thead>
            <tbody>';
    
    // No separate ARREARS row; arrears is part of current term fees as a materialized fee
    
    foreach ($student['fees'] as $fee) {
        $student_html .= '
                <tr>
                    <td>' . strtoupper(htmlspecialchars($fee['fee_name'])) . '</td>
                    <td class="fee-amount-col">' . number_format($fee['amount'], 2) . '</td>
                </tr>';
    }
    
    $student_html .= '
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td class="label">TOTAL:</td>
                <td class="value">GH₵ ' . number_format($student['total_bill'], 2) . '</td>
            </tr>
        </table>

        <div class="payment-section">
            <h3>PAYMENT PLAN</h3>
            <table class="payment-plan-table">
                <thead>
                    <tr>
                        <th>INSTALLMENT</th>
                        <th class="text-center">%</th>
                        <th class="text-right">AMOUNT (GHS)</th>
                        <th>DUE DATE</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>First Installment</td>
                        <td class="text-center">50%</td>
                        <td class="text-right">' . number_format($student['total_bill'] * 0.50, 2) . '</td>
                        <td>15 Jan</td>
                    </tr>
                    <tr>
                        <td>Second Installment</td>
                        <td class="text-center">30%</td>
                        <td class="text-right">' . number_format($student['total_bill'] * 0.30, 2) . '</td>
                        <td>30 Jan</td>
                    </tr>
                    <tr>
                        <td>Final Installment</td>
                        <td class="text-center">20%</td>
                        <td class="text-right">' . number_format($student['total_bill'] * 0.20, 2) . '</td>
                        <td>16 Feb</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="payment-section">
            <h3>PAYMENT MODES</h3>
            <div class="payment-modes-grid">
                <div class="payment-mode">
                    <h4>BANK DEPOSIT/TRANSFER</h4>
                    <p><strong>ACCOUNT NAME:</strong> SALBA MONTESSORI INTERNATIONAL SCHOOL</p>
                    <p><strong>ACCOUNT NUMBER:</strong> 0010223853018</p>
                    <p><strong>BANK NAME:</strong> OMNI BSIC - SPINTEX BRANCH</p>
                </div>
                <div class="payment-mode">
                    <h4>MOBILE MONEY (MOMO)</h4>
                    <p><strong>NUMBER:</strong> 0598872309</p>
                    <p><strong>NAME:</strong> SALBA MONTESSORI INTERNATIONAL SCHOOL</p>
                </div>
            </div>
            <p class="payment-reference">
                Please include the student\'s name and class as reference for all payments.
            </p>
        </div>
        
        <div class="payment-section">
            <h3>NOTE:</h3>
            <ol class="footer-notes">
                <li>All outstanding fees must be cleared before school reopens.</li>
                <li>All fees must be paid before the end of the term.</li>
                <li>Please ensure all items are provided on time.</li>
                <li>Feeding fee is strictly weekly, monthly, or termly (<strong>NO DAILY FEEDING FEE</strong>).</li>
                <li>For any queries, contact the school administration.</li>
            </ol>
        </div>
    </body>
    </html>';
    
    error_log('Processing student: ' . $student['first_name'] . ' ' . $student['last_name'] . ' (ID: ' . $student['id'] . ')');
    try {
        // Create individual PDF for this student
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            // Tight margins to fill A4 while keeping a little white space
            'margin_left' => 6,
            'margin_right' => 6,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_header' => 2,
            'margin_footer' => 2,
            'default_font' => 'dejavusans'
        ]);

        $mpdf->SetTitle('Invoice - ' . $student['first_name'] . ' ' . $student['last_name']);
        $mpdf->SetAuthor('Salba Montessori School');
        $mpdf->SetCreator('Salba Accounting System');
        // Load external stylesheet for invoice/PDF styles (lightweight, mPDF-safe)
        $cssPath = __DIR__ . '/../css/pdf_invoice.css';
        if (file_exists($cssPath)) {
            $css = file_get_contents($cssPath);
            $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        }
        $mpdf->WriteHTML($student_html);

        // Generate filename with student name
        $student_name = $student['first_name'] . '_' . $student['last_name'];
        $student_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $student_name); // Clean filename
        $pdf_filename = $student_name . '_bills.pdf';
        $pdf_path = $temp_dir . '/' . $pdf_filename;
        
        // Save PDF to file
        $mpdf->Output($pdf_path, 'F'); // F = Save to file
        
        // Verify file was created
        if (file_exists($pdf_path)) {
            $pdf_files[] = [
                'path' => $pdf_path,
                'name' => $pdf_filename
            ];
            error_log('PDF created successfully: ' . $pdf_filename);
        } else {
            error_log('ERROR: PDF file was not created at: ' . $pdf_path);
        }
        
    } catch (\Mpdf\MpdfException $e) {
        // Continue with other students even if one fails
        error_log('Error generating PDF for student ' . $student['id'] . ': ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('General error generating PDF for student ' . $student['id'] . ': ' . $e->getMessage());
    }
}

// If only one student, download the PDF directly
if (count($pdf_files) === 1) {
    $file = $pdf_files[0];
    
    // Clear any output buffer
    ob_end_clean();
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
    header('Content-Length: ' . filesize($file['path']));
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    readfile($file['path']);
    
    // Clean up
    unlink($file['path']);
    rmdir($temp_dir);
    exit;
}

// For multiple students, create a ZIP file
if (count($pdf_files) > 1) {
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        // ZipArchive not available - download first PDF with a note
        $file = $pdf_files[0];
        
        // Clean up other files
        for ($i = 1; $i < count($pdf_files); $i++) {
            unlink($pdf_files[$i]['path']);
        }
        
        // Clear any output buffer
        ob_end_clean();
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
        header('Content-Length: ' . filesize($file['path']));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        readfile($file['path']);
        
        unlink($file['path']);
        rmdir($temp_dir);
        
        // Note: You'll need to enable ZIP extension in php.ini to download all files at once
        exit;
    }
    
    $zip_filename = 'Term_Invoices_' . str_replace(' ', '_', $term);
    if ($class_filter !== 'all') {
        $zip_filename .= '_' . str_replace(' ', '_', $class_filter);
    }
    $zip_filename .= '_' . date('Y-m-d') . '.zip';
    
    $zip_path = $temp_dir . '/' . $zip_filename;
    $zip = new ZipArchive();
    
    if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE) {
        foreach ($pdf_files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }
        $zip->close();
        
        // Clear any output buffer
        ob_end_clean();
        
        // Download the ZIP file
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_path));
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        readfile($zip_path);
        
        // Clean up
        foreach ($pdf_files as $file) {
            unlink($file['path']);
        }
        unlink($zip_path);
        rmdir($temp_dir);
        exit;
    } else {
        die('Error creating ZIP file');
    }
}

// No PDFs generated
die('No invoices were generated');
