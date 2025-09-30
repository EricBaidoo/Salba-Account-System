<?php
include '../includes/db_connect.php';
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
$alloc_sql = "SELECT pa.*, f.name AS fee_name, f.fee_type FROM payment_allocations pa LEFT JOIN student_fees sf ON pa.student_fee_id = sf.id LEFT JOIN fees f ON sf.fee_id = f.id WHERE pa.payment_id = ?";
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
    <style>
        body {
            background: #f7f7fa;
            min-height: 100vh;
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        .receipt-container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.10);
            padding: 40px 32px 32px 32px;
            position: relative;
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 32px;
            position: relative;
            border-bottom: 2px solid #e9ecef;
        }
        .receipt-logo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px auto;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
        }
        .receipt-title {
            font-size: 2.1rem;
            font-weight: 700;
            color: #222;
            letter-spacing: 1px;
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        .receipt-brand {
            font-size: 1.15rem;
            color: #555;
            font-weight: 600;
            margin-bottom: 6px;
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        .receipt-info {
            margin-bottom: 24px;
            background: #f8f9fa;
            border-radius: 12px;
            padding: 18px 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .receipt-info div {
            font-size: 1rem;
            margin-bottom: 4px;
            color: #222;
        }
        .receipt-table {
            margin-bottom: 18px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .receipt-table th {
            background: #222;
            color: #fff;
            font-weight: 600;
            border: none;
            font-size: 1rem;
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        .receipt-table td {
            background: #fff;
            border: none;
            font-size: 1rem;
            color: #222;
        }
        .total-row {
            background: #f1f3f6;
            font-weight: bold;
            color: #222;
            font-size: 1.1rem;
        }
        .outstanding-row td {
            background: #fff3f3;
            font-weight: bold;
            color: #e53e3e !important;
            font-size: 1.1rem;
        }
        .print-btn {
            margin-top: 24px;
            background: #222;
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 28px;
            font-weight: 600;
            font-size: 1.05rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            transition: all 0.2s;
        }
        .print-btn:hover {
            background: #444;
            color: #fff;
            box-shadow: 0 8px 24px rgba(0,0,0,0.13);
        }
        @media print { .print-btn { display: none; } .receipt-container { box-shadow: none; margin: 0; } }
        @media (max-width: 700px) { .receipt-container { padding: 18px 6px; } }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <div class="mb-2">
                 <img src="../img/salba_logo.jpg" alt="Salba Montessori Logo" style="width:90px; height:90px; border-radius:50%; box-shadow:0 4px 16px rgba(0,0,0,0.10); background:#fff; object-fit:cover;">
            </div>
            <div class="receipt-title"><i class="fas fa-receipt me-2"></i>Payment Receipt</div>
     
        </div>
        <div class="receipt-info">
            <div><strong>Receipt No:</strong> <?php echo htmlspecialchars($payment['receipt_no']); ?></div>
            <div><strong>Date:</strong> <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></div>
            <div><strong>Student:</strong> <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
            <div><strong>Class:</strong> <?php echo htmlspecialchars($payment['class']); ?></div>
        </div>
        <table class="table receipt-table">
            <thead>
                <tr>
                    <th>Fee/Category</th>
                    <th>Term</th>
                    <th>Amount Paid (GH₵)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($allocations)): ?>
                    <?php foreach ($allocations as $alloc): ?>
                        <tr>
                            <td><span class="fw-bold text-primary"><?php echo htmlspecialchars($alloc['fee_name']); ?></span></td>
                            <td>-</td>
                            <td class="fw-bold"><?php echo number_format($alloc['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><?php echo htmlspecialchars($payment['description']); ?></td>
                        <td>-</td>
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
        <button class="print-btn w-100" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print Receipt
        </button>
    </div>
</body>
</html>
