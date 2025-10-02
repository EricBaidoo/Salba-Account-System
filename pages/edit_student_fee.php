<?php
/**
 * Edit Student Fee Handler
 * Handles updating of fee assignment details for individual students
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

// Get form data
$student_fee_id = intval($_POST['student_fee_id'] ?? 0);
$student_id = intval($_POST['student_id'] ?? 0);
$amount = floatval($_POST['amount'] ?? 0);
$due_date = $_POST['due_date'] ?? '';
$term = trim($_POST['term'] ?? '');
$status = $_POST['status'] ?? 'pending';
$notes = trim($_POST['notes'] ?? '');

// Validate inputs
if ($student_fee_id === 0 || $student_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid fee or student ID']);
    exit;
}

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero']);
    exit;
}

if (empty($due_date)) {
    echo json_encode(['success' => false, 'message' => 'Due date is required']);
    exit;
}

// Validate date format
$date = DateTime::createFromFormat('Y-m-d', $due_date);
if (!$date || $date->format('Y-m-d') !== $due_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Validate status
$valid_statuses = ['pending', 'due', 'overdue', 'paid', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Start transaction
    $conn->autocommit(false);
    
    // First, verify the fee belongs to the student and get current details
    $check_sql = "SELECT sf.id, sf.student_id, sf.fee_id, sf.amount as current_amount, 
                         sf.status as current_status, f.name as fee_name, 
                         s.first_name, s.last_name
                  FROM student_fees sf 
                  JOIN fees f ON sf.fee_id = f.id 
                  JOIN students s ON sf.student_id = s.id 
                  WHERE sf.id = ? AND sf.student_id = ?";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $student_fee_id, $student_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $current_fee = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$current_fee) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Fee assignment not found or does not belong to this student']);
        exit;
    }
    
    // Check if fee is already paid - only allow certain changes for paid fees
    if ($current_fee['current_status'] === 'paid') {
        // For paid fees, only allow notes and term changes, not amount or due date
        if ($amount != $current_fee['current_amount']) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Cannot change amount for a paid fee. Please process a refund first.']);
            exit;
        }
        
        if ($status !== 'paid') {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Cannot change status of a paid fee.']);
            exit;
        }
    }
    
    // Check if there are payments that exceed the new amount
    $payment_check_sql = "SELECT SUM(amount) as total_paid FROM payments WHERE student_id = ? AND fee_id = ?";
    $payment_stmt = $conn->prepare($payment_check_sql);
    $payment_stmt->bind_param("ii", $student_id, $current_fee['fee_id']);
    $payment_stmt->execute();
    $payment_result = $payment_stmt->get_result();
    $payment_data = $payment_result->fetch_assoc();
    $payment_stmt->close();
    
    $total_paid = floatval($payment_data['total_paid'] ?? 0);
    
    if ($total_paid > $amount) {
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => "Cannot set amount to GH₵" . number_format($amount, 2) . " because student has already paid GH₵" . number_format($total_paid, 2) . " towards this fee."
        ]);
        exit;
    }
    
    // Update the fee assignment
    $update_sql = "UPDATE student_fees 
                   SET amount = ?, due_date = ?, term = ?, status = ?, notes = ?
                   WHERE id = ? AND student_id = ?";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("dssssii", $amount, $due_date, $term, $status, $notes, $student_fee_id, $student_id);
    
    if ($update_stmt->execute()) {
        $updated_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        if ($updated_rows > 0) {
            // Log the action (optional - you can create an audit log table)
            /*
            $log_sql = "INSERT INTO audit_log (action, table_name, record_id, details, user_id, created_at) 
                       VALUES ('UPDATE', 'student_fees', ?, ?, ?, NOW())";
            
            $log_stmt = $conn->prepare($log_sql);
            $details = json_encode([
                'student_name' => $current_fee['first_name'] . ' ' . $current_fee['last_name'],
                'fee_name' => $current_fee['fee_name'],
                'old_amount' => $current_fee['current_amount'],
                'new_amount' => $amount,
                'due_date' => $due_date,
                'term' => $term,
                'status' => $status,
                'notes' => $notes,
                'action' => 'Fee assignment updated'
            ]);
            $user_id = $_SESSION['user_id'] ?? 1;
            $log_stmt->bind_param("isi", $student_fee_id, $details, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            */
            
            // Commit the transaction
            $conn->commit();
            
            // Determine if status needs updating based on payments
            $balance = $amount - $total_paid;
            $auto_status = '';
            if ($balance <= 0) {
                $auto_status = ' (Status automatically updated to paid)';
                // Update status to paid if fully paid
                $status_update = $conn->prepare("UPDATE student_fees SET status = 'paid' WHERE id = ?");
                $status_update->bind_param("i", $student_fee_id);
                $status_update->execute();
                $status_update->close();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Fee '{$current_fee['fee_name']}' has been successfully updated for {$current_fee['first_name']} {$current_fee['last_name']}.{$auto_status}"
            ]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'No changes were made to the fee assignment']);
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