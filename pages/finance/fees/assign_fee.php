<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_check.php';
include '../../../includes/system_settings.php';

// Helper function to calculate fee amount based on type
function calculateFeeAmount($conn, $fee_info, $student_class) {
    if ($fee_info['fee_type'] === 'fixed') {
        return $fee_info['amount'];
    } else if ($fee_info['fee_type'] === 'class_based') {
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND class_name = ?");
        $amount_stmt->bind_param("is", $fee_info['id'], $student_class);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        if ($amount_row) {
            return $amount_row['amount'];
        } else {
            throw new Exception("Fee amount not configured for this student's class.");
        }
    } else { // category based
        // Join students.class to classes.name to get Level
        $level_stmt = $conn->prepare("SELECT Level FROM classes WHERE name = ? LIMIT 1");
        $level_stmt->bind_param("s", $student_class);
        $level_stmt->execute();
        $level_result = $level_stmt->get_result();
        $level_row = $level_result->fetch_assoc();
        $level_stmt->close();
        $category = $level_row ? $level_row['Level'] : null;
        if (!$category) {
            throw new Exception("Student's class is not mapped to a category/level.");
        }
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND category = ?");
        $amount_stmt->bind_param("is", $fee_info['id'], $category);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        if ($amount_row) {
            return $amount_row['amount'];
        } else {
            throw new Exception("Fee amount not configured for this student's category/level.");
        }
    }
}

// Helper function to check if fee is already assigned for the same semester and academic year
function isAlreadyAssigned($conn, $student_id, $fee_id, $semester, $academic_year) {
    $sql = "SELECT id FROM student_fees WHERE student_id = ? AND fee_id = ? AND semester = ? AND (academic_year = ? OR (academic_year IS NULL AND ? = '')) AND status != 'cancelled'";
    $check_stmt = $conn->prepare($sql);
    $check_stmt->bind_param("issss", $student_id, $fee_id, $semester, $academic_year, $academic_year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $is_assigned = $check_result->num_rows > 0;
    $check_stmt->close();
    return $is_assigned;
}

// Helper function to insert fee assignment
function insertFeeAssignment($conn, $student_id, $fee_id, $due_date, $amount, $semester, $academic_year, $notes) {
    $insert_stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, due_date, amount, semester, academic_year, notes, assigned_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')");
    $insert_stmt->bind_param("iisdsss", $student_id, $fee_id, $due_date, $amount, $semester, $academic_year, $notes);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Error assigning fee: " . $insert_stmt->error);
    }
    $insert_stmt->close();
}

$success = false;
$error_message = '';
$student_info = null;
$fee_info = null;
$calculated_amount = 0;
$assignment_type = '';
$class_name = '';
$assigned_students = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_type = $_POST['assignment_type'] ?? 'individual';
    $fee_ids_string = $_POST['selectedFeesInput'] ?? '';
    $due_date = $_POST['due_date'];
    $semester = $_POST['semester'] ?? '';
    $academic_year = trim($_POST['academic_year'] ?? '');
    if ($academic_year === '') { $academic_year = getAcademicYear($conn); }
    $notes = $_POST['notes'] ?? '';
    
    // Parse fee IDs
    $fee_ids = array_filter(array_map('intval', explode(',', $fee_ids_string)));
    
    if (empty($fee_ids)) {
        $error_message = "Please select at least one fee to assign.";
    } else {

        // Start transaction
        $conn->begin_transaction();

        try {
            $all_fees_info = [];
            $total_successful_assignments = 0;
            $all_assignment_errors = [];
            
            // Get information for all selected fees
            foreach ($fee_ids as $fee_id) {
                $fee_stmt = $conn->prepare("SELECT id, name, amount, fee_type, description FROM fees WHERE id = ?");
                $fee_stmt->bind_param("i", $fee_id);
                $fee_stmt->execute();
                $fee_result = $fee_stmt->get_result();
                $fee_info = $fee_result->fetch_assoc();
                $fee_stmt->close();

                if (!$fee_info) {
                    throw new Exception("Fee with ID $fee_id not found.");
                }
                
                $all_fees_info[] = $fee_info;
            }

            if ($assignment_type === 'individual') {
                // Individual assignment
                $student_id = intval($_POST['selectedStudentId']);
                
                // Get student information
                $student_stmt = $conn->prepare("SELECT id, first_name, last_name, class FROM students WHERE id = ? AND status = 'active'");
                $student_stmt->bind_param("i", $student_id);
                $student_stmt->execute();
                $student_result = $student_stmt->get_result();
                $student_info = $student_result->fetch_assoc();
                $student_stmt->close();

                if (!$student_info) {
                    throw new Exception("Student not found or inactive.");
                }

                // Process each selected fee
                foreach ($all_fees_info as $fee_info) {
                    try {
                        // Calculate amount and process assignment
                        $calculated_amount = calculateFeeAmount($conn, $fee_info, $student_info['class']);
                        
                        // Check if already assigned
                        if (isAlreadyAssigned($conn, $student_id, $fee_info['id'], $semester, $academic_year)) {
                            $all_assignment_errors[] = $fee_info['name'] . " (already assigned to " . $student_info['first_name'] . " " . $student_info['last_name'] . ")";
                        } else {
                            // Insert assignment
                            insertFeeAssignment($conn, $student_id, $fee_info['id'], $due_date, $calculated_amount, $semester, $academic_year, $notes);
                            $total_successful_assignments++;
                        }
                    } catch (Exception $e) {
                        $all_assignment_errors[] = $fee_info['name'] . " (" . $e->getMessage() . ")";
                    }
                }
                
                if ($total_successful_assignments > 0) {
                    $assigned_students[] = $student_info;
                }
            
            } elseif ($assignment_type === 'multi-student') {
                // Multi-student assignment
                $student_ids_string = $_POST['selectedStudentIds'] ?? '';
                $student_ids = array_filter(array_map('intval', explode(',', $student_ids_string)));
                
                if (empty($student_ids)) {
                    throw new Exception("Please select at least one student.");
                }
                
                // Get information for all selected students
                $student_ids_placeholders = implode(',', array_fill(0, count($student_ids), '?'));
                $students_stmt = $conn->prepare("SELECT id, first_name, last_name, class FROM students WHERE id IN ($student_ids_placeholders) AND status = 'active'");
                $students_stmt->bind_param(str_repeat('i', count($student_ids)), ...$student_ids);
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();
                
                if ($students_result->num_rows === 0) {
                    throw new Exception("No valid students found from selection.");
                }
                
                while ($student = $students_result->fetch_assoc()) {
                    // Process each fee for each selected student
                    foreach ($all_fees_info as $fee_info) {
                        try {
                            // Calculate amount for this student and fee
                            $calculated_amount = calculateFeeAmount($conn, $fee_info, $student['class']);
                            
                            // Check if already assigned
                            if (!isAlreadyAssigned($conn, $student['id'], $fee_info['id'], $semester, $academic_year)) {
                                // Insert assignment
                                insertFeeAssignment($conn, $student['id'], $fee_info['id'], $due_date, $calculated_amount, $semester, $academic_year, $notes);
                                $total_successful_assignments++;
                            } else {
                                $all_assignment_errors[] = $student['first_name'] . ' ' . $student['last_name'] . ' - ' . $fee_info['name'] . ' (already assigned)';
                            }
                        } catch (Exception $e) {
                            $all_assignment_errors[] = $student['first_name'] . ' ' . $student['last_name'] . ' - ' . $fee_info['name'] . ' (' . $e->getMessage() . ')';
                        }
                    }
                }
                
                // Get unique students for display
                $students_result->data_seek(0);
                while ($student = $students_result->fetch_assoc()) {
                    $assigned_students[] = $student;
                }
                $students_stmt->close();
                
                if ($total_successful_assignments === 0) {
                    throw new Exception("No fee assignments could be completed. Errors: " . implode(', ', array_slice($all_assignment_errors, 0, 10)));
                }
            
            } else {
                // Class assignment
                $class_name = $_POST['classSelect'];
                
                if (empty($class_name)) {
                    throw new Exception("Class name is required for class assignment.");
                }
                
                // Get all active students in the class
                $students_stmt = $conn->prepare("SELECT id, first_name, last_name, class FROM students WHERE class = ? AND status = 'active'");
                $students_stmt->bind_param("s", $class_name);
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();
                
                if ($students_result->num_rows === 0) {
                    throw new Exception("No active students found in class: " . $class_name);
                }
                
                while ($student = $students_result->fetch_assoc()) {
                    // Process each fee for each student
                    foreach ($all_fees_info as $fee_info) {
                        try {
                            // Calculate amount for this student and fee
                            $calculated_amount = calculateFeeAmount($conn, $fee_info, $student['class']);
                            
                            // Check if already assigned
                            if (!isAlreadyAssigned($conn, $student['id'], $fee_info['id'], $semester, $academic_year)) {
                                // Insert assignment
                                insertFeeAssignment($conn, $student['id'], $fee_info['id'], $due_date, $calculated_amount, $semester, $academic_year, $notes);
                                $total_successful_assignments++;
                            } else {
                                $all_assignment_errors[] = $student['first_name'] . ' ' . $student['last_name'] . ' - ' . $fee_info['name'] . ' (already assigned)';
                            }
                        } catch (Exception $e) {
                            $all_assignment_errors[] = $student['first_name'] . ' ' . $student['last_name'] . ' - ' . $fee_info['name'] . ' (' . $e->getMessage() . ')';
                        }
                    }
                }
                
                // Get unique students for display
                $students_stmt->execute();
                $students_result = $students_stmt->get_result();
                while ($student = $students_result->fetch_assoc()) {
                    $assigned_students[] = $student;
                }
                $students_stmt->close();
                
                if ($total_successful_assignments === 0) {
                    throw new Exception("No fee assignments could be completed. Errors: " . implode(', ', array_slice($all_assignment_errors, 0, 10)));
                }
            }

            // Commit transaction
            $conn->commit();
            $success = true;

            // Ensure arrears carry-forward is materialized for each affected student in this semester/year
            if (!empty($assigned_students)) {
                include_once '../../../includes/student_balance_functions.php';
                foreach ($assigned_students as $st) {
                    if (!empty($st['id'])) {
                        ensureArrearsAssignment($conn, intval($st['id']), $semester, $academic_year);
                    }
                }
            }
            
            // Set fee_info to first fee for display (or create summary)
            if (!empty($all_fees_info)) {
                $fee_info = $all_fees_info[0]; // Use first fee for basic display
                if (count($all_fees_info) > 1) {
                    $fee_info['name'] = count($all_fees_info) . ' fees assigned';
                    $fee_info['description'] = 'Multiple fees: ' . implode(', ', array_column($all_fees_info, 'name'));
                }
            }
            
            // Add assignment errors to notes if any
            if (!empty($all_assignment_errors)) {
                $notes .= "\n\nSome assignments were skipped: " . implode(', ', array_slice($all_assignment_errors, 0, 5));
                if (count($all_assignment_errors) > 5) {
                    $notes .= " and " . (count($all_assignment_errors) - 5) . " more...";
                }
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>
<<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignment Result | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_fees.php" class="hover:text-blue-600 transition-colors">Fee Management</a>
                <span>/</span>
                <span class="text-blue-600">Assignment Result</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-tasks text-emerald-600"></i> Fee Assignment Status
                    </h1>
                </div>
                <a href="assign_fee_form.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Back to Assignments
                </a>
            </div>
        </div>

        <div class="px-6 max-w-4xl mx-auto">
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="bg-white rounded-xl border border-emerald-200 shadow-sm overflow-hidden mb-6">
                    <div class="bg-emerald-50 px-6 py-4 border-b border-emerald-100 flex items-center gap-3 text-emerald-700">
                        <i class="fas fa-check-circle text-xl"></i>
                        <h4 class="font-bold text-lg m-0">Fee Assignment Successful</h4>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fas fa-money-bill-wave mr-2"></i>Fee Information</h6>
                                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200">
                                    <strong class="text-sm text-slate-800 block mb-1"><?php echo htmlspecialchars($fee_info['name']); ?></strong>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-200 text-slate-700"><?php echo ucfirst(str_replace('_', ' ', $fee_info['fee_type'])); ?></span>
                                </div>
                            </div>
                            <div>
                                <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fas fa-users mr-2"></i>Assignment Summary</h6>
                                <div class="bg-slate-50 rounded-lg p-4 border border-slate-200 text-sm">
                                    <?php if ($assignment_type === 'individual'): ?>
                                        <strong class="text-slate-800 block mb-1">Individual Assignment</strong>
                                        <span class="text-slate-600">
                                            <?php echo isset($total_successful_assignments) ? $total_successful_assignments : 1; ?> fee assignment(s) to 1 student
                                        </span>
                                    <?php elseif ($assignment_type === 'multi-student'): ?>
                                        <strong class="text-slate-800 block mb-1">Multi-Student Assignment</strong>
                                        <span class="text-slate-600">
                                            <?php echo isset($total_successful_assignments) ? $total_successful_assignments : count($assigned_students); ?> total fee assignments to <?php echo count($assigned_students); ?> selected students
                                        </span>
                                    <?php else: ?>
                                        <strong class="text-slate-800 block mb-1">Class Assignment</strong>
                                        <span class="text-slate-600">
                                            <?php echo isset($total_successful_assignments) ? $total_successful_assignments : count($assigned_students); ?> total fee assignments to <?php echo count($assigned_students); ?> students
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Assigned Students -->
                        <div class="mb-6">
                            <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fas fa-list mr-2"></i>Assigned Students</h6>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($assigned_students as $index => $student): ?>
                                    <?php if ($index < 8): ?>
                                        <div class="bg-white border border-slate-200 rounded-lg p-3">
                                            <strong class="text-sm text-slate-800 truncate block"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($student['class']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (count($assigned_students) > 8): ?>
                                    <div class="col-span-full">
                                        <p class="text-sm text-slate-500 font-medium"><i class="fas fa-info-circle mr-1"></i>And <?php echo (count($assigned_students) - 8); ?> more students...</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fas fa-calendar mr-2"></i>Assignment Details</h6>
                                <ul class="space-y-2 text-sm text-slate-700">
                                    <li><strong class="text-slate-800">Due Date:</strong> <?php echo date('F j, Y', strtotime($due_date)); ?></li>
                                    <?php if (!empty($semester)): ?>
                                        <li><strong class="text-slate-800">Semester:</strong> <?php echo htmlspecialchars($semester); ?></li>
                                    <?php endif; ?>
                                    <?php if (!empty($academic_year)): ?>
                                        <li><strong class="text-slate-800">Academic Year:</strong> <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)); ?></li>
                                    <?php endif; ?>
                                    <li><strong class="text-slate-800">Assigned:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></li>
                                    <li><strong class="text-slate-800">Status:</strong> <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-700">Pending Payment</span></li>
                                </ul>
                            </div>
                            <div>
                                <?php if (!empty($notes)): ?>
                                    <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fas fa-sticky-note mr-2"></i>Notes</h6>
                                    <div class="p-4 bg-slate-50 text-slate-700 text-sm rounded border border-slate-200">
                                        <?php echo nl2br(htmlspecialchars($notes)); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($all_assignment_errors)): ?>
                                    <h6 class="text-xs font-bold text-slate-500 uppercase tracking-wider mt-4 mb-3"><i class="fas fa-exclamation-circle mr-2 text-amber-600"></i>Skipped Assignments</h6>
                                    <div class="p-4 bg-amber-50 text-amber-700 text-sm rounded border border-amber-200">
                                        <ul class="list-disc pl-4 space-y-1 mb-2">
                                            <?php foreach ($all_assignment_errors as $err): ?>
                                                <li><?php echo htmlspecialchars($err); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <div class="mt-2 text-xs text-amber-600 opacity-80">
                                            <strong>Tip:</strong> For missing fee amounts, edit the fee and ensure every class has a configured amount. For already assigned fees, check the student's assignments.
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="flex flex-wrap items-center gap-3 mt-8 pt-6 border-t border-slate-100">
                            <?php if ($assignment_type === 'individual' && isset($student_info['id'])): ?>
                                <a href="../payments/record_payment_form.php?student_id=<?php echo $student_info['id']; ?>&fee_id=<?php echo $fee_info['id']; ?>&semester=<?php echo urlencode($semester); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition-all">
                                    <i class="fas fa-money-bill-wave mr-2"></i>Record Payment Now
                                </a>
                            <?php endif; ?>
                            <a href="assign_fee_form.php" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-all">
                                <i class="fas fa-plus mr-2"></i>Assign Another Fee
                            </a>
                            <a href="view_assigned_fees.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all">
                                <i class="fas fa-eye mr-2"></i>View All Assignments
                            </a>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Error Message -->
                <div class="bg-white rounded-xl border border-rose-200 shadow-sm overflow-hidden mb-6">
                    <div class="bg-rose-50 px-6 py-4 border-b border-rose-100 flex items-center gap-3 text-rose-700">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <h4 class="font-bold text-lg m-0">Assignment Failed</h4>
                    </div>
                    <div class="p-6">
                        <div class="p-4 bg-rose-50 text-rose-700 rounded border border-rose-200 mb-6">
                            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                        
                        <div class="flex gap-3">
                            <a href="assign_fee_form.php" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Form
                            </a>
                            <a href="../dashboard.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all">
                                <i class="fas fa-home mr-2"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
