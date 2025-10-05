<?php 
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/student_balance_functions.php';

// Get filter parameters
$class_filter = $_GET['class'] ?? 'all';
$status_filter = $_GET['status'] ?? 'active';
$owing_filter = $_GET['owing'] ?? 'all'; // all, owing, paid_up

// Get all student balances
$student_balances = getAllStudentBalances($conn, $class_filter, $status_filter);

// Apply owing filter
if ($owing_filter === 'owing') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] > 0;
    });
} elseif ($owing_filter === 'paid_up') {
    $student_balances = array_filter($student_balances, function($student) {
        return $student['net_balance'] == 0;
    });
}

// Calculate summary statistics
$total_students = count($student_balances);
$total_fees = array_sum(array_column($student_balances, 'total_fees'));
$total_payments = array_sum(array_column($student_balances, 'total_payments'));
$total_owing = array_sum(array_column($student_balances, 'net_balance'));
$students_with_balance = count(array_filter($student_balances, function($s) { return $s['net_balance'] > 0; }));

// Get classes for filter
$classes_result = $conn->query("SELECT DISTINCT class FROM students ORDER BY class");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Balances - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .balance-card {
            transition: all 0.3s ease;
            border: none;
            margin-bottom: 1rem;
        }
        .balance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .balance-amount {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .balance-owing {
            color: #dc3545;
        }
        .balance-paid {
            color: #28a745;
        }
        .balance-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }
        .filter-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="balance-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-6 fw-bold mb-2">
                        <i class="fas fa-balance-scale me-3"></i>
                        Student Balances
                    </h1>
                    <p class="lead mb-0">Track outstanding fees and payment status</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="assign_fee_form.php" class="btn btn-light btn-lg me-2">
                        <i class="fas fa-plus me-2"></i>Assign Fees
                    </a>
                    <a href="record_payment_form.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-credit-card me-2"></i>Record Payment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-primary text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4 class="mb-1"><?php echo $total_students; ?></h4>
                    <p class="text-muted mb-0">Total Students</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-secondary text-white">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h4 class="mb-1">GH₵<?php echo number_format($total_fees, 2); ?></h4>
                    <p class="text-muted mb-0">Total Fees</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h4 class="mb-1">GH₵<?php echo number_format($total_payments, 2); ?></h4>
                    <p class="text-muted mb-0">Total Paid</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon bg-danger text-white">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h4 class="mb-1">GH₵<?php echo number_format($total_owing, 2); ?></h4>
                    <p class="text-muted mb-0">Outstanding</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-layer-group me-2"></i>Class
                    </label>
                    <select name="class" class="form-select">
                        <option value="all" <?php echo $class_filter === 'all' ? 'selected' : ''; ?>>All Classes</option>
                        <?php while($class_row = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($class_row['class']); ?>" 
                                    <?php echo $class_filter === $class_row['class'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class_row['class']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-user-check me-2"></i>Status
                    </label>
                    <select name="status" class="form-select">
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        <i class="fas fa-balance-scale me-2"></i>Balance
                    </label>
                    <select name="owing" class="form-select">
                        <option value="all" <?php echo $owing_filter === 'all' ? 'selected' : ''; ?>>All Students</option>
                        <option value="owing" <?php echo $owing_filter === 'owing' ? 'selected' : ''; ?>>Owing Money</option>
                        <option value="paid_up" <?php echo $owing_filter === 'paid_up' ? 'selected' : ''; ?>>Paid Up</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Student Balances -->
        <div class="row">
            <?php if (!empty($student_balances)): ?>
                <?php foreach($student_balances as $student): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card balance-card shadow-sm h-100">
                            <div class="card-body">
                                <!-- Student Info Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($student['student_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($student['class']); ?>
                                            <?php if ($student['student_status'] === 'inactive'): ?>
                                                <span class="badge bg-secondary ms-2">Inactive</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="student_balance_details.php?id=<?php echo $student['student_id']; ?>">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </a></li>
                                            <li><a class="dropdown-item" href="record_payment_form.php?student_id=<?php echo $student['student_id']; ?>">
                                                <i class="fas fa-credit-card me-2"></i>Record Payment
                                            </a></li>
                                            <li><a class="dropdown-item" href="assign_fee_form.php?student_id=<?php echo $student['student_id']; ?>">
                                                <i class="fas fa-plus me-2"></i>Assign Fee
                                            </a></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Balance Information -->
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="balance-amount text-primary">
                                            GH₵<?php echo number_format($student['total_fees'] ?? 0, 2); ?>
                                        </div>
                                        <small class="text-muted">Total Fees</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="balance-amount balance-paid">
                                            GH₵<?php echo number_format($student['total_payments'] ?? 0, 2); ?>
                                        </div>
                                        <small class="text-muted">Total Paid</small>
                                    </div>
                                </div>

                                <!-- Net Balance (Owes/Paid Up) -->
                                <div class="text-center mt-3 pt-3 border-top">
                                    <?php $outstanding = max(0, ($student['total_fees'] ?? 0) - ($student['total_payments'] ?? 0)); ?>
                                    <div class="balance-amount <?php echo $outstanding > 0 ? 'balance-owing' : 'balance-paid'; ?>">
                                        <?php if ($outstanding > 0): ?>
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Owes: GH₵<?php echo number_format($outstanding, 2); ?>
                                        <?php else: ?>
                                            <i class="fas fa-check-circle me-2"></i>
                                            Paid Up
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Assignment Summary -->
                                <div class="mt-3 pt-3 border-top">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Pending</small>
                                            <span class="badge bg-warning"><?php echo $student['pending_assignments']; ?></span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Paid</small>
                                            <span class="badge bg-success"><?php echo $student['paid_assignments']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Students Found</h4>
                        <p class="text-muted mb-4">No students match your current filter criteria.</p>
                        <a href="?class=all&status=active&owing=all" class="btn btn-primary">
                            <i class="fas fa-refresh me-2"></i>Clear Filters
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="text-center mt-5 mb-4">
            <div class="btn-group" role="group">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="view_students.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>All Students
                </a>
                <a href="view_assigned_fees.php" class="btn btn-outline-success">
                    <i class="fas fa-list-alt me-2"></i>Fee Assignments
                </a>
                <a href="view_payments.php" class="btn btn-outline-info">
                    <i class="fas fa-credit-card me-2"></i>Payment History
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>