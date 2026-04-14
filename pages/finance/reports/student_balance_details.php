<?php 
include '../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../../includes/db_connect.php';
include '../../includes/system_settings.php';
include '../../includes/student_balance_functions.php';
require_once '../../includes/term_helpers.php';

$student_id = intval($_GET['id'] ?? 0);

// Get current term from system settings or URL parameter
$current_term = getCurrentTerm($conn);
$selected_term = $_GET['term'] ?? $current_term;
$default_academic_year = getAcademicYear($conn);
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

if ($student_id === 0) {
    header('Location: student_balances.php');
    exit;
}

// Ensure arrears are carried forward as an assigned fee in the current term BEFORE computing balances
ensureArrearsAssignment($conn, $student_id, $selected_term, $selected_academic_year);

// Get student balance information for selected term/year (now includes arrears assignment)
$student_balance = getStudentBalance($conn, $student_id, $selected_term, $selected_academic_year);
if (!$student_balance) {
    header('Location: student_balances.php');
    exit;
}

// Get all assigned fees for selected term/year (pending or paid)
$term_fees = getStudentTermFees($conn, $student_id, $selected_term, $selected_academic_year);

// Get payment history for selected term/year
$payment_history = getStudentPaymentHistory($conn, $student_id, $selected_term, $selected_academic_year);

// Arrears are now represented as a fee within the current term via ensureArrearsAssignment
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student_balance['student_name']); ?> - Student Bill & Balance - Salba Montessori</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="student_balances.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Balances
                </a>
            </div>
            <div class="flex justify-between items-center flex-wrap">
                <div class="mb- mb-md-0">
                    <h1 class="clean-page-title"><i class="fas fa-user-graduate mr-2"></i><?php echo htmlspecialchars($student_balance['student_name']); ?></h1>
                    <p class="clean-page-subtitle">
                        <span class="clean-badge clean-badge-primary mr-2">
                            <i class="fas fa-layer-group mr-1"></i><?php echo htmlspecialchars($student_balance['class']); ?>
                        </span>
                        <?php if ($student_balance['student_status'] === 'inactive'): ?>
                            <span class="clean-badge clean-badge-danger">Inactive</span>
                        <?php endif; ?>
                    </p>
                    <p class="text-gray-600 small"><i class="fas fa-calendar-alt mr-1"></i>Term: <?php echo htmlspecialchars($selected_term); ?> | <i class="fas fa-graduation-cap mr-1"></i>Year: <?php echo htmlspecialchars($display_academic_year); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
                    <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>&term=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="px-3 py-2 rounded px-3 py-2 rounded-light px-3 py-2 rounded-lg shadow-sm block d-md-inline-block mb-">
                        <i class="fas fa-credit-bg-white rounded shadow mr-2"></i>Record Payment
                    </a>
                    <a href="assign_fee_form.php?student_id=<?php echo $student_id; ?>&term=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="px-3 py-2 rounded px-3 py-2 rounded-outline-light px-3 py-2 rounded-lg shadow-sm block d-md-inline-block">
                        <i class="fas fa-plus mr-2"></i>Assign Fee
                    </a>
                    <a href="download_term_invoice.php?student_id=<?php echo $student_id; ?>&term=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 px-3 py-2 rounded-lg shadow-sm block d-md-inline-block ml-md-2 mt-2 mt-md-0">
                        <i class="fas fa-download mr-2"></i>Download Invoice (PDF)
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto mb-">
        <div class="flex justify-content-end">
            <a class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" href="reallocate_term_payments.php?student_id=<?php echo intval($student_id); ?>&term=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>">Re-allocate payments for this term</a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto">
        <!-- Term Selector -->
        <div class="row mb- g-4">
            <div class="md:col-span-6">
                <div class="bg-white rounded shadow border-0 shadow-sm h-full">
                    <div class="bg-white rounded shadow-body">
                        <label for="termFilter" class="block text-sm font-medium mb- fw-bold">
                            <i class="fas fa-calendar-alt text-primary mr-2"></i>Academic Term
                        </label>
                        <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="termFilter" onchange="window.location.href='?id=<?php echo $student_id; ?>&term=' + this.value + '&academic_year=' + encodeURIComponent(document.getElementById('yearFilter').value);">
                            <?php 
                            $available_terms = getAvailableTerms();
                            foreach ($available_terms as $term): 
                            ?>
                                <option value="<?php echo htmlspecialchars($term); ?>" <?php echo $term === $selected_term ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term); ?>
                                    <?php if ($term === $current_term): ?>(Current)<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mt-3">
                            <label for="yearFilter" class="block text-sm font-medium mb- fw-bold">
                                <i class="fas fa-graduation-cap text-blue-600 mr-2"></i>Academic Year
                            </label>
                            <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="yearFilter" onchange="window.location.href='?id=<?php echo $student_id; ?>&term=' + encodeURIComponent(document.getElementById('termFilter').value) + '&academic_year=' + this.value;">
                                <?php 
                                $year_options = [];
                                $yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
                                if ($yrs_rs) {
                                    while ($yr = $yrs_rs->fetch_assoc()) {
                                        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
                                    }
                                    $yrs_rs->close();
                                }
                                if (!in_array($default_academic_year, $year_options, true)) { array_unshift($year_options, $default_academic_year); }
                                foreach ($year_options as $yr): ?>
                                    <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $selected_academic_year) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $yr)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="md:col-span-6">
                <div class="bg-white rounded shadow border-0 shadow-sm h-full">
                    <div class="bg-white rounded shadow-body">
                        <h6 class="fw-bold mb-"><i class="fas fa-wallet text-green-600 mr-2"></i>Balance Summary</h6>
                        <div class="row text-center g-2">
                            <div class="col-4">
                                <div class="text-gray-600 small mb-">Total Fees</div>
                                <div class="fw-bold text-primary fs-5">GHâ‚µ<?php echo number_format($student_balance['total_fees'], 2); ?></div>
                                <?php if ($student_balance['arrears'] > 0): ?>
                                    <small class="text-red-600 block mt-1"><i class="fas fa-exclamation-circle"></i> +GHâ‚µ<?php echo number_format($student_balance['arrears'], 2); ?> arrears</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-4">
                                <div class="text-gray-600 small mb-">Payments</div>
                                <div class="fw-bold text-green-600 fs-5">GHâ‚µ<?php echo number_format($student_balance['total_payments'], 2); ?></div>
                            </div>
                            <div class="col-4">
                                <div class="text-gray-600 small mb-">Balance</div>
                                <div class="fw-bold <?php echo $student_balance['net_balance'] > 0 ? 'text-red-600' : 'text-green-600'; ?> fs-5">
                                    GHâ‚µ<?php echo number_format($student_balance['net_balance'], 2); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance Summary -->
        <div class="row g-4 mb-">
            <div class="col-12">
                <div class="bg-white rounded shadow border-0 shadow-sm">
                    <div class="bg-white rounded shadow-body p-4">
                        <div class="bg-white rounded shadow-section-title"><i class="fas fa-list mr-2"></i>Student Bill & Payment Details</div>
                        <div class="clean-w-full border-collapse-scroll">
                        <table class="w-full border-collapse w-full border-collapse-bordered w-full border-collapse-hover align-middle mb-">
                            <thead class="w-full border-collapse-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Fee Name / Payment</th>
                                    <th>Amount (GHâ‚µ)</th>
                                    <th>Date</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Receipt No</th>
                                    <th>Description / Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Term Fees (pending or paid) -->
                                <?php if (!empty($term_fees)): ?>
                                    <?php foreach($term_fees as $fee): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                                $name = strtolower(trim($fee['fee_name']));
                                                $is_ob_fee = ($name === 'outstanding balance' || $name === 'arrears carry forward');
                                            ?>
                                            <span class="badge <?php echo $is_ob_fee ? 'bg-warning text-dark' : 'bg-danger'; ?>">
                                                <i class="fas <?php echo $is_ob_fee ? 'fa-exclamation-circle' : 'fa-file-invoice'; ?> mr-1"></i>
                                                <?php echo $is_ob_fee ? 'Outstanding' : 'Fee'; ?>
                                            </span>
                                        </td>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                        <td class="fw-bold <?php echo ($fee['status'] === 'paid') ? 'text-green-600' : 'text-red-600'; ?>">GHâ‚µ<?php echo number_format($fee['amount'], 2); ?></td>
                                        <td>
                                            <?php if (!empty($fee['due_date'])): ?>
                                                <i class="far fa-calendar mr-1"></i><?php echo date('M j, Y', strtotime($fee['due_date'])); ?>
                                            <?php else: ?>-<?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($fee['term'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge <?php echo ($fee['status'] === 'paid') ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($fee['status']); ?>
                                            </span>
                                        </td>
                                        <td>-</td>
                                        <td><?php if (!empty($fee['notes'])): ?><i class="fas fa-sticky-note mr-1 text-gray-600"></i> <?php echo htmlspecialchars($fee['notes']); ?><?php endif; ?></td>
                                        <td>
                                            <?php if ($is_ob_fee): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700" title="Auto-managed from previous term">
                                                    <i class="fas fa-robot mr-1"></i>Auto-calculated
                                                </span>
                                            <?php else: ?>
                                            <div class="px-3 py-2 rounded-group" role="group">
                                                <?php if ($fee['status'] !== 'paid'): ?>
                                                    <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>&fee_id=<?php echo $fee['id']; ?>&amount=<?php echo $fee['amount']; ?>&term=<?php echo urlencode($selected_term); ?>&academic_year=<?php echo urlencode($selected_academic_year); ?>" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-success" title="Pay this fee">
                                                        <i class="fas fa-credit-bg-white rounded shadow"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-primary" 
                                                        onclick="editFee(<?php echo $fee['id']; ?>, <?php echo $student_id; ?>, '<?php echo htmlspecialchars($fee['fee_name'], ENT_QUOTES); ?>', <?php echo $fee['amount']; ?>, '<?php echo $fee['due_date']; ?>', '<?php echo htmlspecialchars($fee['term'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fee['notes'] ?? '', ENT_QUOTES); ?>')"
                                                        title="Edit this fee assignment">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" 
                                                        onclick="unassignFee(<?php echo $fee['id']; ?>, <?php echo $student_id; ?>, '<?php echo htmlspecialchars($fee['fee_name'], ENT_QUOTES); ?>', <?php echo $fee['amount']; ?>)"
                                                        title="Remove this fee assignment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-green-600 fw-bold py-3">
                                            <i class="fas fa-check-circle mr-2"></i>No fees assigned in this term
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <!-- Payment History -->
                                <?php if (!empty($payment_history)): ?>
                                    <?php foreach($payment_history as $payment): ?>
                                    <tr class="w-full border-collapse-success-light">
                                        <td><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700"><i class="fas fa-money-bill-wave mr-1"></i>Payment</span></td>
                                        <td class="fw-semibold">Payment Received</td>
                                        <td class="fw-bold text-green-600">GHâ‚µ<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><i class="far fa-calendar mr-1"></i><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($payment['term'] ?? ''); ?></td>
                                        <td><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700"><i class="fas fa-check mr-1"></i>Paid</span></td>
                                        <td><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700"><?php echo htmlspecialchars($payment['receipt_no'] ?? 'N/A'); ?></span></td>
                                        <td><?php echo htmlspecialchars($payment['description'] ?? ''); ?></td>
                                        <td>
                                            <div class="px-3 py-2 rounded-group" role="group">
                                                <a href="edit_payment_form.php?payment_id=<?php echo $payment['id']; ?>" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-primary" title="Edit this payment">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" onclick="deletePayment(<?php echo $payment['id']; ?>, <?php echo $student_id; ?>)" title="Delete this payment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
</script>
<script>
function deletePayment(paymentId, studentId) {
    if (!confirm('Are you sure you want to delete this payment?')) return;
    fetch('delete_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ payment_id: paymentId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => {
                if (data.redirect) {
                    window.location.href = window.location.href;
                } else {
                    window.location.reload();
                }
            }, 1200);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        showAlert('danger', 'Error deleting payment.');
    });
}
</script>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-gray-600 py-3">
                                            <i class="fas fa-info-circle mr-2"></i>No Payments Recorded Yet
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-5 mb- quick-actions text-center">
            <div class="col-12 mb-">
                <h5 class="text-gray-600"><i class="fas fa-link mr-2"></i>Quick Actions</h5>
            </div>
            <div class="col-lg-3 md:col-span-6 mb-">
                <a href="student_balances.php" class="px-4 py-2 border border-gray-300 rounded px-3 py-2 rounded-lg w-full shadow-sm">
                    <i class="fas fa-balance-scale mr-2"></i>All Balances
                </a>
            </div>
            <div class="col-lg-3 md:col-span-6 mb-">
                <a href="../../administration/students/view_students.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-primary px-3 py-2 rounded-lg w-full shadow-sm">
                    <i class="fas fa-users mr-2"></i>All Students
                </a>
            </div>
            <div class="col-lg-3 md:col-span-6 mb-">
                <a href="view_payments.php?student_id=<?php echo $student_id; ?>" class="px-3 py-2 rounded px-3 py-2 rounded-outline-success px-3 py-2 rounded-lg w-full shadow-sm">
                    <i class="fas fa-history mr-2"></i>Full Payment History
                </a>
            </div>
            <div class="col-lg-3 md:col-span-6 mb-">
                <a href="../dashboard.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-info px-3 py-2 rounded-lg w-full shadow-sm">
                    <i class="fas fa-home mr-2"></i>Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Edit Fee Modal -->
    <div class="modal fade" id="editFeeModal" tabindex="-1" aria-labelledby="editFeeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="editFeeModalLabel">
                        <i class="fas fa-edit mr-2"></i>Edit Fee Assignment
                    </h5>
                    <button type="button" class="px-3 py-2 rounded-close px-3 py-2 rounded-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editFeeForm">
                    <div class="modal-body">
                        <input type="hidden" id="editStudentFeeId" name="student_fee_id">
                        <input type="hidden" id="editStudentId" name="student_id" value="<?php echo $student_id; ?>">
                        
                        <div class="flex flex-wrap">
                            <div class="col-12 mb-">
                                <div class="bg-white rounded shadow">
                                    <div class="bg-white rounded shadow-body">
                                        <h6 class="bg-white rounded shadow-title mb-" id="editModalFeeName">Fee Name</h6>
                                        <p class="bg-white rounded shadow-text text-gray-600">
                                            <strong>Student:</strong> <?php echo htmlspecialchars($student_balance['student_name']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap">
                            <div class="md:col-span-6 mb-">
                                <label for="editFeeAmount" class="block text-sm font-medium mb-">
                                    <i class="fas fa-money-bill mr-1"></i>Amount (GHâ‚µ)
                                </label>
                                <input type="number" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded" id="editFeeAmount" name="amount" required>
                            </div>
                            <div class="md:col-span-6 mb-">
                                <label for="editFeeDueDate" class="block text-sm font-medium mb-">
                                    <i class="fas fa-calendar mr-1"></i>Due Date
                                </label>
                                <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded" id="editFeeDueDate" name="due_date" required>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap">
                            <div class="md:col-span-6 mb-">
                                <label for="editFeeTerm" class="block text-sm font-medium mb-">
                                    <i class="fas fa-calendar-alt mr-1"></i>Term/Period
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="editFeeTerm" name="term">
                                    <option value="">Select Term...</option>
                                    <?php 
                                        $available_terms = getAvailableTerms();
                                        foreach ($available_terms as $t): ?>
                                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
                                    <?php endforeach; ?>
                                    <option value="Annual">Annual</option>
                                    <option value="One-time">One-time</option>
                                </select>
                                <small class="text-gray-600 block mt-1"><i class="fas fa-info-circle mr-1"></i>Changing the term moves this fee to that term. It may no longer appear in the current view.</small>
                            </div>
                            <div class="md:col-span-6 mb-">
                                <label for="editFeeStatus" class="block text-sm font-medium mb-">
                                    <i class="fas fa-flag mr-1"></i>Status
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="editFeeStatus" name="status">
                                    <option value="pending">Pending</option>
                                    <option value="due">Due</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-">
                            <label for="editFeeNotes" class="block text-sm font-medium mb-">
                                <i class="fas fa-sticky-note mr-1"></i>Notes
                            </label>
                            <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="editFeeNotes" name="notes" rows="3" placeholder="Add any notes or comments about this fee..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded" data-bs-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            <i class="fas fa-save mr-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Unassign Fee Confirmation Modal -->
    <div class="modal fade" id="unassignFeeModal" tabindex="-1" aria-labelledby="unassignFeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="unassignFeeModalLabel">
                        <i class="fas fa-exclamation-triangle mr-2"></i>Unassign Fee
                    </h5>
                    <button type="button" class="px-3 py-2 rounded-close px-3 py-2 rounded-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-4 bg-yellow-100 text-yellow-700 rounded border border-yellow-200">
                        <i class="fas fa-warning mr-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you sure you want to unassign the following fee?</p>
                    <div class="bg-white rounded shadow">
                        <div class="bg-white rounded shadow-body">
                            <h6 class="bg-white rounded shadow-title mb-" id="modalFeeName">Fee Name</h6>
                            <p class="bg-white rounded shadow-text">
                                <strong>Amount:</strong> <span id="modalFeeAmount">GHâ‚µ0.00</span><br>
                                <strong>Student:</strong> <?php echo htmlspecialchars($student_balance['student_name']); ?>
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        This will completely remove the fee assignment. If the student needs to pay this fee later, you will need to reassign it.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="px-4 py-2 bg-gray-600 text-white rounded" data-bs-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>Cancel
                    </button>
                    <button type="button" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700" id="confirmUnassignBtn">
                        <i class="fas fa-trash mr-1"></i>Yes, Unassign Fee
                    </button>
                </div>
            </div>
        </div>
    </div>

        <script>
        let currentFeeId = null;
        let currentStudentId = null;

        function editFee(studentFeeId, studentId, feeName, feeAmount, dueDate, term, notes) {
            // Set form values
            document.getElementById('editStudentFeeId').value = studentFeeId;
            document.getElementById('editStudentId').value = studentId;
            document.getElementById('editModalFeeName').textContent = feeName;
            document.getElementById('editFeeAmount').value = parseFloat(feeAmount).toFixed(2);
            document.getElementById('editFeeDueDate').value = dueDate;
            document.getElementById('editFeeTerm').value = term || '';
            document.getElementById('editFeeNotes').value = notes || '';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('editFeeModal'));
            modal.show();
        }

        function unassignFee(studentFeeId, studentId, feeName, feeAmount) {
            currentFeeId = studentFeeId;
            currentStudentId = studentId;
            
            // Update modal content
            document.getElementById('modalFeeName').textContent = feeName;
            document.getElementById('modalFeeAmount').textContent = 'GHâ‚µ' + parseFloat(feeAmount).toFixed(2);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('unassignFeeModal'));
            modal.show();
        }

        // Handle edit form submission
        document.getElementById('editFeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            // Show loading state
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
            submitBtn.disabled = true;
            
            // Send AJAX request
            fetch('edit_student_fee.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // Close modal and reload page
                    const modal = bootstrap.Modal.getInstance(document.getElementById('editFeeModal'));
                    modal.hide();
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while updating the fee. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Handle confirmation
        document.getElementById('confirmUnassignBtn').addEventListener('click', function() {
            if (currentFeeId && currentStudentId) {
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Processing...';
                this.disabled = true;
                
                // Send AJAX request
                fetch('unassign_fee.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        student_fee_id: currentFeeId,
                        student_id: currentStudentId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showAlert('success', data.message);
                        
                        // Reload page to reflect changes
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Show error message
                        showAlert('danger', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('danger', 'An error occurred while unassigning the fee. Please try again.');
                })
                .finally(() => {
                    // Reset button and close modal
                    document.getElementById('confirmUnassignBtn').innerHTML = '<i class="fas fa-trash mr-1"></i>Yes, Unassign Fee';
                    document.getElementById('confirmUnassignBtn').disabled = false;
                    
                    const modal = bootstrap.Modal.getInstance(document.getElementById('unassignFeeModal'));
                    modal.hide();
                    
                    // Reset variables
                    currentFeeId = null;
                    currentStudentId = null;
                });
            }
        });

        function showAlert(type, message) {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.top = '20px';
            alertDiv.style.right = '20px';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="px-3 py-2 rounded-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            
            // Add to page
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>
