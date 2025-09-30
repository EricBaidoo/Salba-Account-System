<?php
include '../includes/db_connect.php';
include '../includes/auth_check.php';

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

// Helper function to check if fee is already assigned
function isAlreadyAssigned($conn, $student_id, $fee_id) {
    $check_stmt = $conn->prepare("SELECT id FROM student_fees WHERE student_id = ? AND fee_id = ? AND status != 'cancelled'");
    $check_stmt->bind_param("ii", $student_id, $fee_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $is_assigned = $check_result->num_rows > 0;
    $check_stmt->close();
    return $is_assigned;
}

// Helper function to insert fee assignment
function insertFeeAssignment($conn, $student_id, $fee_id, $due_date, $amount, $term, $notes) {
    $insert_stmt = $conn->prepare("
        INSERT INTO student_fees (student_id, fee_id, due_date, amount, term, notes, assigned_date, status) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
    ");
    $insert_stmt->bind_param("iisdss", $student_id, $fee_id, $due_date, $amount, $term, $notes);
    
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
    $term = $_POST['term'] ?? '';
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
                        if (isAlreadyAssigned($conn, $student_id, $fee_info['id'])) {
                            $all_assignment_errors[] = $fee_info['name'] . " (already assigned to " . $student_info['first_name'] . " " . $student_info['last_name'] . ")";
                        } else {
                            // Insert assignment
                            insertFeeAssignment($conn, $student_id, $fee_info['id'], $due_date, $calculated_amount, $term, $notes);
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
                            if (!isAlreadyAssigned($conn, $student['id'], $fee_info['id'])) {
                                // Insert assignment
                                insertFeeAssignment($conn, $student['id'], $fee_info['id'], $due_date, $calculated_amount, $term, $notes);
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
                            if (!isAlreadyAssigned($conn, $student['id'], $fee_info['id'])) {
                                // Insert assignment
                                insertFeeAssignment($conn, $student['id'], $fee_info['id'], $due_date, $calculated_amount, $term, $notes);
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Assignment Result - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($success): ?>
                    <!-- Success Message -->
                    <div class="card border-success shadow-lg">
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                Fee Assignment Successful
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-money-bill-wave me-2"></i>Fee Information</h6>
                                    <div class="card bg-light mb-3">
                                        <div class="card-body">
                                            <strong><?php echo htmlspecialchars($fee_info['name']); ?></strong><br>
                                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $fee_info['fee_type'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-users me-2"></i>Assignment Summary</h6>
                                    <div class="card bg-light mb-3">
                                        <div class="card-body">
                                            <?php if ($assignment_type === 'individual'): ?>
                                                <strong>Individual Assignment</strong><br>
                                                <span class="text-muted">
                                                    <?php echo isset($total_successful_assignments) ? $total_successful_assignments : 1; ?> fee assignment(s) to 1 student
                                                </span>
                                            <?php elseif ($assignment_type === 'multi-student'): ?>
                                                <strong>Multi-Student Assignment</strong><br>
                                                <span class="text-muted">
                                                    <?php echo isset($total_successful_assignments) ? $total_successful_assignments : count($assigned_students); ?> total fee assignments to <?php echo count($assigned_students); ?> selected students
                                                </span>
                                            <?php else: ?>
                                                <strong>Class Assignment</strong><br>
                                                <span class="text-muted">
                                                    <?php echo isset($total_successful_assignments) ? $total_successful_assignments : count($assigned_students); ?> total fee assignments to <?php echo count($assigned_students); ?> students
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Assigned Students -->
                            <div class="mb-4">
                                <h6><i class="fas fa-list me-2"></i>Assigned Students</h6>
                                <div class="row">
                                    <?php foreach ($assigned_students as $index => $student): ?>
                                        <?php if ($index < 8): // Show max 8 students directly ?>
                                            <div class="col-md-3 col-sm-6 mb-2">
                                                <div class="card bg-light">
                                                    <div class="card-body p-2">
                                                        <small>
                                                            <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong><br>
                                                            <span class="text-muted"><?php echo htmlspecialchars($student['class']); ?></span>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (count($assigned_students) > 8): ?>
                                        <div class="col-12">
                                            <p class="text-muted"><i class="fas fa-info-circle me-1"></i>And <?php echo (count($assigned_students) - 8); ?> more students...</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-calendar me-2"></i>Assignment Details</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Due Date:</strong> <?php echo date('F j, Y', strtotime($due_date)); ?></li>
                                        <?php if (!empty($term)): ?>
                                            <li><strong>Term:</strong> <?php echo htmlspecialchars($term); ?></li>
                                        <?php endif; ?>
                                        <li><strong>Assigned:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></li>
                                        <li><strong>Status:</strong> <span class="badge bg-warning">Pending Payment</span></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <?php if (!empty($notes)): ?>
                                        <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                        <div class="alert alert-light">
                                            <?php echo nl2br(htmlspecialchars($notes)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($all_assignment_errors)): ?>
                                        <h6 class="mt-4"><i class="fas fa-exclamation-circle me-2 text-danger"></i>Skipped Assignments</h6>
                                        <div class="alert alert-warning">
                                            <ul class="mb-0">
                                                <?php foreach ($all_assignment_errors as $err): ?>
                                                    <li><?php echo htmlspecialchars($err); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <div class="mt-2 small text-muted">
                                                <strong>Tip:</strong> For missing fee amounts, edit the fee and ensure every class has a configured amount. For already assigned fees, check the student's assignments.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                <a href="record_payment_form.php?student_id=<?php echo $student_info['id']; ?>&fee_id=<?php echo $fee_info['id']; ?>" class="btn btn-success">
                                    <i class="fas fa-credit-card me-2"></i>Record Payment Now
                                </a>
                                <a href="assign_fee_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Assign Another Fee
                                </a>
                                <a href="view_assigned_fees.php" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i>View All Assignments
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Error Message -->
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Assignment Failed
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger">
                                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <a href="assign_fee_form.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Assignment Form
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-home me-2"></i>Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Navigation Links -->
                <div class="text-center mt-4">
                    <div class="btn-group" role="group">
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                        <a href="view_students.php" class="btn btn-outline-primary">
                            <i class="fas fa-users me-2"></i>Students
                        </a>
                        <a href="view_fees.php" class="btn btn-outline-success">
                            <i class="fas fa-money-bill-wave me-2"></i>Fees
                        </a>
                        <a href="view_payments.php" class="btn btn-outline-info">
                            <i class="fas fa-credit-card me-2"></i>Payments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>