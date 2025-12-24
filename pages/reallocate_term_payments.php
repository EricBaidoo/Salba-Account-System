<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';
include '../includes/system_settings.php';
include '../includes/student_balance_functions.php';

$student_id = intval($_GET['student_id'] ?? 0);
$term = trim($_GET['term'] ?? '');
$academic_year = trim($_GET['academic_year'] ?? '');

if ($academic_year === '') { $academic_year = getAcademicYear($conn); }

if ($student_id <= 0 || $term === '') {
    echo "<div class='alert alert-danger'>Missing student_id or term.</div>";
    exit;
}

// Ensure arrears row exists before reallocation
ensureArrearsAssignment($conn, $student_id, $term, $academic_year);

$conn->begin_transaction();

try {
    // Reset current term fees for the student
    $reset = $conn->prepare("UPDATE student_fees SET amount_paid = 0, status = 'pending' WHERE student_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) AND status != 'cancelled'");
    $reset->bind_param('iss', $student_id, $term, $academic_year);
    $reset->execute();
    $reset->close();

    // Fetch payments for this student in this term/year
    $pstmt = $conn->prepare("SELECT id, amount FROM payments WHERE student_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) ORDER BY payment_date ASC, id ASC");
    $pstmt->bind_param('iss', $student_id, $term, $academic_year);
    $pstmt->execute();
    $pres = $pstmt->get_result();

    // Get arrears fee id if any
    $arrears_fee_id = getArrearsFeeId($conn);

    while ($p = $pres->fetch_assoc()) {
        $payment_id = intval($p['id']);
        $remaining = floatval($p['amount']);

        // Clear existing allocations for this payment
        $del = $conn->prepare("DELETE FROM payment_allocations WHERE payment_id = ?");
        $del->bind_param('i', $payment_id);
        $del->execute();
        $del->close();

        // Allocate to arrears first
        if ($arrears_fee_id) {
            $arrq = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND fee_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) AND status != 'cancelled' LIMIT 1");
            $arrq->bind_param('iiss', $student_id, $arrears_fee_id, $term, $academic_year);
            $arrq->execute();
            $arrres = $arrq->get_result();
            if ($fee = $arrres->fetch_assoc()) {
                $fid = intval($fee['id']);
                $due = max(0, floatval($fee['amount']) - floatval($fee['amount_paid']));
                $to_pay = min($due, $remaining);
                if ($to_pay > 0) {
                    $upd = $conn->prepare("UPDATE student_fees SET amount_paid = amount_paid + ?, status = CASE WHEN amount_paid + ? >= amount THEN 'paid' ELSE 'pending' END WHERE id = ?");
                    $upd->bind_param('ddi', $to_pay, $to_pay, $fid);
                    $upd->execute();
                    $upd->close();

                    $ins = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                    $ins->bind_param('iid', $payment_id, $fid, $to_pay);
                    $ins->execute();
                    $ins->close();
                    $remaining -= $to_pay;
                }
            }
            $arrq->close();
        }

        // Allocate to remaining fees by due_date
        $fq = $conn->prepare("SELECT id, amount, amount_paid FROM student_fees WHERE student_id = ? AND term = ? AND (academic_year = ? OR academic_year IS NULL) AND status != 'cancelled' ORDER BY due_date ASC, id ASC");
        $fq->bind_param('iss', $student_id, $term, $academic_year);
        $fq->execute();
        $fres = $fq->get_result();
        while ($remaining > 0 && ($fee = $fres->fetch_assoc())) {
            $fid = intval($fee['id']);
            // Skip arrears if already covered above
            if ($arrears_fee_id && isset($fee['fee_id']) && intval($fee['fee_id']) === $arrears_fee_id) {
                // We didn't select fee_id in this query; safe to proceed without check
            }
            $due = max(0, floatval($fee['amount']) - floatval($fee['amount_paid']));
            $to_pay = min($due, $remaining);
            if ($to_pay > 0) {
                $upd = $conn->prepare("UPDATE student_fees SET amount_paid = amount_paid + ?, status = CASE WHEN amount_paid + ? >= amount THEN 'paid' ELSE 'pending' END WHERE id = ?");
                $upd->bind_param('ddi', $to_pay, $to_pay, $fid);
                $upd->execute();
                $upd->close();

                $ins = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                $ins->bind_param('iid', $payment_id, $fid, $to_pay);
                $ins->execute();
                $ins->close();
                $remaining -= $to_pay;
            }
        }
        $fq->close();
    }

    $pstmt->close();
    $conn->commit();
    echo "<div class='alert alert-success'>Reallocation completed. <a href='student_balance_details.php?id=" . $student_id . "&term=" . urlencode($term) . "&academic_year=" . urlencode($academic_year) . "&debug=1'>Back to details</a></div>";
} catch (Exception $e) {
    $conn->rollback();
    echo "<div class='alert alert-danger'>Error during reallocation: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>
