<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_check.php';
include '../../../includes/system_settings.php';

$success = false;
$error_message = '';
$total_successful_unassignments = 0;
$all_unassignment_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_type = $_POST['assignment_type'] ?? 'individual';
    $fee_ids_string = $_POST['selectedFeesInput'] ?? '';
    $semester = $_POST['semester'] ?? '';
    $academic_year = trim($_POST['academic_year'] ?? '');
    
    // Parse fee IDs
    $fee_ids = array_filter(array_map('intval', explode(',', $fee_ids_string)));
    
    if (empty($fee_ids)) {
        $error_message = "Please select at least one fee to unassign.";
    } else {
        $conn->begin_transaction();

        try {
            // Get student IDs based on target
            $target_student_ids = [];
            
            if ($assignment_type === 'individual') {
                $student_id = intval($_POST['selectedStudentId'] ?? 0);
                if ($student_id <= 0) throw new Exception("Invalid student ID.");
                $target_student_ids[] = $student_id;
            } elseif ($assignment_type === 'multiple' || $assignment_type === 'multi-student') {
                $student_ids_string = $_POST['selectedStudentIds'] ?? '';
                $target_student_ids = array_filter(array_map('intval', explode(',', $student_ids_string)));
                if (empty($target_student_ids)) throw new Exception("Please select at least one student.");
            } elseif ($assignment_type === 'class') {
                $class_name = $_POST['classSelect'] ?? '';
                if (empty($class_name)) throw new Exception("Class name is required.");
                
                $students_stmt = $conn->prepare("SELECT id FROM students WHERE class = ? AND status = 'active'");
                $students_stmt->bind_param("s", $class_name);
                $students_stmt->execute();
                $res = $students_stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $target_student_ids[] = $row['id'];
                }
                $students_stmt->close();
                
                if (empty($target_student_ids)) throw new Exception("No active students found in class: " . $class_name);
            } elseif ($assignment_type === 'whole_school') {
                $students_stmt = $conn->query("SELECT id FROM students WHERE status = 'active'");
                while ($row = $students_stmt->fetch_assoc()) {
                    $target_student_ids[] = $row['id'];
                }
                
                if (empty($target_student_ids)) throw new Exception("No active students found in the school.");
            } else {
                throw new Exception("Invalid assignment type.");
            }
            
            // Loop through students and fees
            foreach ($target_student_ids as $student_id) {
                foreach ($fee_ids as $fee_id) {
                    // Check if fee is assigned
                    $sql = "SELECT id FROM student_fees WHERE student_id = ? AND fee_id = ? AND semester = ? AND academic_year = ? AND status != 'cancelled'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iiss", $student_id, $fee_id, $semester, $academic_year);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $assigned = $res->fetch_assoc();
                    $stmt->close();
                    
                    if ($assigned) {
                        $student_fee_id = $assigned['id'];
                        
                        // Check if paid
                        $payment_check_sql = "SELECT SUM(amount) as total_paid FROM payments WHERE student_id = ? AND fee_id = ?";
                        $payment_stmt = $conn->prepare($payment_check_sql);
                        $payment_stmt->bind_param("ii", $student_id, $fee_id);
                        $payment_stmt->execute();
                        $payment_result = $payment_stmt->get_result();
                        $payment_data = $payment_result->fetch_assoc();
                        $payment_stmt->close();
                        
                        $total_paid = floatval($payment_data['total_paid'] ?? 0);
                        
                        if ($total_paid > 0) {
                            $all_unassignment_errors[] = "Student ID $student_id has paid GH₵" . number_format($total_paid, 2) . " towards fee ID $fee_id. Cannot unassign.";
                        } else {
                            // Delete assignment
                            $delete_sql = "DELETE FROM student_fees WHERE id = ?";
                            $delete_stmt = $conn->prepare($delete_sql);
                            $delete_stmt->bind_param("i", $student_fee_id);
                            if ($delete_stmt->execute()) {
                                $total_successful_unassignments++;
                                
                                // Clean up feeding records if it's the feeding fee
                                $feeding_fee_stmt = $conn->prepare("SELECT id FROM fees WHERE name = 'Feeding Fee' LIMIT 1");
                                if ($feeding_fee_stmt) {
                                    $feeding_fee_stmt->execute();
                                    $feeding_fee_row = $feeding_fee_stmt->get_result()->fetch_assoc();
                                    $feeding_fee_stmt->close();

                                    if ($feeding_fee_row && (int)$fee_id === (int)$feeding_fee_row['id']) {
                                        $feeding_exists_stmt = $conn->prepare("SELECT id FROM student_daily_weekly_feeding WHERE student_id = ? AND academic_year = ? AND semester = ? AND status = 'active' LIMIT 1");
                                        if ($feeding_exists_stmt) {
                                            $feeding_exists_stmt->bind_param('iss', $student_id, $academic_year, $semester);
                                            $feeding_exists_stmt->execute();
                                            $feeding_exists_row = $feeding_exists_stmt->get_result()->fetch_assoc();
                                            $feeding_exists_stmt->close();

                                            if ($feeding_exists_row) {
                                                $feeding_delete_stmt = $conn->prepare("UPDATE student_daily_weekly_feeding SET status = 'deleted' WHERE id = ?");
                                                if ($feeding_delete_stmt) {
                                                    $feeding_delete_stmt->bind_param('i', $feeding_exists_row['id']);
                                                    $feeding_delete_stmt->execute();
                                                    $feeding_delete_stmt->close();
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $delete_stmt->close();
                        }
                    }
                }
            }
            
            if ($total_successful_unassignments === 0 && empty($all_unassignment_errors)) {
                throw new Exception("No matching fee assignments found to unassign for the selected targets and period.");
            }
            
            $conn->commit();
            $success = true;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Unassign Result | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-xl max-w-2xl w-full overflow-hidden border border-slate-100">
        <?php if ($success): ?>
            <div class="bg-emerald-50 border-b border-emerald-100 p-8 text-center">
                <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-inner">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="text-3xl font-black text-slate-900 mb-2">Unassignment Complete</h1>
                <p class="text-emerald-700 font-medium">Successfully expunged <?php echo $total_successful_unassignments; ?> fee assignment(s).</p>
            </div>
            
            <?php if (!empty($all_unassignment_errors)): ?>
                <div class="p-8 border-b border-slate-100">
                    <h3 class="text-sm font-black text-rose-600 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle"></i> Skipped Records
                    </h3>
                    <div class="bg-rose-50 rounded-xl p-4 max-h-48 overflow-y-auto">
                        <ul class="space-y-2 text-sm text-rose-700 font-medium">
                            <?php foreach($all_unassignment_errors as $err): ?>
                                <li><i class="fas fa-times-circle mr-2 opacity-50"></i><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="p-8 bg-slate-50 flex gap-4 justify-center">
                <a href="bulk_unassign_fee_form.php" class="px-6 py-3 bg-white text-slate-700 border border-slate-200 rounded-xl font-bold text-sm hover:bg-slate-100 transition-colors">
                    Back to Form
                </a>
                <a href="view_assigned_fees.php" class="px-6 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200">
                    View All Assignments
                </a>
            </div>

        <?php else: ?>
            <div class="bg-rose-50 border-b border-rose-100 p-8 text-center">
                <div class="w-20 h-20 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-inner">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 class="text-3xl font-black text-slate-900 mb-2">Unassignment Failed</h1>
                <p class="text-rose-700 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            
            <?php if (!empty($all_unassignment_errors)): ?>
                <div class="p-8 border-b border-slate-100">
                    <div class="bg-rose-50/50 rounded-xl p-4 max-h-48 overflow-y-auto">
                        <ul class="space-y-2 text-sm text-rose-700 font-medium">
                            <?php foreach($all_unassignment_errors as $err): ?>
                                <li><i class="fas fa-times-circle mr-2 opacity-50"></i><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="p-8 bg-slate-50 flex justify-center">
                <a href="javascript:history.back()" class="px-8 py-3 bg-slate-900 text-white rounded-xl font-bold text-sm hover:bg-slate-800 transition-colors shadow-lg shadow-slate-200">
                    Go Back and Fix
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
