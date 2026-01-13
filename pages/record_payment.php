<?php
include '../includes/db_connect.php';
include '../includes/auth_check.php';
include '../includes/system_settings.php';
include '../includes/student_balance_functions.php';

// Ensure receipt numbers are unique; generate when missing
function ensureUniqueReceiptNo(mysqli $conn, string $receipt_no): string {
    $base = $receipt_no !== '' ? $receipt_no : ('RCPT-' . date('Ymd') . '-' . random_int(1000, 9999));
    $candidate = $base;
    $suffix = 1;
    while (true) {
        $check = $conn->prepare("SELECT id FROM payments WHERE receipt_no = ? LIMIT 1");
        $check->bind_param('s', $candidate);
        $check->execute();
        $check->store_result();
        $exists = $check->num_rows > 0;
        $check->close();
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $suffix;
        $suffix++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_mode = $_POST['payment_mode'] ?? 'student';
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $receipt_no = trim($_POST['receipt_no']);
    $description = trim($_POST['description']);
    $term = trim($_POST['term'] ?? ''); // Capture term from form
    $created_by = $_SESSION['user_id'] ?? null;
    $academic_year = trim($_POST['academic_year'] ?? '');
    if ($academic_year === '') { $academic_year = getAcademicYear($conn); }

    // Basic validation for required fields
    if ($term === '') {
        echo "<div class='alert alert-danger'>Please select a term for this payment.</div>";
        exit;
    }
    if ($academic_year === '') {
        echo "<div class='alert alert-danger'>Please select an academic year for this payment.</div>";
        exit;
    }
    if ($amount <= 0) {
        echo "<div class='alert alert-danger'>Payment amount must be greater than zero.</div>";
        exit;
    }

    // Normalize/ensure unique receipt number
    $receipt_no = ensureUniqueReceiptNo($conn, $receipt_no);

    if ($payment_mode === 'general') {
        // General payment (not tied to student) - fee_id is optional
        $fee_id = intval($_POST['fee_id'] ?? 0);
        
        // If fee_id is provided, validate it exists
        if ($fee_id > 0) {
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
            // Insert with fee_id
            $stmt = $conn->prepare("INSERT INTO payments (amount, payment_date, receipt_no, description, payment_type, fee_id, term, academic_year) VALUES (?, ?, ?, ?, 'general', ?, ?, ?)");
            $stmt->bind_param("dsssiss", $amount, $payment_date, $receipt_no, $description, $fee_id, $term, $academic_year);
        } else {
            // Insert without fee_id (pure general payment)
            $stmt = $conn->prepare("INSERT INTO payments (amount, payment_date, receipt_no, description, payment_type, term, academic_year) VALUES (?, ?, ?, ?, 'general', ?, ?)");
            $stmt->bind_param("dsssss", $amount, $payment_date, $receipt_no, $description, $term, $academic_year);
        }
        
        if ($stmt->execute()) {
            echo "<div class='alert alert-success'>General payment recorded successfully!</div>";
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

        try {
            $conn->begin_transaction();

            // Ensure arrears carry-forward exists in this term/year before allocating payment
            ensureArrearsAssignment($conn, $student_id, $term, $academic_year);

            $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, receipt_no, description, term, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idsssss", $student_id, $amount, $payment_date, $receipt_no, $description, $term, $academic_year);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $payment_id = $conn->insert_id;
            $stmt->close();

            $remaining = $amount;
            // Allocation scope setting: 'global' or 'term_year' (default to term_year for correctness)
            $alloc_scope = getSystemSetting($conn, 'payment_allocation_scope', 'term_year');

            // Allocate arrears first
            $arrears_fee_id = getArrearsFeeId($conn);
            $processed_arrears_fee_id = null;
            if ($arrears_fee_id) {
                if ($alloc_scope === 'term_year') {
                    $arr_stmt = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND fee_id = ? AND status != 'paid' AND term = ? AND (academic_year = ? OR (academic_year IS NULL AND ? = '')) LIMIT 1");
                    $arr_stmt->bind_param("iisss", $student_id, $arrears_fee_id, $term, $academic_year, $academic_year);
                } else {
                    $arr_stmt = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND fee_id = ? AND status != 'paid' LIMIT 1");
                    $arr_stmt->bind_param("ii", $student_id, $arrears_fee_id);
                }
                $arr_stmt->execute();
                $arr_res = $arr_stmt->get_result();
                if ($fee = $arr_res->fetch_assoc()) {
                    $fee_id = $fee['id'];
                    $processed_arrears_fee_id = $fee_id;
                    $fee_amount = floatval($fee['amount']);
                    $already_paid = floatval($fee['amount_paid']);
                    $to_pay = min($fee_amount - $already_paid, $remaining);
                    if ($to_pay > 0) {
                        $new_paid = $already_paid + $to_pay;
                        $new_status = ($new_paid >= $fee_amount) ? 'paid' : 'pending';
                        $update_stmt = $conn->prepare("UPDATE student_fees SET amount_paid = ?, status = ? WHERE id = ?");
                        $update_stmt->bind_param("dsi", $new_paid, $new_status, $fee_id);
                        if (!$update_stmt->execute()) { throw new Exception($update_stmt->error); }
                        $update_stmt->close();

                        $alloc_stmt = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                        $alloc_stmt->bind_param("iid", $payment_id, $fee_id, $to_pay);
                        if (!$alloc_stmt->execute()) { throw new Exception($alloc_stmt->error); }
                        $alloc_stmt->close();
                        $remaining -= $to_pay;
                    }
                }
                $arr_stmt->close();
            }

            // Then allocate to the rest in due_date order
            if ($alloc_scope === 'term_year') {
                $fees_stmt = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND status != 'paid' AND term = ? AND (academic_year = ? OR (academic_year IS NULL AND ? = '')) ORDER BY due_date ASC, id ASC");
                $fees_stmt->bind_param("isss", $student_id, $term, $academic_year, $academic_year);
            } else {
                $fees_stmt = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND status != 'paid' ORDER BY due_date ASC, id ASC");
                $fees_stmt->bind_param("i", $student_id);
            }
            $fees_stmt->execute();
            $fees_result = $fees_stmt->get_result();
            while ($remaining > 0 && ($fee = $fees_result->fetch_assoc())) {
                $fee_id = $fee['id'];
                // Skip arrears fee if already processed
                if ($processed_arrears_fee_id && $fee_id === $processed_arrears_fee_id) {
                    continue;
                }
                $fee_amount = floatval($fee['amount']);
                $already_paid = floatval($fee['amount_paid']);
                $to_pay = min($fee_amount - $already_paid, $remaining);
                if ($to_pay > 0) {
                    $new_paid = $already_paid + $to_pay;
                    $new_status = ($new_paid >= $fee_amount) ? 'paid' : 'pending';
                    $update_stmt = $conn->prepare("UPDATE student_fees SET amount_paid = ?, status = ? WHERE id = ?");
                    $update_stmt->bind_param("dsi", $new_paid, $new_status, $fee_id);
                    if (!$update_stmt->execute()) { throw new Exception($update_stmt->error); }
                    $update_stmt->close();

                    $alloc_stmt = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                    $alloc_stmt->bind_param("iid", $payment_id, $fee_id, $to_pay);
                    if (!$alloc_stmt->execute()) { throw new Exception($alloc_stmt->error); }
                    $alloc_stmt->close();
                    $remaining -= $to_pay;
                }
            }
            $fees_stmt->close();

            $conn->commit();

            echo "<div class='alert alert-success'>Payment recorded and allocated successfully!";
            if ($amount != $remaining) {
                echo "<br>Updated student fee records.";
            }
            if ($remaining > 0) {
                echo "<br>Unallocated amount: GH₵" . number_format($remaining, 2);
            }
            echo "</div>";
        } catch (Exception $e) {
            $conn->rollback();
            echo "<div class='alert alert-danger'>Error processing payment: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
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