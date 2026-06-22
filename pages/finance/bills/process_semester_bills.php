<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';
include_once '../../../includes/accounting_engine.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
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
    
    // Get the Waiver fee ID
    $discount_fee_id = null;
    $df_res = $conn->query("SELECT id FROM fees WHERE name = 'Waivers & Scholarships' LIMIT 1");
    if ($df_res && $df_res->num_rows > 0) {
        $discount_fee_id = $df_res->fetch_assoc()['id'];
    }
    
    $schol_stmt = $conn->prepare("SELECT s.name, s.discount_type, s.discount_value, s.applies_to_fees FROM student_scholarships ss JOIN scholarships s ON ss.scholarship_id = s.id WHERE ss.student_id = ? AND ss.status = 'active' AND s.status = 'active'");
    $insert_discount_stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, due_date, amount, semester, academic_year, notes, assigned_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");

    while ($student = $students_result->fetch_assoc()) {
        $billed_fee_amounts = [];
        $any_billed = false;
        
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
                $billed_fee_amounts[$fee_id] = $amount;
                $any_billed = true;
            }
        }
        
        // Apply Scholarships if billed this cycle
        if ($any_billed && $discount_fee_id) {
            $schol_stmt->bind_param("i", $student['id']);
            $schol_stmt->execute();
            $schols = $schol_stmt->get_result();
            
            while ($schol = $schols->fetch_assoc()) {
                $target_fees = json_decode($schol['applies_to_fees'] ?? '[]', true) ?: [];
                
                $target_amount = 0;
                if (empty($target_fees)) {
                    // Applies to all billed fees if none specified
                    $target_amount = array_sum($billed_fee_amounts);
                } else {
                    foreach ($target_fees as $fid) {
                        if (isset($billed_fee_amounts[$fid])) {
                            $target_amount += $billed_fee_amounts[$fid];
                        }
                    }
                }
                
                // Only apply if there is a targeted amount to discount
                if ($target_amount > 0) {
                    $discount_amount = 0;
                    
                    if ($schol['discount_type'] === 'percentage') {
                        $discount_amount = -1 * ($target_amount * ($schol['discount_value'] / 100));
                    } else {
                        $discount_amount = -1 * min($target_amount, $schol['discount_value']); // cap discount
                    }
                    
                    $note = "Waiver Applied: " . $schol['name'];
                    $insert_discount_stmt->bind_param("iisdsss", $student['id'], $discount_fee_id, $due_date, $discount_amount, $semester, $academic_year, $note);
                    if ($insert_discount_stmt->execute()) {
                        $discount_id = $conn->insert_id;
                        $assigned_count++; // count discount row as an assignment
                        
                        // Journal Entry for Scholarship Expense
                        record_journal_entry($conn, date('Y-m-d'), 'Waiver', $discount_id, "Waiver for Student #{$student['id']} ($semester)", [
                            ['account_code' => '5100', 'debit' => abs($discount_amount), 'credit' => 0], // DR Scholarship Expense
                            ['account_code' => '1200', 'debit' => 0, 'credit' => abs($discount_amount)]  // CR Accounts Rec
                        ]);
                    }
                }
            }
        }
        
        // Journal Entry for Total Student Bill (Revenue)
        $total_gross = array_sum($billed_fee_amounts);
        if ($total_gross > 0) {
            record_journal_entry($conn, date('Y-m-d'), 'StudentBill', $student['id'], "Semester Bill for Student #{$student['id']} ($semester)", [
                ['account_code' => '1200', 'debit' => $total_gross, 'credit' => 0], // DR Accounts Rec
                ['account_code' => '4000', 'debit' => 0, 'credit' => $total_gross]  // CR Tuition Revenue
            ]);
        }
    }
    
    $schol_stmt->close();
    if ($insert_discount_stmt) $insert_discount_stmt->close();
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
