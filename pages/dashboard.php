<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
// Total students
$total_students = $conn->query("SELECT COUNT(*) AS count FROM students")->fetch_assoc()['count'];
// Total fees assigned (all non-cancelled)
$total_fees_assigned = $conn->query("SELECT SUM(amount) AS total FROM student_fees WHERE status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
// Total payments
$total_payments = $conn->query("SELECT SUM(amount) AS total FROM payments")->fetch_assoc()['total'] ?? 0;
// Total expenses
$total_expenses = $conn->query("SELECT SUM(amount) AS total FROM expenses")->fetch_assoc()['total'] ?? 0;
// Outstanding fees (all due or pending, not cancelled)
$outstanding_fees = $conn->query("SELECT SUM(amount) AS total FROM student_fees WHERE (status = 'due' OR status = 'pending' OR status = 'overdue') AND status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
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
    <style>
        .hero-header {
            background: linear-gradient(120deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 8px 32px rgba(106,17,203,0.08);
            padding: 3rem 0 2rem 0;
            margin-bottom: 2.5rem;
        }
        .summary-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 4px 24px rgba(37,117,252,0.07);
            padding: 2rem 1.5rem;
            margin-bottom: 2rem;
        }
        .quick-link-card {
            border-radius: 1.5rem;
            box-shadow: 0 2px 12px rgba(37,117,252,0.04);
            transition: all 0.3s ease;
        }
        .quick-link-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(37,117,252,0.10);
        }
        .quick-link-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .footer {
            background: #f8fafd;
            border-top: 1px solid #e3e6fc;
            padding: 1.5rem 0 0.5rem 0;
            margin-top: 3rem;
        }
    </style>
</head>
<body>

    <!-- Hero Header -->
    <div class="hero-header mb-4">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1">Welcome to Salba Montessori Accounting</h1>
                    <p class="mb-0 fs-5">A modern, classic dashboard for all your school financial management needs.</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="fs-5">Hello, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>!</span>
                    <a href="logout.php" class="btn btn-outline-danger ms-2"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Summary Cards at Top -->
    <div class="container mt-4 mb-4">
        <div class="row">
            <div class="col-md-3 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-primary"><i class="fas fa-users me-2"></i><?php echo $total_students; ?></div>
                    <div class="text-muted">Total Students</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-success"><i class="fas fa-money-bill-wave me-2"></i>GH₵<?php echo number_format($total_payments, 2); ?></div>
                    <div class="text-muted">Total Payments</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-danger"><i class="fas fa-receipt me-2"></i>GH₵<?php echo number_format($total_expenses, 2); ?></div>
                    <div class="text-muted">Total Expenses</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-warning"><i class="fas fa-exclamation-triangle me-2"></i>GH₵<?php echo number_format($outstanding_fees, 2); ?></div>
                    <div class="text-muted">Outstanding Fees</div>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <!-- Quick Links -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card quick-link-card h-100 text-center p-4">
                    <div class="quick-link-icon text-primary"><i class="fas fa-user-graduate"></i></div>
                    <h5 class="fw-bold">Students</h5>
                    <p class="text-muted">Manage student records and profiles</p>
                    <a href="view_students.php" class="btn btn-outline-primary btn-sm mb-2">View All</a>
                    <a href="add_student_form.php" class="btn btn-primary btn-sm mb-2">Add Student</a>
                    <a href="student_balances.php" class="btn btn-secondary btn-sm">Student Balances</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card quick-link-card h-100 text-center p-4">
                    <div class="quick-link-icon text-success"><i class="fas fa-money-check-alt"></i></div>
                    <h5 class="fw-bold">Fees</h5>
                    <p class="text-muted">Manage fee structures and assignments</p>
                    <a href="view_fees.php" class="btn btn-outline-success btn-sm mb-2">View Fees</a>
                    <a href="add_fee_form.php" class="btn btn-success btn-sm mb-2">Add Fee</a>
                    <a href="assign_fee_form.php" class="btn btn-outline-success btn-sm">Assign Fee</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card quick-link-card h-100 text-center p-4">
                    <div class="quick-link-icon text-info"><i class="fas fa-credit-card"></i></div>
                    <h5 class="fw-bold">Payments</h5>
                    <p class="text-muted">Record and track student payments</p>
                    <a href="view_payments.php" class="btn btn-outline-info btn-sm mb-2">View Payments</a>
                    <a href="record_payment_form.php" class="btn btn-info btn-sm">Record Payment</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card quick-link-card h-100 text-center p-4">
                    <div class="quick-link-icon text-warning"><i class="fas fa-file-invoice-dollar"></i></div>
                    <h5 class="fw-bold">Expenses</h5>
                    <p class="text-muted">Track and manage school expenses</p>
                    <a href="view_expenses.php" class="btn btn-outline-warning btn-sm mb-2">View Expenses</a>
                    <a href="add_expense_form.php" class="btn btn-warning btn-sm mb-2">Add Expense</a>
                    <a href="add_expense_category_form.php" class="btn btn-outline-secondary btn-sm">Add Category</a>
                </div>
            </div>
        </div>
        <!-- Financial Overview -->
        <div class="text-center mt-4">
            <a href="report.php" class="btn btn-secondary btn-lg">View Detailed Reports</a>
        </div>
    </div>
    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container text-center">
            <p>&copy; 2025 Salba Montessori School. All rights reserved.</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>