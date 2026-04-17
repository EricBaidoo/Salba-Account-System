<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
// Current term/year
$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);

// Total students (active only)
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students WHERE status = 'active'")->fetch_assoc()['count'];


// Use per-student balances logic for outstanding fees, but compute payments/expenses directly from ledgers
require_once '../includes/student_balance_functions.php';
$student_balances = getAllStudentBalances($conn, null, 'active', $current_term, $academic_year);

$total_fees_assigned = 0;
$outstanding_fees = 0;
$total_arrears = 0;
foreach ($student_balances as $s) {
    $total_fees_assigned += (float)($s['total_fees'] ?? 0);
    $outstanding_fees += (float)($s['net_balance'] ?? 0); // student-only outstanding
    $total_arrears += (float)($s['arrears'] ?? 0);
}

// Total payments (include general payments) for current term/year
$pay_stmt = $conn->prepare("SELECT COALESCE(SUM(p.amount),0) AS total FROM payments p LEFT JOIN students s ON p.student_id = s.id WHERE p.term = ? AND p.academic_year = ? AND (s.status = 'active' OR p.payment_type = 'general')");
$pay_stmt->bind_param('ss', $current_term, $academic_year);
$pay_stmt->execute();
$pay_res = $pay_stmt->get_result();
$total_payments = $pay_res->fetch_assoc()['total'] ?? 0;
$pay_stmt->close();

// Total expenses for current term/year (use term/year columns; fall back to zero if missing)
$exp_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) AS total FROM expenses WHERE term = ? AND academic_year = ?");
$exp_stmt->bind_param('ss', $current_term, $academic_year);
$exp_stmt->execute();
$exp_res = $exp_stmt->get_result();
$total_expenses = $exp_res->fetch_assoc()['total'] ?? 0;
$exp_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="dashboard-body">
    
    <!-- Clean Header -->
    <div class="dashboard-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="header-badge">WELCOME BACK</div>
                    <h1 class="header-title">Salba Montessori Accounting</h1>
                    <p class="header-subtitle">Comprehensive financial management system for academic excellence</p>
                    <div class="mt-2">
                        <span class="clean-badge clean-badge-primary me-2"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($current_term); ?></span>
                        <span class="clean-badge clean-badge-info"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($display_academic_year); ?></span>
                    </div>
                </div>
                <div class="header-user">
                    <div class="user-circle">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <a href="logout.php" class="btn-logout-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Statistics Cards -->
    <div class="container-fluid px-4 py-4">
        <div class="row g-3">
                        <!-- Manage Fee Categories Module removed as requested -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-box stats-primary">
                    <div class="stats-icon-circle">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-badge">100%</div>
                        <div class="stats-number"><?php echo $total_students; ?></div>
                        <div class="stats-label">ACTIVE STUDENTS</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-box stats-success">
                    <div class="stats-icon-circle">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-badge"><i class="fas fa-check-circle"></i> Received</div>
                        <div class="stats-number">GH₵<?php echo number_format($total_payments, 2); ?></div>
                        <div class="stats-label">TOTAL PAYMENTS</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-box stats-danger">
                    <div class="stats-icon-circle">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-badge"><i class="fas fa-arrow-down"></i> Spent</div>
                        <div class="stats-number">GH₵<?php echo number_format($total_expenses, 2); ?></div>
                        <div class="stats-label">TOTAL EXPENSES</div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-box stats-warning">
                    <div class="stats-icon-circle">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stats-content">
                        <div class="stats-badge"><i class="fas fa-clock"></i> Pending</div>
                        <div class="stats-number">GH₵<?php echo number_format($outstanding_fees, 2); ?></div>
                        <div class="stats-label">OUTSTANDING FEES</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- Quick Access Section -->
    <div class="container-fluid px-4 py-4">
        <div class="section-title-bar">
            <h2 class="section-title-text"><i class="fas fa-bolt me-2"></i>Quick Access</h2>
            <p class="section-subtitle-text">Navigate to key modules and features</p>
        </div>

        <div class="row g-3">
            <!-- Students Module -->
            <div class="col-xl-3 col-lg-6">
                <div class="quick-module quick-module-students">
                    <div class="quick-module-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h5 class="quick-module-title">Students</h5>
                    <p class="quick-module-desc">Manage student records, enrollment, and profiles</p>
                    <div class="quick-module-actions">
                        <a href="view_students.php" class="btn-quick-outline"><i class="fas fa-eye me-1"></i> VIEW ALL</a>
                        <a href="add_student_form.php" class="btn-quick-solid"><i class="fas fa-plus me-1"></i> ADD NEW</a>
                    </div>
                    <div class="quick-module-footer">
                        <a href="student_balances.php" class="quick-link"><i class="fas fa-wallet me-1"></i> View Balances</a>
                    </div>
                </div>
            </div>

            <!-- Fees & Billing Module -->
            <div class="col-xl-3 col-lg-6">
                <div class="quick-module quick-module-billing">
                    <div class="quick-module-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h5 class="quick-module-title">Fees & Billing</h5>
                    <p class="quick-module-desc">Configure fees, generate bills, and manage invoices</p>
                    <div class="quick-module-actions">
                        <a href="view_fees.php" class="btn-quick-outline"><i class="fas fa-eye me-1"></i> VIEW ALL</a>
                        <a href="add_fee_form.php" class="btn-quick-solid"><i class="fas fa-plus me-1"></i> ADD FEE</a>
                    </div>
                    <div class="quick-module-footer">
                        <a href="assign_fee_form.php" class="quick-link"><i class="fas fa-link me-1"></i> Assign Fees</a>
                        <a href="generate_term_bills.php" class="quick-link"><i class="fas fa-file-invoice me-1"></i> Generate Bills</a>
                        <a href="term_invoice.php" class="quick-link"><i class="fas fa-print me-1"></i> Term Invoices</a>
                        <a href="manage_fee_categories.php" class="quick-link"><i class="fas fa-tags me-1"></i> Manage Fee Categories</a>
                    </div>
                </div>
            </div>

            <!-- Payments Module -->
            <div class="col-xl-3 col-lg-6">
                <div class="quick-module quick-module-payments">
                    <div class="quick-module-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h5 class="quick-module-title">Payments</h5>
                    <p class="quick-module-desc">Record student payments and track transactions</p>
                    <div class="quick-module-actions">
                        <a href="view_payments.php" class="btn-quick-outline"><i class="fas fa-eye me-1"></i> VIEW ALL</a>
                        <a href="record_payment_form.php" class="btn-quick-solid"><i class="fas fa-plus me-1"></i> RECORD</a>
                    </div>
                    <div class="quick-module-footer">
                        <a href="receipt.php" class="quick-link"><i class="fas fa-print me-1"></i> Print Receipt</a>
                    </div>
                </div>
            </div>

            <!-- Expenses Module -->
            <div class="col-xl-3 col-lg-6">
                <div class="quick-module quick-module-expenses">
                    <div class="quick-module-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h5 class="quick-module-title">Expenses</h5>
                    <p class="quick-module-desc">Track school expenses and manage budgets</p>
                    <div class="quick-module-actions">
                        <a href="view_expenses.php" class="btn-quick-outline"><i class="fas fa-eye me-1"></i> VIEW ALL</a>
                        <a href="add_expense_form.php" class="btn-quick-solid"><i class="fas fa-plus me-1"></i> ADD NEW</a>
                    </div>
                    <div class="quick-module-footer">
                        <a href="add_expense_category_form.php" class="quick-link"><i class="fas fa-tags me-1"></i> Categories</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div>

    <!-- System Management Section -->
    <div class="container-fluid px-4 pb-4">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="admin-feature-box">
                    <div class="admin-icon-lg">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <div class="admin-content">
                        <h4 class="admin-title">System Administration</h4>
                        <p class="admin-subtitle">Configure term settings, academic year, and school information</p>
                        <div class="admin-buttons">
                            <a href="system_settings.php" class="btn-admin-primary">
                                <i class="fas fa-cog me-2"></i>SYSTEM SETTINGS
                            </a>
                            <a href="report.php" class="btn-admin-secondary">
                                <i class="fas fa-chart-line me-2"></i>REPORTS
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="academic-info-box">
                    <div class="academic-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h5 class="academic-title">Academic Info</h5>
                    <div class="academic-details">
                        <div class="academic-item">
                            <span class="academic-label">Current Term:</span>
                            <span class="academic-value"><?php echo htmlspecialchars($current_term); ?></span>
                        </div>
                        <div class="academic-item">
                            <span class="academic-label">Academic Year:</span>
                            <span class="academic-value"><?php echo htmlspecialchars($display_academic_year); ?></span>
                        </div>
                        <div class="academic-item">
                            <span class="academic-label">Status:</span>
                            <span class="badge badge-active">Active</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Professional Footer -->
    <footer class="clean-footer">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Salba Montessori School. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0">
                        <i class="fas fa-shield-alt me-1"></i>Secure Financial Management System
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>