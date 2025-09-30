<?php
include '../includes/db_connect.php';
include '../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_mode = $_POST['payment_mode'] ?? 'student';
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $receipt_no = trim($_POST['receipt_no']);
    $description = trim($_POST['description']);
    $created_by = $_SESSION['user_id'] ?? null;

    if ($payment_mode === 'general') {
        // General/category payment (not tied to student)
        $fee_id = intval($_POST['fee_id'] ?? 0);
        if ($fee_id <= 0) {
            echo "<div class='alert alert-danger'>No fee category selected.</div>";
            exit;
        }
        // Validate fee_id exists in fees table
        $fee_check = $conn->prepare("SELECT id FROM fees WHERE id = ?");
        $fee_check->bind_param("i", $fee_id);
        $fee_check->execute();
        $fee_check->store_result();
        if ($fee_check->num_rows === 0) {
            echo "<div class='alert alert-danger'>Invalid fee category selected.</div>";
            $fee_check->close();
            exit;
        }
        $fee_check->close();
        $stmt = $conn->prepare("INSERT INTO payments (amount, payment_date, receipt_no, description, payment_type, fee_id) VALUES (?, ?, ?, ?, 'general', ?)");
        $stmt->bind_param("dsssi", $amount, $payment_date, $receipt_no, $description, $fee_id);
        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>General/category payment recorded successfully!</div>";
        } else {
            echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        // Student payment (default)
        $student_id = intval($_POST['student_id']);
        if ($student_id <= 0) {
            echo "<div class='alert alert-danger'>No student selected.</div>";
            exit;
        }
    $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, receipt_no, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $student_id, $amount, $payment_date, $receipt_no, $description);
        if ($stmt->execute()) {
            $payment_id = $conn->insert_id;
            $remaining = $amount;
            // Fetch all student_fees for this student that are not fully paid, ordered by due_date
            $fees_stmt = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND status != 'paid' ORDER BY due_date ASC, id ASC");
            $fees_stmt->bind_param("i", $student_id);
            $fees_stmt->execute();
            $fees_result = $fees_stmt->get_result();
            while ($fee = $fees_result->fetch_assoc()) {
                $fee_id = $fee['id'];
                $fee_amount = floatval($fee['amount']);
                $already_paid = floatval($fee['amount_paid']);
                $to_pay = min($fee_amount - $already_paid, $remaining);
                if ($to_pay > 0) {
                    // Update amount_paid and status
                    $new_paid = $already_paid + $to_pay;
                    $new_status = ($new_paid >= $fee_amount) ? 'paid' : 'pending';
                    $update_stmt = $conn->prepare("UPDATE student_fees SET amount_paid = ?, status = ? WHERE id = ?");
                    $update_stmt->bind_param("dsi", $new_paid, $new_status, $fee_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                    // Record allocation
                    $alloc_stmt = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                    $alloc_stmt->bind_param("iid", $payment_id, $fee_id, $to_pay);
                    $alloc_stmt->execute();
                    $alloc_stmt->close();
                    $remaining -= $to_pay;
                    if ($remaining <= 0) break;
                }
            }
            $fees_stmt->close();
            echo "<div class='alert alert-success'>Payment recorded and allocated successfully!";
            if ($amount != $remaining) {
                echo "<br>Updated student fee records.";
            }
            if ($remaining > 0) {
                echo "<br>Unallocated amount: GH₵" . number_format($remaining, 2);
            }
            echo "</div>";
        } else {
            echo "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container mt-5">
        <a href="record_payment_form.php" class="btn btn-secondary mb-3">Back to Payment Form</a>
        <a href="view_payments.php" class="btn btn-info mb-3">View All Payments</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>