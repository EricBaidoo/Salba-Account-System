<?php
/**
 * Unassign Fee Handler
 * Handles the removal of fee assignments from students
 */

include '../includes/db_connect.php';
include '../includes/auth_functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$student_fee_id = intval($input['student_fee_id'] ?? 0);
$student_id = intval($input['student_id'] ?? 0);

// Validate inputs
if ($student_fee_id === 0 || $student_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid fee or student ID']);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // First, verify the fee belongs to the student and get fee details
    $check_sql = "SELECT sf.id, sf.student_id, sf.fee_id, sf.amount, f.name as fee_name, 
                         s.first_name, s.last_name, sf.status
                  FROM student_fees sf 
                  JOIN fees f ON sf.fee_id = f.id 
                  JOIN students s ON sf.student_id = s.id 
                  WHERE sf.id = ? AND sf.student_id = ?";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $student_fee_id, $student_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $fee_details = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$fee_details) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Fee assignment not found or does not belong to this student']);
        exit;
    }
    
    // Check if fee has been paid
    if ($fee_details['status'] === 'paid') {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Cannot unassign a paid fee. Please process a refund instead.']);
        exit;
    }
    
    // Check if there are any payments made for this fee
    $payment_check_sql = "SELECT SUM(amount) as total_paid FROM payments WHERE student_id = ? AND fee_id = ?";
    $payment_stmt = $conn->prepare($payment_check_sql);
    $payment_stmt->bind_param("ii", $student_id, $fee_details['fee_id']);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    $payment_data = $payment_result->fetch_assoc();
    $payment_stmt->close();
    
    $total_paid = floatval($payment_data['total_paid'] ?? 0);
    
    if ($total_paid > 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => "Cannot unassign fee. Student has already paid GH₵" . number_format($total_paid, 2) . " towards this fee. Please process a refund instead."
        ]);
        exit;
    }
    
    // Delete the fee assignment
    $delete_sql = "DELETE FROM student_fees WHERE id = ? AND student_id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("ii", $student_fee_id, $student_id);
    
    if ($delete_stmt->execute()) {
        $deleted_rows = $delete_stmt->affected_rows;
        $delete_stmt->close();
        
        if ($deleted_rows > 0) {
            // Log the action (optional - you can create an audit log table)
            $log_sql = "INSERT INTO audit_log (action, table_name, record_id, details, user_id, created_at) 
                       VALUES ('DELETE', 'student_fees', ?, ?, ?, NOW())";
            
            // For now, we'll skip logging if the audit_log table doesn't exist
            // You can uncomment this if you want to add audit logging
            /*
            $log_stmt = $conn->prepare($log_sql);
            $details = json_encode([
                'student_name' => $fee_details['first_name'] . ' ' . $fee_details['last_name'],
                'fee_name' => $fee_details['fee_name'],
                'amount' => $fee_details['amount'],
                'reason' => 'Fee unassigned by admin'
            ]);
            $user_id = $_SESSION['user_id'] ?? 1; // Default to 1 if session doesn't have user_id
            $log_stmt->bind_param("isi", $student_fee_id, $details, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            */
            
            // Commit the transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "Fee '{$fee_details['fee_name']}' (GH₵" . number_format($fee_details['amount'], 2) . ") has been successfully unassigned from {$fee_details['first_name']} {$fee_details['last_name']}."
            ]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Fee assignment could not be removed']);
        }
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $conn->autocommit(true);
}
?>