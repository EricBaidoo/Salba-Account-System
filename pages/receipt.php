<?php
include '../includes/db_connect.php';
include '../includes/system_settings.php';
// Get payment ID from query string
$payment_id = isset($_GET['payment_id']) ? intval($_GET['payment_id']) : 0;
if ($payment_id <= 0) {
    die('Invalid payment ID.');
}
// Fetch payment details
$sql = "SELECT p.*, s.first_name, s.last_name, s.class FROM payments p LEFT JOIN students s ON p.student_id = s.id WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $payment_id);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
if (!$payment) {
    die('Payment not found.');
}
// Fetch fee breakdown (if using payment_allocations table)
$allocations = [];
$alloc_sql = "SELECT pa.*, f.name AS fee_name, f.fee_type, sf.term AS sf_term, sf.academic_year AS sf_academic_year
             FROM payment_allocations pa
             LEFT JOIN student_fees sf ON pa.student_fee_id = sf.id
             LEFT JOIN fees f ON sf.fee_id = f.id
             WHERE pa.payment_id = ?";
$alloc_stmt = $conn->prepare($alloc_sql);
$alloc_stmt->bind_param('i', $payment_id);
$alloc_stmt->execute();
$alloc_result = $alloc_stmt->get_result();
while ($row = $alloc_result->fetch_assoc()) {
    $allocations[] = $row;
}
// Calculate total paid and outstanding (if needed)
$total_paid = $payment['amount'];
// Calculate outstanding balance for the student
$student_id = $payment['student_id'];
$outstanding = 0;
if ($student_id) {
    // Sum all assigned fees for the student
    $fees_sql = "SELECT COALESCE(SUM(amount),0) as total_due FROM student_fees WHERE student_id = ? AND status != 'cancelled'";
    $fees_stmt = $conn->prepare($fees_sql);
    $fees_stmt->bind_param('i', $student_id);
    $fees_stmt->execute();
    $fees_result = $fees_stmt->get_result();
    $total_due = $fees_result->fetch_assoc()['total_due'];
    // Sum all payments made by the student
    $paid_sql = "SELECT COALESCE(SUM(amount),0) as total_paid FROM payments WHERE student_id = ?";
    $paid_stmt = $conn->prepare($paid_sql);
    $paid_stmt->bind_param('i', $student_id);
    $paid_stmt->execute();
    $paid_result = $paid_stmt->get_result();
    $total_paid = $paid_result->fetch_assoc()['total_paid'];
    $outstanding = max(0, $total_due - $total_paid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Receipt - Salba Montessori</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-body">
    <div class="receipt-container">
        <div class="receipt-header">
            <div class="mb-2">
                 <img src="../img/salba_logo.jpg" alt="Salba Montessori Logo" class="logo-circle logo-md">
            </div>
            <div class="receipt-title"><i class="fas fa-receipt me-2"></i>Payment Receipt</div>
     
        </div>
        <div class="receipt-details">
            <div><strong>Receipt No:</strong> <?php echo htmlspecialchars($payment['receipt_no']); ?></div>
            <div><strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
            <div><strong>Student:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
            <div><strong>Class:</strong> <?php echo htmlspecialchars($payment['class']); ?></div>
            <div><strong>Term:</strong> <?php echo htmlspecialchars($payment['term'] ?? ''); ?></div>
            <div><strong>Academic Year:</strong> <?php echo htmlspecialchars(!empty($payment['academic_year']) ? formatAcademicYearDisplay($conn, $payment['academic_year']) : ''); ?></div>
        </div>
        <table class="table receipt-table">
            <thead>
                <tr>
                    <th>Fee/Category</th>
                    <th>Term</th>
                    <th>Year</th>
                    <th>Amount Paid (GH₵)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($allocations)): ?>
                    <?php foreach ($allocations as $alloc): ?>
                        <tr>
                            <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($alloc['fee_name']); ?></span></td>
                            <td><?php echo htmlspecialchars($alloc['sf_term'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars(!empty($alloc['sf_academic_year']) ? formatAcademicYearDisplay($conn, $alloc['sf_academic_year']) : ''); ?></td>
                            <td class="fw-bold"><?php echo number_format($alloc['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td><?php echo htmlspecialchars($payment['term'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars(!empty($payment['academic_year']) ? formatAcademicYearDisplay($conn, $payment['academic_year']) : ''); ?></td>
                        <td><?php echo number_format($payment['amount'], 2); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="2" class="text-end">Total Paid</td>
                    <td><?php echo number_format($total_paid, 2); ?></td>
                </tr>
                <tr class="outstanding-row">
                    <td colspan="2" class="text-end"><i class="fas fa-exclamation-triangle me-2"></i>Outstanding Balance</td>
                    <td>GH₵<?php echo number_format($outstanding, 2); ?></td>
                </tr>
            </tfoot>
        </table>
        <div class="mt-3 text-center receipt-brand fw-bold text-primary">
            <span class="fw-bold text-primary">Thank you for your payment!</span>
        </div>
        <button class="clean-btn-primary clean-btn-lg no-print w-100" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Receipt
        </button>
    </div>
</body>
</html>
