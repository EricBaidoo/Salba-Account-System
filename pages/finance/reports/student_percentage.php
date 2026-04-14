<?php
include '../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../../includes/db_connect.php';
include '../../includes/student_balance_functions.php';

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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body>
    <div class="max-w-7xl mx-auto py-4">
        <div class="flex justify-between items-center mb-">
            <h3><i class="fas fa-chart-pie mr-2"></i>Percentage for <?php echo htmlspecialchars($student['student_name']); ?></h3>
            <a href="student_balances.php" class="px-4 py-2 border border-gray-300 rounded">Back</a>
        </div>

        <div class="bg-white rounded shadow mb-">
            <div class="bg-white rounded shadow-body">
                <h5 class="bg-white rounded shadow-title">Payment Progress</h5>
                <p class="text-gray-600 mb-">Total Fees: GHâ‚µ<?php echo number_format($total_fees, 2); ?> &nbsp; | &nbsp; Total Paid: GHâ‚µ<?php echo number_format($total_payments, 2); ?></p>

                <div class="mb-">
                    <div class="progress progress-enhanced">
                        <div class="h-full bg-green-600" role="progressbar" style="width: <?php echo $paid_percent; ?>%;" aria-valuenow="<?php echo $paid_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                            <?php echo round($paid_percent, 2); ?>% Paid
                        </div>
                        <?php if ($owing_percent > 0): ?>
                            <div class="h-full bg-red-600" role="progressbar" style="width: <?php echo $owing_percent; ?>%;" aria-valuenow="<?php echo $owing_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <?php echo round($owing_percent, 2); ?>% Owing
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex flex-wrap">
                    <div class="md:col-span-6">
                        <h6>Details</h6>
                        <ul class="list-group">
                            <li class="list-group-item flex justify-between items-center">
                                Total Fees
                                <span>GHâ‚µ<?php echo number_format($total_fees, 2); ?></span>
                            </li>
                            <li class="list-group-item flex justify-between items-center">
                                Total Paid
                                <span>GHâ‚µ<?php echo number_format($total_payments, 2); ?></span>
                            </li>
                            <li class="list-group-item flex justify-between items-center">
                                Outstanding
                                <span>GHâ‚µ<?php echo number_format(max(0, $total_fees - $total_payments), 2); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="md:col-span-6">
                        <h6>Recent Payments</h6>
                        <ul class="list-group">
                            <?php $payments = getStudentPaymentHistory($conn, $student_id); ?>
                            <?php if (!empty($payments)): ?>
                                <?php foreach ($payments as $p): ?>
                                    <li class="list-group-item">
                                        <div class="flex justify-between">
                                            <div>
                                                <strong>GHâ‚µ<?php echo number_format($p['amount'], 2); ?></strong>
                                                <div class="small text-gray-600"><?php echo htmlspecialchars($p['payment_date']); ?> &nbsp; | &nbsp; Receipt: <?php echo htmlspecialchars($p['receipt_no']); ?></div>
                                            </div>
                                            <div class="text-right">
                                                <small class="text-gray-600"><?php echo htmlspecialchars($p['description']); ?></small>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-gray-600">No payments recorded</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    </body>
</html>

