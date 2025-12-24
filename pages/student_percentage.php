<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/student_balance_functions.php';

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($student_id <= 0) {
    header('Location: student_balances.php');
    exit;
}

$student = getStudentBalance($conn, $student_id);
if (!$student) {
    header('Location: student_balances.php');
    exit;
}

$total_fees = (float)($student['total_fees'] ?? 0);
$total_payments = (float)($student['total_payments'] ?? 0);
$paid_percent = $total_fees > 0 ? min(100, ($total_payments / $total_fees) * 100) : ($total_payments > 0 ? 100 : 0);
$owing_percent = 100 - $paid_percent;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Percentage - <?php echo htmlspecialchars($student['student_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3><i class="fas fa-chart-pie me-2"></i>Percentage for <?php echo htmlspecialchars($student['student_name']); ?></h3>
            <a href="student_balances.php" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Payment Progress</h5>
                <p class="text-muted mb-2">Total Fees: GH₵<?php echo number_format($total_fees, 2); ?> &nbsp; | &nbsp; Total Paid: GH₵<?php echo number_format($total_payments, 2); ?></p>

                <div class="mb-3">
                    <div class="progress" style="height: 28px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $paid_percent; ?>%;" aria-valuenow="<?php echo $paid_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($paid_percent, 2); ?>% Paid
                        </div>
                        <?php if ($owing_percent > 0): ?>
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $owing_percent; ?>%;" aria-valuenow="<?php echo $owing_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($owing_percent, 2); ?>% Owing
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6>Details</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Fees
                                <span>GH₵<?php echo number_format($total_fees, 2); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Total Paid
                                <span>GH₵<?php echo number_format($total_payments, 2); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Outstanding
                                <span>GH₵<?php echo number_format(max(0, $total_fees - $total_payments), 2); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Recent Payments</h6>
                        <ul class="list-group">
                            <?php $payments = getStudentPaymentHistory($conn, $student_id); ?>
                            <?php if (!empty($payments)): ?>
                                <?php foreach ($payments as $p): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong>GH₵<?php echo number_format($p['amount'], 2); ?></strong>
                                                <div class="small text-muted"><?php echo htmlspecialchars($p['payment_date']); ?> &nbsp; | &nbsp; Receipt: <?php echo htmlspecialchars($p['receipt_no']); ?></div>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted"><?php echo htmlspecialchars($p['description']); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-muted">No payments recorded</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
