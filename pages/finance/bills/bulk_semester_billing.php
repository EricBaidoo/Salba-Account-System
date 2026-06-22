<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
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
        // Get student's level/category from classes w-full border-collapse
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
$semester = $_POST['semester'] ?? '';
$due_date = $_POST['due_date'] ?? '';
$class_filter = $_POST['class_filter'] ?? 'all';
$notes = $_POST['notes'] ?? '';
$selected_fees = $_POST['selected_fees'] ?? '';

// Validate inputs
if (empty($semester) || empty($due_date) || empty($selected_fees)) {
    die('<div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">Missing required fields. Please go back and fill all required fields.</div>');
}

// Parse fee IDs
$fee_ids = array_filter(array_map('intval', explode(',', $selected_fees)));
if (empty($fee_ids)) {
    die('<div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">No fees selected. Please go back and select at least one fee.</div>');
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
        // Check if already assigned for this semester
        $check_stmt = $conn->prepare("
            SELECT id FROM student_fees 
            WHERE student_id = ? AND fee_id = ? AND semester = ? AND status != 'cancelled'
        ");
        $check_stmt->bind_param("iis", $student_id, $fee_id, $semester);
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
            INSERT INTO student_fees (student_id, fee_id, due_date, amount, semester, notes, assigned_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        
        foreach ($preview_data as $assignment) {
            $insert_stmt->bind_param(
                "iisdss",
                $assignment['student_id'],
                $assignment['fee_id'],
                $due_date,
                $assignment['amount'],
                $semester,
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
    <title>Bulk Billing Preview - Salba Montessori</title>
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
                <a href="view_semester_bills.php" class="hover:text-blue-600 transition-colors">Billing Center</a>
                <span>/</span>
                <span class="text-blue-600">Bulk Billing Preview</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-eye text-emerald-600"></i> Review Bulk Billing
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Review the assignments before confirming</p>
                </div>
                <a href="generate_semester_bills.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Back to Form
                </a>
            </div>
        </div>

        <div class="px-6">

        <?php if (isset($error_message)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                <i class="fas fa-exclamation-triangle"></i> <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900"><?= $total_assignments ?></h3>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">New Assignments</p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fas fa-forward"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900"><?= $skipped_count ?></h3>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Skipped (Active)</p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900"><?= $error_count ?></h3>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Skipped (No Config)</p>
                </div>
            </div>
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0">
                    <i class="fas fa-coins"></i>
                </div>
                <div>
                    <h3 class="text-2xl font-bold text-slate-900">GH₵<?= number_format(array_sum(array_column($preview_data, 'amount')), 2) ?></h3>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Value</p>
                </div>
            </div>
        </div>

        <!-- Billing Details -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
            <h5 class="text-sm font-semibold text-slate-900 mb-4 flex items-center gap-2"><i class="fas fa-info-circle text-slate-400"></i> Execution Parameters</h5>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <strong class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Semester</strong>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($semester) ?></span>
                </div>
                <div>
                    <strong class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Due Date</strong>
                    <span class="font-medium text-slate-800"><?= htmlspecialchars($due_date) ?></span>
                </div>
                <div>
                    <strong class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Classes</strong>
                    <span class="font-medium text-slate-800"><?= $class_filter === 'all' ? 'All Classes' : htmlspecialchars($class_filter) ?></span>
                </div>
                <div>
                    <strong class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fees</strong>
                    <span class="font-medium text-slate-800"><?= count($fee_ids) ?> selected</span>
                </div>
            </div>
            <?php if ($notes): ?>
                <div class="mt-4 pt-4 border-t border-slate-100 text-sm">
                    <strong class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Notes:</strong> <span class="font-medium text-slate-800"><?= htmlspecialchars($notes) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($error_count > 0): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                <i class="fas fa-exclamation-triangle"></i>
                <span><strong><?= $error_count ?> assignments were skipped</strong> because fees are not configured for certain classes or categories.</span>
            </div>
        <?php endif; ?>

        <?php if ($total_assignments > 0): ?>
            <!-- Preview Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                <div class="bg-slate-50 border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                    <h5 class="text-sm font-semibold text-slate-800"><i class="fas fa-list text-slate-400 mr-2"></i> Assignments Overview</h5>
                    <span class="bg-slate-200 text-slate-700 py-1 px-3 rounded-full text-xs font-bold"><?= $total_assignments ?> target(s)</span>
                </div>
                <div class="overflow-x-auto max-h-[500px]">
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 sticky top-0 border-b border-slate-200 text-xs uppercase font-semibold text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Student Name</th>
                                <th class="px-6 py-3">Class</th>
                                <th class="px-6 py-3">Fee Category</th>
                                <th class="px-6 py-3 text-right">Amount (GH₵)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php 
                            $current_class = '';
                            foreach ($preview_data as $item): 
                                if ($current_class !== $item['student_class']) {
                                    $current_class = $item['student_class'];
                                    echo '<tr class="bg-slate-50/50 border-y border-slate-200"><td colspan="4" class="px-6 py-2 text-xs font-bold text-slate-700 uppercase tracking-wider">' . htmlspecialchars($current_class) . '</td></tr>';
                                }
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-3 font-medium text-slate-900"><?= htmlspecialchars($item['student_name']) ?></td>
                                    <td class="px-6 py-3"><?= htmlspecialchars($item['student_class']) ?></td>
                                    <td class="px-6 py-3"><?= htmlspecialchars($item['fee_name']) ?></td>
                                    <td class="px-6 py-3 text-right font-semibold text-slate-900"><?= number_format($item['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Confirmation Form -->
            <form method="POST" action="bulk_semester_billing.php" class="flex justify-end gap-3 items-center border-t border-slate-200 pt-6">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="semester" value="<?= htmlspecialchars($semester) ?>">
                <input type="hidden" name="due_date" value="<?= htmlspecialchars($due_date) ?>">
                <input type="hidden" name="class_filter" value="<?= htmlspecialchars($class_filter) ?>">
                <input type="hidden" name="notes" value="<?= htmlspecialchars($notes) ?>">
                <input type="hidden" name="selected_fees" value="<?= htmlspecialchars($selected_fees) ?>">
                
                <a href="generate_semester_bills.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-times text-slate-400"></i> Cancel
                </a>
                <button type="submit" class="px-6 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Confirm & Assign <?= $total_assignments ?> Fees
                </button>
            </form>
        <?php else: ?>
            <div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-6 py-8 rounded-xl text-center shadow-sm">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center text-blue-500 text-3xl mx-auto mb-4 shadow-sm border border-blue-100">
                    <i class="fas fa-info-circle"></i>
                </div>
                <h3 class="text-lg font-semibold text-blue-900 mb-2">No Assignments Required</h3>
                <p class="text-sm">All selected students already have these fees assigned for this semester or fees aren't configured for them.</p>
            </div>
            <div class="flex justify-center">
                <a href="generate_semester_bills.php" class="px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Back to Form
                </a>
            </div>
        <?php endif; ?>
        </div>
    </main>
</body>
</html>

