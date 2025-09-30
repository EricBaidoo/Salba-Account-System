<?php 
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/student_balance_functions.php';

$student_id = intval($_GET['id'] ?? 0);

if ($student_id === 0) {
    header('Location: student_balances.php');
    exit;
}

// Get student balance information
$student_balance = getStudentBalance($conn, $student_id);
if (!$student_balance) {
    header('Location: student_balances.php');
    exit;
}

// Get outstanding fees
$outstanding_fees = getStudentOutstandingFees($conn, $student_id);

// Get payment history
$payment_history = getStudentPaymentHistory($conn, $student_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student_balance['student_name']); ?> - Student Bill & Balance - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f6f8fa; }
        .student-header {
            background: linear-gradient(120deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 2.5rem 0 2rem 0;
            margin-bottom: 2.5rem;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 8px 32px rgba(106,17,203,0.08);
        }
        .student-header .avatar {
            width: 80px; height: 80px; border-radius: 50%; background: #fff; color: #2575fc; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; margin-right: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .balance-summary {
            background: #fff;
            border-radius: 1.5rem;
            padding: 2.5rem 2rem 2rem 2rem;
            box-shadow: 0 4px 24px rgba(37,117,252,0.07);
            margin-bottom: 2.5rem;
        }
        .summary-item {
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            margin-bottom: 1rem;
            background: #f8fafd;
            box-shadow: 0 2px 8px rgba(37,117,252,0.03);
        }
        .summary-item .icon {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .summary-item .label {
            font-size: 1.1rem;
            color: #6c757d;
        }
        .summary-item .value {
            font-size: 1.7rem;
            font-weight: 700;
        }
        .summary-item.owing .value { color: #dc3545; }
        .summary-item.paid .value { color: #28a745; }
        .summary-item.balance .value { color: #2575fc; }
        .summary-item.pending .value { color: #ffc107; }
        .card-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2575fc;
            margin-bottom: 1rem;
        }
        .fee-card, .payment-card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(37,117,252,0.04);
            margin-bottom: 1.2rem;
            border: none;
        }
        .fee-card.overdue { border-left: 5px solid #dc3545; }
        .fee-card.due-soon { border-left: 5px solid #ffc107; }
        .fee-card.pending { border-left: 5px solid #6c757d; }
        .fee-card .badge, .payment-card .badge { font-size: 0.95rem; }
        .fee-card .fw-bold, .payment-card .fw-bold { font-size: 1.1rem; }
        .payment-card { border-left: 5px solid #28a745; }
        .quick-actions .btn { min-width: 160px; margin-bottom: 0.5rem; }
        @media (max-width: 767px) {
            .student-header { text-align: center; padding: 2rem 0 1.5rem 0; }
            .student-header .avatar { margin: 0 auto 1rem auto; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Salba Montessori
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-primary" href="student_balances.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Balances
                </a>
            </div>
        </div>
    </nav>

    <!-- Student Header -->
    <div class="student-header mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 d-flex justify-content-center align-items-center">
                    <div class="avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="col-md-7">
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($student_balance['student_name']); ?></h2>
                    <div class="mb-2">
                        <span class="badge bg-primary fs-6"><i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($student_balance['class']); ?></span>
                        <?php if ($student_balance['student_status'] === 'inactive'): ?>
                            <span class="badge bg-secondary ms-2">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>" class="btn btn-success btn-lg me-2 mb-2">
                        <i class="fas fa-credit-card me-2"></i>Record Payment
                    </a>
                    <a href="assign_fee_form.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-light btn-lg mb-2">
                        <i class="fas fa-plus me-2"></i>Assign Fee
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Balance Summary -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm p-3">
                    <div class="card-section-title mb-2"><i class="fas fa-list text-primary me-2"></i>Student Bill & Payment Details</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Fee Name / Payment</th>
                                    <th>Amount (GH₵)</th>
                                    <th>Date</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Receipt No</th>
                                    <th>Description / Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Outstanding Fees -->
                                <?php if (!empty($outstanding_fees)): ?>
                                    <?php foreach($outstanding_fees as $fee): ?>
                                    <tr>
                                        <td><span class="badge bg-danger">Fee</span></td>
                                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                        <td class="fw-bold">GH₵<?php echo number_format($fee['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($fee['term'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $fee['payment_status'] === 'Overdue' ? 'bg-danger' : 
                                                    ($fee['payment_status'] === 'Due Soon' ? 'bg-warning text-dark' : 'bg-secondary'); 
                                            ?>">
                                                <?php echo $fee['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td></td>
                                        <td><?php if (!empty($fee['notes'])): ?><i class="fas fa-sticky-note me-1"></i> <?php echo htmlspecialchars($fee['notes']); ?><?php endif; ?></td>
                                        <td>
                                            <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>&fee_id=<?php echo $fee['id']; ?>&amount=<?php echo $fee['amount']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-credit-card me-1"></i>Pay Now
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-success">All Fees Paid!</td>
                                    </tr>
                                <?php endif; ?>
                                <!-- Payment History -->
                                <?php if (!empty($payment_history)): ?>
                                    <?php foreach($payment_history as $payment): ?>
                                    <tr>
                                        <td><span class="badge bg-success">Payment</span></td>
                                        <td>Payment</td>
                                        <td class="fw-bold">GH₵<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td></td>
                                        <td><span class="badge bg-success"><i class="fas fa-check"></i> Paid</span></td>
                                        <td><?php echo htmlspecialchars($payment['receipt_no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($payment['description'] ?? ''); ?></td>
                                        <td></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No Payments Yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-5 mb-4 quick-actions justify-content-center">
            <div class="col-auto">
                <a href="student_balances.php" class="btn btn-outline-secondary">
                    <i class="fas fa-balance-scale me-2"></i>All Balances
                </a>
            </div>
            <div class="col-auto">
                <a href="view_students.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>All Students
                </a>
            </div>
            <div class="col-auto">
                <a href="view_payments.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-success">
                    <i class="fas fa-history me-2"></i>Full Payment History
                </a>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-outline-info">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>