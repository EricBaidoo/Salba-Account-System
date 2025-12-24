<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Helper function to calculate fee amount based on type
function calculateFeeAmount($conn, $fee_id, $student_class) {
    // Get fee info
    $fee_stmt = $conn->prepare("SELECT fee_type, amount FROM fees WHERE id = ?");
    $fee_stmt->bind_param("i", $fee_id);
    $fee_stmt->execute();
    $fee_result = $fee_stmt->get_result();
    $fee_info = $fee_result->fetch_assoc();
    $fee_stmt->close();
    
    if (!$fee_info) {
        throw new Exception("Fee not found");
    }
    
    if ($fee_info['fee_type'] === 'fixed') {
        return $fee_info['amount'];
    } else if ($fee_info['fee_type'] === 'class_based') {
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND class_name = ?");
        $amount_stmt->bind_param("is", $fee_id, $student_class);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        
        if ($amount_row) {
            return $amount_row['amount'];
        } else {
            return null; // Fee not configured for this class
        }
    } else { // category based
        // Get student's level/category from classes table
        $level_stmt = $conn->prepare("SELECT Level FROM classes WHERE name = ? LIMIT 1");
        $level_stmt->bind_param("s", $student_class);
        $level_stmt->execute();
        $level_result = $level_stmt->get_result();
        $level_row = $level_result->fetch_assoc();
        $level_stmt->close();
        
        $category = $level_row ? $level_row['Level'] : null;
        if (!$category) {
            return null; // Class not mapped to category
        }
        
        $amount_stmt = $conn->prepare("SELECT amount FROM fee_amounts WHERE fee_id = ? AND category = ?");
        $amount_stmt->bind_param("is", $fee_id, $category);
        $amount_stmt->execute();
        $amount_result = $amount_stmt->get_result();
        $amount_row = $amount_result->fetch_assoc();
        $amount_stmt->close();
        
        if ($amount_row) {
            return $amount_row['amount'];
        } else {
            return null; // Fee not configured for this category
        }
    }
}

// Check if this is a preview or confirmation
$action = $_POST['action'] ?? 'preview';
$term = $_POST['term'] ?? '';
$due_date = $_POST['due_date'] ?? '';
$class_filter = $_POST['class_filter'] ?? 'all';
$notes = $_POST['notes'] ?? '';
$selected_fees = $_POST['selected_fees'] ?? '';

// Validate inputs
if (empty($term) || empty($due_date) || empty($selected_fees)) {
    die('<div class="alert alert-danger">Missing required fields. Please go back and fill all required fields.</div>');
}

// Parse fee IDs
$fee_ids = array_filter(array_map('intval', explode(',', $selected_fees)));
if (empty($fee_ids)) {
    die('<div class="alert alert-danger">No fees selected. Please go back and select at least one fee.</div>');
}

// Get fee details
$fee_placeholders = implode(',', array_fill(0, count($fee_ids), '?'));
$fee_query = "SELECT id, name, fee_type, amount FROM fees WHERE id IN ($fee_placeholders)";
$fee_stmt = $conn->prepare($fee_query);
$types = str_repeat('i', count($fee_ids));
$fee_stmt->bind_param($types, ...$fee_ids);
$fee_stmt->execute();
$fees_result = $fee_stmt->get_result();
$fees = [];
while ($fee = $fees_result->fetch_assoc()) {
    $fees[$fee['id']] = $fee;
}
$fee_stmt->close();

// Build student query based on class filter
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

// Prepare preview data
$preview_data = [];
$total_assignments = 0;
$skipped_count = 0;
$error_count = 0;

while ($student = $students_result->fetch_assoc()) {
    $student_id = $student['id'];
    $student_name = $student['first_name'] . ' ' . $student['last_name'];
    $student_class = $student['class'];
    
    foreach ($fee_ids as $fee_id) {
        // Check if already assigned for this term
        $check_stmt = $conn->prepare("
            SELECT id FROM student_fees 
            WHERE student_id = ? AND fee_id = ? AND term = ? AND status != 'cancelled'
        ");
        $check_stmt->bind_param("iis", $student_id, $fee_id, $term);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $already_assigned = $check_result->num_rows > 0;
        $check_stmt->close();
        
        if ($already_assigned) {
            $skipped_count++;
            continue;
        }
        
        // Calculate amount
        try {
            $amount = calculateFeeAmount($conn, $fee_id, $student_class);
            if ($amount === null) {
                $error_count++;
                continue; // Skip if fee not configured for this class/category
            }
            
            $preview_data[] = [
                'student_id' => $student_id,
                'student_name' => $student_name,
                'student_class' => $student_class,
                'fee_id' => $fee_id,
                'fee_name' => $fees[$fee_id]['name'],
                'amount' => $amount
            ];
            $total_assignments++;
        } catch (Exception $e) {
            $error_count++;
        }
    }
}
$student_stmt->close();

// If confirm action, execute the assignments
if ($action === 'confirm') {
    $conn->begin_transaction();
    try {
        $success_count = 0;
        $insert_stmt = $conn->prepare("
            INSERT INTO student_fees (student_id, fee_id, due_date, amount, term, notes, assigned_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        
        foreach ($preview_data as $assignment) {
            $insert_stmt->bind_param(
                "iisdss",
                $assignment['student_id'],
                $assignment['fee_id'],
                $due_date,
                $assignment['amount'],
                $term,
                $notes
            );
            
            if ($insert_stmt->execute()) {
                $success_count++;
            }
        }
        
        $insert_stmt->close();
        $conn->commit();
        
        // Redirect to success page
        header("Location: bulk_term_billing_form.php?success=1&count=$success_count&skipped=$skipped_count");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error during bulk assignment: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Billing Preview - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="mb-3">
            <a href="bulk_term_billing_form.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Form</a>
        </div>

        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-2"><i class="fas fa-eye me-2"></i>Bulk Billing Preview</h2>
            <p class="lead mb-0">Review the assignments before confirming</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Summary Card -->
        <div class="summary-card">
            <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Billing Summary</h4>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="mb-1"><?php echo $total_assignments; ?></h3>
                        <small>New Assignments</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="mb-1"><?php echo $skipped_count; ?></h3>
                        <small>Skipped (Already Assigned)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="mb-1"><?php echo $error_count; ?></h3>
                        <small>Skipped (Not Configured)</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h3 class="mb-1">GH₵<?php echo number_format(array_sum(array_column($preview_data, 'amount')), 2); ?></h3>
                        <small>Total Amount</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Details -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><i class="fas fa-info-circle me-2"></i>Billing Details</h5>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Term:</strong> <?php echo htmlspecialchars($term); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Due Date:</strong> <?php echo htmlspecialchars($due_date); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Classes:</strong> <?php echo $class_filter === 'all' ? 'All Classes' : htmlspecialchars($class_filter); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Fees:</strong> <?php echo count($fee_ids); ?> selected
                    </div>
                </div>
                <?php if ($notes): ?>
                    <div class="mt-3">
                        <strong>Notes:</strong> <?php echo htmlspecialchars($notes); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error_count > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong><?php echo $error_count; ?> assignments were skipped</strong> because fees are not configured for certain classes or categories.
            </div>
        <?php endif; ?>

        <?php if ($total_assignments > 0): ?>
            <!-- Preview Table -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Assignments to be Created (<?php echo $total_assignments; ?>)</h5>
                </div>
                <div class="preview-table">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Fee</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_class = '';
                            foreach ($preview_data as $item): 
                                if ($current_class !== $item['student_class']) {
                                    $current_class = $item['student_class'];
                                    echo '<tr class="table-secondary"><td colspan="4"><strong>' . htmlspecialchars($current_class) . '</strong></td></tr>';
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['student_class']); ?></td>
                                    <td><?php echo htmlspecialchars($item['fee_name']); ?></td>
                                    <td class="text-end">GH₵<?php echo number_format($item['amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Confirmation Form -->
            <form method="POST" action="bulk_term_billing.php">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="term" value="<?php echo htmlspecialchars($term); ?>">
                <input type="hidden" name="due_date" value="<?php echo htmlspecialchars($due_date); ?>">
                <input type="hidden" name="class_filter" value="<?php echo htmlspecialchars($class_filter); ?>">
                <input type="hidden" name="notes" value="<?php echo htmlspecialchars($notes); ?>">
                <input type="hidden" name="selected_fees" value="<?php echo htmlspecialchars($selected_fees); ?>">
                
                <div class="text-center">
                    <a href="bulk_term_billing_form.php" class="btn btn-secondary btn-lg me-2">
                        <i class="fas fa-times me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="fas fa-check-circle me-2"></i>Confirm & Assign <?php echo $total_assignments; ?> Fees
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle me-2"></i>
                No new assignments to create. All selected students already have these fees assigned for this term.
            </div>
            <div class="text-center">
                <a href="bulk_term_billing_form.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left me-1"></i>Back to Form
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
