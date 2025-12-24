<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
$payment_id = intval($_GET['payment_id'] ?? 0);
$payment = null;
if ($payment_id) {
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();
}
if (!$payment) {
    echo '<div class="alert alert-danger">Payment not found.</div>';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $receipt_no = trim($_POST['receipt_no']);
    $description = trim($_POST['description']);
    $stmt = $conn->prepare("UPDATE payments SET amount = ?, payment_date = ?, receipt_no = ?, description = ? WHERE id = ?");
    $stmt->bind_param("dsssi", $amount, $payment_date, $receipt_no, $description, $payment_id);
    if ($stmt->execute()) {
        // Proportional adjustment for multiple allocations
        $alloc_stmt = $conn->prepare("SELECT id, amount FROM payment_allocations WHERE payment_id = ?");
        $alloc_stmt->bind_param("i", $payment_id);
        $alloc_stmt->execute();
        $alloc_result = $alloc_stmt->get_result();
        $allocs = [];
        $old_total = 0;
        while ($row = $alloc_result->fetch_assoc()) {
            $allocs[] = $row;
            $old_total += $row['amount'];
        }
        $alloc_stmt->close();
        if ($old_total > 0 && count($allocs) > 0) {
            foreach ($allocs as $alloc) {
                $new_alloc = round($alloc['amount'] * ($amount / $old_total), 2);
                $update_alloc = $conn->prepare("UPDATE payment_allocations SET amount = ? WHERE id = ?");
                $update_alloc->bind_param("di", $new_alloc, $alloc['id']);
                $update_alloc->execute();
                $update_alloc->close();
            }
        }
        header('Location: student_balance_details.php?id=' . $payment['student_id']);
        exit;
    } else {
        echo '<div class="alert alert-danger">Error: ' . $stmt->error . '</div>';
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Payment</h2>
    <form method="POST">
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="payment_date" class="form-label">Payment Date</label>
            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="receipt_no" class="form-label">Receipt No</label>
            <input type="text" class="form-control" id="receipt_no" name="receipt_no" value="<?php echo htmlspecialchars($payment['receipt_no']); ?>">
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?php echo htmlspecialchars($payment['description']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Update Payment</button>
        <a href="student_balance_details.php?id=<?php echo $payment['student_id']; ?>" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
