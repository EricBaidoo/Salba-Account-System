<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
include '../includes/student_balance_functions.php';
require_once '../includes/term_helpers.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get system settings
$current_term = getSystemSetting($conn, 'current_term', 'First Term');
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
// Allow override via query param
$selected_academic_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== ''
    ? $_GET['academic_year']
    : $default_academic_year;
$strict_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== '';
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

/**
 * ARREARS HANDLING:
 * We now materialize previous term arrears as a fee within the
 * current term ("Arrears Carry Forward"). Invoices should therefore
 * list arrears inside the current term fees rather than as a separate
 * computed block. Payments and balances are scoped by term+year.
 */

// Get parameters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$term = isset($_GET['term']) ? $_GET['term'] : '';
$class_filter = isset($_GET['class']) ? $_GET['class'] : 'all';

// Show selection form if no term specified
if (empty($term)) {
    $classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");
    // Collect distinct academic years available in data
    $years = [];
    $years_result = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
    if ($years_result) {
        while ($yr = $years_result->fetch_assoc()) {
            if (!empty($yr['academic_year'])) {
                $years[] = $yr['academic_year'];
            }
        }
        $years_result->close();
    }
    if (!in_array($default_academic_year, $years, true)) {
        array_unshift($years, $default_academic_year);
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Generate Term Invoices - Salba Montessori</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body class="clean-body">
        <div class="clean-container">
            <div class="clean-header">
                <div class="clean-header-content">
                    <a href="dashboard.php" class="clean-back-link">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Dashboard</span>
                    </a>
                    <div class="clean-header-title">
                        <h1><i class="fas fa-file-invoice"></i>Generate Term Invoices</h1>
                        <p>Create printable invoices for all students</p>
                    </div>
                </div>
            </div>

            <div class="clean-card">
                <div class="clean-card-header">
                    <h5><i class="fas fa-cog"></i>Invoice Settings</h5>
                </div>
                <div class="clean-card-body">
                    <form method="GET" action="term_invoice.php">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="term" class="clean-form-label">
                                    <i class="fas fa-calendar-alt"></i>Select Term
                                    <span class="required-indicator">*</span>
                                </label>
                                <select class="clean-form-control" id="term" name="term" required>
                                    <option value="">Choose Term...</option>
                                    <option value="First Term">First Term</option>
                                    <option value="Second Term">Second Term</option>
                                    <option value="Third Term">Third Term</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="academic_year" class="clean-form-label">
                                    <i class="fas fa-calendar"></i>Academic Year
                                </label>
                                <select class="clean-form-control" id="academic_year" name="academic_year">
                                    <?php foreach ($years as $yr): $label = formatAcademicYearDisplay($conn, $yr); ?>
                                        <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $selected_academic_year) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="class" class="clean-form-label">
                                    <i class="fas fa-school"></i>Filter by Class
                                </label>
                                <select class="clean-form-control" id="class" name="class">
                                    <option value="all">All Classes</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="clean-info-box mt-4">
                            <i class="fas fa-info-circle"></i>
                            <span>This will generate invoices for all active students with fees assigned for the selected term.</span>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="clean-btn-primary clean-btn-lg">
                                <i class="fas fa-file-invoice"></i>Generate Invoices
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

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
}

// Process each student's fees and calculations
foreach ($students as &$student) {
    // Ensure arrears is materialized as a fee in the current term/year
    ensureArrearsAssignment($conn, $student['id'], $term, $selected_academic_year);

    // Fetch current term fees (includes arrears carry-forward if any)
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
    
    // Arrears are now part of current term fees; do not compute separately to avoid double counting
    $student['arrears'] = 0.0;
    
    // Get total paid for THIS term/year only (usually zero at invoice time)
    $paid_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE student_id = ? AND term = ? AND ".($strict_year ? "academic_year = ?" : "(academic_year = ? OR academic_year IS NULL)"));
    $paid_stmt->bind_param('iss', $student['id'], $term, $selected_academic_year);
    $paid_stmt->execute();
    $student['total_paid'] = $paid_stmt->get_result()->fetch_assoc()['total'];
    $paid_stmt->close();
    
    // With arrears embedded in current term fees, total bill is simply the term total
    $student['total_bill'] = $student['current_term_total'];
    $student['balance_due'] = max(0, $student['total_bill'] - $student['total_paid']);
}

$invoice_date = date('F j, Y');
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Term Invoices - <?php echo htmlspecialchars($term); ?></title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body class="invoice-body">
        <div class="no-print no-print-actions">
            <?php if (isset($_GET['generated']) && $_GET['generated'] == 1): ?>
                <div class="clean-success-box mb-4">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <h5>Fees Assigned & Invoices Generated!</h5>
                        <p>
                            <strong><?php echo intval($_GET['count'] ?? 0); ?> fees</strong> assigned successfully.
                            <?php if (isset($_GET['skipped']) && $_GET['skipped'] > 0): ?>
                                <small><?php echo intval($_GET['skipped']); ?> already assigned (skipped).</small>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            <div class="clean-action-bar">
                <div class="clean-action-info">
                    <h4><i class="fas fa-file-invoice"></i><?php echo count($students); ?> Invoices Ready</h4>
                    <p>Term: <?php echo htmlspecialchars($term); ?> | Year: <?php echo htmlspecialchars($display_academic_year); ?> | Class: <?php echo $class_filter === 'all' ? 'All' : htmlspecialchars($class_filter); ?></p>
                </div>
                <div class="clean-action-buttons">
                    <button onclick="downloadPDF()" class="clean-btn-success clean-btn-lg">
                        <i class="fas fa-download"></i>Download PDF
                    </button>
                    <a href="generate_term_bills.php" class="clean-btn-primary clean-btn-lg">
                        <i class="fas fa-arrow-left"></i>Generate More
                    </a>
                </div>
            </div>
        </div>

        <?php foreach ($students as $index => $student): 
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
        ?>
        <div class="invoice-page">
            <!-- Professional Header with Logo -->
            <div class="bill-header">
                <img src="/ACCOUNTING/img/salba_logo.jpg" alt="Salba Montessori School" class="school-logo">
                <div class="school-info">
                    <h1>SALBA MONTESSORI SCHOOL</h1>
                    <p class="tagline">Surge illuminare</p>
                    <p class="contact">GC-051-0961 | Tel: 059 887 2309 | Email: info@salbamontessori.edu.gh</p>
                </div>
            </div>

            <div class="bill-title">SCHOOL FEES BILL - <?php echo strtoupper($term); ?></div>

            <!-- Student Details (2 columns x 3 rows) -->
            <div class="student-details">
                <table class="student-details-table">
                    <tr>
                        <td>
                            <span class="label">STUDENT NAME:</span>
                            <span><?php echo strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'])); ?></span>
                        </td>
                        <td>
                            <span class="label">CLASS:</span>
                            <span><?php echo strtoupper(htmlspecialchars($student['class'])); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label">TERM:</span>
                            <span><?php echo strtoupper(htmlspecialchars($term)); ?></span>
                        </td>
                        <td>
                            <span class="label">ACADEMIC YEAR:</span>
                            <span><?php echo htmlspecialchars($display_academic_year); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="label">BILL NO:</span>
                            <span><?php echo $invoice_number; ?></span>
                        </td>
                        <td>
                            <span class="label">DATE ISSUED:</span>
                            <span><?php echo date('d M, Y'); ?></span>
                        </td>
                    </tr>
                </table>
            </div>

            <table class="fee-table">
                <thead>
                    <tr>
                        <th class="fee-description-col">FEE DESCRIPTION</th>
                        <th class="fee-amount-col">AMOUNT (GH₵)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($student['arrears'] > 0): ?>
                        <tr class="arrears-row">
                            <td><strong>ARREARS</strong></td>
                            <td><strong><?php echo number_format($student['arrears'], 2); ?></strong></td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($student['fees'] as $fee): ?>
                        <tr>
                            <td><?php echo strtoupper(htmlspecialchars($fee['fee_name'])); ?></td>
                            <td><?php echo number_format($fee['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="totals-table">
                <tr>
                    <td class="label">TOTAL:</td>
                    <td class="value">GH₵ <?php echo number_format($student['total_bill'], 2); ?></td>
                </tr>
            </table>

            <div class="footer">
                <!-- Payment Plan -->
                <div class="payment-section">
                    <h3>PAYMENT PLAN</h3>
                    <table class="payment-plan-table">
                        <thead>
                            <tr>
                                <th class="installment-name">INSTALLMENT</th>
                                <th class="percentage">%</th>
                                <th class="amount">AMOUNT (GHS)</th>
                                <th class="due-date">DUE DATE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="installment-name">First Installment</td>
                                <td class="percentage">50%</td>
                                <td class="amount"><?php echo number_format($student['total_bill'] * 0.50, 2); ?></td>
                                <td class="due-date">12 Sept</td>
                            </tr>
                            <tr>
                                <td class="installment-name">Second Installment</td>
                                <td class="percentage">30%</td>
                                <td class="amount"><?php echo number_format($student['total_bill'] * 0.30, 2); ?></td>
                                <td class="due-date">1st Oct</td>
                            </tr>
                            <tr>
                                <td class="installment-name">Final Installment</td>
                                <td class="percentage">20%</td>
                                <td class="amount"><?php echo number_format($student['total_bill'] * 0.20, 2); ?></td>
                                <td class="due-date">3rd Nov</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Payment Modes -->
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
                        Please include the student's name and class as reference for all payments.
                    </p>
                </div>
                
                <!-- Notes -->
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
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (count($students) == 0): ?>
            <div class="invoice-page">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    No students found with fees assigned for <strong><?php echo htmlspecialchars($term); ?></strong>
                    <?php if ($class_filter !== 'all'): ?>
                        in class <strong><?php echo htmlspecialchars($class_filter); ?></strong>
                    <?php endif; ?>.
                </div>
                <div class="text-center">
                    <a href="term_invoice.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Go Back
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <script>
        function downloadPDF() {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const term = urlParams.get('term');
            const classFilter = urlParams.get('class') || 'all';
            const academicYear = urlParams.get('academic_year') || '';
            const studentId = urlParams.get('student_id') || '';
            
            // Build download URL
            let downloadUrl = 'download_term_invoice.php?term=' + encodeURIComponent(term) + 
                            '&class=' + encodeURIComponent(classFilter);
            if (academicYear) {
                downloadUrl += '&academic_year=' + encodeURIComponent(academicYear);
            }
            
            if (studentId) {
                downloadUrl += '&student_id=' + studentId;
            }
            
            // Open in new tab to trigger download
            window.open(downloadUrl, '_blank');
        }
        </script>
    </body>
    </html>
