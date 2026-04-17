<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
    exit;
}
require_finance_access();

// Helper function to calculate fee amount
function calculateFeeAmount($conn, $fee_id, $student_class) {
    $fee_stmt = $conn->prepare("SELECT fee_type, amount FROM fees WHERE id = ?");
    $fee_stmt->bind_param("i", $fee_id);
    $fee_stmt->execute();
    $fee_result = $fee_stmt->get_result();
    $fee_info = $fee_result->fetch_assoc();
    $fee_stmt->close();
    
    if (!$fee_info) return null;
    
    if ($fee_info['fee_type'] === 'fixed') {
        return $fee_info['amount'];
    } else if ($fee_info['fee_type'] === 'class_based') {
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND class_name = ?");
        $amount_stmt->bind_param("is", $fee_id, $student_class);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        return $amount_row ? $amount_row['amount'] : null;
    } else {
        $level_stmt = $conn->prepare("SELECT Level FROM classes WHERE name = ? LIMIT 1");
        $level_stmt->bind_param("s", $student_class);
        $level_stmt->execute();
        $level_result = $level_stmt->get_result();
        $level_row = $level_result->fetch_assoc();
        $level_stmt->close();
        
        $category = $level_row ? $level_row['Level'] : null;
        if (!$category) return null;
        
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND category = ?");
        $amount_stmt->bind_param("is", $fee_id, $category);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        return $amount_row ? $amount_row['amount'] : null;
    }
}

// Get form data
$semester = $_POST['semester'] ?? '';
$academic_year = trim($_POST['academic_year'] ?? '');
if ($academic_year === '') {
    $academic_year = getAcademicYear($conn);
}
$due_date = $_POST['due_date'] ?? '';
$class_filter = $_POST['class_filter'] ?? 'all';
$selected_fees = $_POST['selected_fees'] ?? '';

if (empty($semester) || empty($due_date) || empty($selected_fees)) {
    die('Missing required fields');
}

$fee_ids = array_filter(array_map('intval', explode(',', $selected_fees)));
if (empty($fee_ids)) {
    die('No fees selected');
}

// Get students based on class filter
$student_query = "SELECT id, first_name, last_name, class FROM students WHERE status = 'active'";
if ($class_filter !== 'all') {
    $student_query .= " AND class = ?";
}
$student_query .= " ORDER BY class, last_name, first_name";

$student_stmt = $conn->prepare($student_query);
if ($class_filter !== 'all') {
    $student_stmt->bind_param("s", $class_filter);
}
$student_stmt->execute();
$students_result = $student_stmt->get_result();

// Process assignments
$conn->begin_transaction();
try {
    $assigned_count = 0;
    $skipped_count = 0;
    
    $insert_stmt = $conn->prepare(
        "INSERT INTO student_fees (student_id, fee_id, due_date, amount, semester, academic_year, assigned_date, status) 
         VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')"
    );
    
    while ($student = $students_result->fetch_assoc()) {
        foreach ($fee_ids as $fee_id) {
            // Check if already assigned
            $check_stmt = $conn->prepare(
                "SELECT id FROM student_fees 
                 WHERE student_id = ? AND fee_id = ? AND semester = ? AND academic_year = ? AND status != 'cancelled'"
            );
            $check_stmt->bind_param("iiss", $student['id'], $fee_id, $semester, $academic_year);
            $check_stmt->execute();
            $already_exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();
            
            if ($already_exists) {
                $skipped_count++;
                continue;
            }
            
            // Calculate amount
            $amount = calculateFeeAmount($conn, $fee_id, $student['class']);
            if ($amount === null) continue;
            $amount = floatval($amount);
            
            // Insert assignment
            $insert_stmt->bind_param("iisdss", $student['id'], $fee_id, $due_date, $amount, $semester, $academic_year);
            if ($insert_stmt->execute()) {
                $assigned_count++;
            }
        }
    }
    
    $insert_stmt->close();
    $conn->commit();
    
    // Redirect back to billing hub with results
    header("Location: view_semester_bills.php?generated=1&count=$assigned_count&skipped=$skipped_count");
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    die('Error: ' . $e->getMessage());
}
?>
