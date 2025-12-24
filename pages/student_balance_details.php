<?php 
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/student_balance_functions.php';

$student_id = intval($_GET['id'] ?? 0);

if ($student_id === 0) {
    header('Location: student_balances.php');
    exit;
}

// Get student balance information
$student_balance = getStudentBalance($conn, $student_id);
if (!$student_balance) {
    header('Location: student_balances.php');
    exit;
}

// Get outstanding fees
$outstanding_fees = getStudentOutstandingFees($conn, $student_id);

// Get payment history
$payment_history = getStudentPaymentHistory($conn, $student_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student_balance['student_name']); ?> - Student Bill & Balance - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { background: #f6f8fa; }
        .student-header {
            background: linear-gradient(120deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 2.5rem 0 2rem 0;
            margin-bottom: 2.5rem;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 8px 32px rgba(106,17,203,0.08);
        }
        .student-header .avatar {
            width: 80px; height: 80px; border-radius: 50%; background: #fff; color: #2575fc; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; margin-right: 1.5rem; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        .balance-summary {
            background: #fff;
            border-radius: 1.5rem;
            padding: 2.5rem 2rem 2rem 2rem;
            box-shadow: 0 4px 24px rgba(37,117,252,0.07);
            margin-bottom: 2.5rem;
        }
        .summary-item {
            border-radius: 1rem;
            padding: 1.5rem 1rem;
            margin-bottom: 1rem;
            background: #f8fafd;
            box-shadow: 0 2px 8px rgba(37,117,252,0.03);
        }
        .summary-item .icon {
            font-size: 2.2rem;
            margin-bottom: 0.5rem;
        }
        .summary-item .label {
            font-size: 1.1rem;
            color: #6c757d;
        }
        .summary-item .value {
            font-size: 1.7rem;
            font-weight: 700;
        }
        .summary-item.owing .value { color: #dc3545; }
        .summary-item.paid .value { color: #28a745; }
        .summary-item.balance .value { color: #2575fc; }
        .summary-item.pending .value { color: #ffc107; }
        .card-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2575fc;
            margin-bottom: 1rem;
        }
        .fee-card, .payment-card {
            border-radius: 1rem;
            box-shadow: 0 2px 12px rgba(37,117,252,0.04);
            margin-bottom: 1.2rem;
            border: none;
        }
        .fee-card.overdue { border-left: 5px solid #dc3545; }
        .fee-card.due-soon { border-left: 5px solid #ffc107; }
        .fee-card.pending { border-left: 5px solid #6c757d; }
        .fee-card .badge, .payment-card .badge { font-size: 0.95rem; }
        .fee-card .fw-bold, .payment-card .fw-bold { font-size: 1.1rem; }
        .payment-card { border-left: 5px solid #28a745; }
        .quick-actions .btn { min-width: 160px; margin-bottom: 0.5rem; }
        @media (max-width: 767px) {
            .student-header { text-align: center; padding: 2rem 0 1.5rem 0; }
            .student-header .avatar { margin: 0 auto 1rem auto; }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-3">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>Salba Montessori
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link text-primary" href="student_balances.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Balances
                </a>
            </div>
        </div>
    </nav>

    <!-- Student Header -->
    <div class="student-header mb-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2 d-flex justify-content-center align-items-center">
                    <div class="avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div class="col-md-7">
                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($student_balance['student_name']); ?></h2>
                    <div class="mb-2">
                        <span class="badge bg-primary fs-6"><i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($student_balance['class']); ?></span>
                        <?php if ($student_balance['student_status'] === 'inactive'): ?>
                            <span class="badge bg-secondary ms-2">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3 text-md-end mt-3 mt-md-0">
                    <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>" class="btn btn-success btn-lg me-2 mb-2">
                        <i class="fas fa-credit-card me-2"></i>Record Payment
                    </a>
                    <a href="assign_fee_form.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-light btn-lg mb-2">
                        <i class="fas fa-plus me-2"></i>Assign Fee
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Balance Summary -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm p-3">
                    <div class="card-section-title mb-2"><i class="fas fa-list text-primary me-2"></i>Student Bill & Payment Details</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Fee Name / Payment</th>
                                    <th>Amount (GH₵)</th>
                                    <th>Date</th>
                                    <th>Term</th>
                                    <th>Status</th>
                                    <th>Receipt No</th>
                                    <th>Description / Notes</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Outstanding Fees -->
                                <?php if (!empty($outstanding_fees)): ?>
                                    <?php foreach($outstanding_fees as $fee): ?>
                                    <tr>
                                        <td><span class="badge bg-danger">Fee</span></td>
                                        <td><?php echo htmlspecialchars($fee['fee_name']); ?></td>
                                        <td class="fw-bold">GH₵<?php echo number_format($fee['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($fee['due_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($fee['term'] ?? ''); ?></td>
                                        <td>
                                            <span class="badge <?php 
                                                echo $fee['payment_status'] === 'Overdue' ? 'bg-danger' : 
                                                    ($fee['payment_status'] === 'Due Soon' ? 'bg-warning text-dark' : 'bg-secondary'); 
                                            ?>">
                                                <?php echo $fee['payment_status']; ?>
                                            </span>
                                        </td>
                                        <td></td>
                                        <td><?php if (!empty($fee['notes'])): ?><i class="fas fa-sticky-note me-1"></i> <?php echo htmlspecialchars($fee['notes']); ?><?php endif; ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="record_payment_form.php?student_id=<?php echo $student_id; ?>&fee_id=<?php echo $fee['id']; ?>&amount=<?php echo $fee['amount']; ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-credit-card me-1"></i>Pay Now
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editFee(<?php echo $fee['id']; ?>, <?php echo $student_id; ?>, '<?php echo htmlspecialchars($fee['fee_name'], ENT_QUOTES); ?>', <?php echo $fee['amount']; ?>, '<?php echo $fee['due_date']; ?>', '<?php echo htmlspecialchars($fee['term'] ?? '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars($fee['notes'] ?? '', ENT_QUOTES); ?>')"
                                                        title="Edit this fee assignment">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="unassignFee(<?php echo $fee['id']; ?>, <?php echo $student_id; ?>, '<?php echo htmlspecialchars($fee['fee_name'], ENT_QUOTES); ?>', <?php echo $fee['amount']; ?>)"
                                                        title="Remove this fee assignment">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-success">All Fees Paid!</td>
                                    </tr>
                                <?php endif; ?>
                                <!-- Payment History -->
                                <?php if (!empty($payment_history)): ?>
                                    <?php foreach($payment_history as $payment): ?>
                                    <tr>
                                        <td><span class="badge bg-success">Payment</span></td>
                                        <td>Payment</td>
                                        <td class="fw-bold">GH₵<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td></td>
                                        <td><span class="badge bg-success"><i class="fas fa-check"></i> Paid</span></td>
                                        <td><?php echo htmlspecialchars($payment['receipt_no'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($payment['description'] ?? ''); ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="edit_payment_form.php?payment_id=<?php echo $payment['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit this payment">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deletePayment(<?php echo $payment['id']; ?>, <?php echo $student_id; ?>)" title="Delete this payment">
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
                                        <td colspan="9" class="text-center text-muted">No Payments Yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-5 mb-4 quick-actions justify-content-center">
            <div class="col-auto">
                <a href="student_balances.php" class="btn btn-outline-secondary">
                    <i class="fas fa-balance-scale me-2"></i>All Balances
                </a>
            </div>
            <div class="col-auto">
                <a href="view_students.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>All Students
                </a>
            </div>
            <div class="col-auto">
                <a href="view_payments.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-success">
                    <i class="fas fa-history me-2"></i>Full Payment History
                </a>
            </div>
            <div class="col-auto">
                <a href="dashboard.php" class="btn btn-outline-info">
                    <i class="fas fa-home me-2"></i>Dashboard
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
                        <i class="fas fa-edit me-2"></i>Edit Fee Assignment
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editFeeForm">
                    <div class="modal-body">
                        <input type="hidden" id="editStudentFeeId" name="student_fee_id">
                        <input type="hidden" id="editStudentId" name="student_id" value="<?php echo $student_id; ?>">
                        
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-1" id="editModalFeeName">Fee Name</h6>
                                        <p class="card-text text-muted">
                                            <strong>Student:</strong> <?php echo htmlspecialchars($student_balance['student_name']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editFeeAmount" class="form-label">
                                    <i class="fas fa-money-bill me-1"></i>Amount (GH₵)
                                </label>
                                <input type="number" step="0.01" min="0" class="form-control" id="editFeeAmount" name="amount" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editFeeDueDate" class="form-label">
                                    <i class="fas fa-calendar me-1"></i>Due Date
                                </label>
                                <input type="date" class="form-control" id="editFeeDueDate" name="due_date" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editFeeTerm" class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>Term/Period
                                </label>
                                <select class="form-select" id="editFeeTerm" name="term">
                                    <option value="">Select Term...</option>
                                    <option value="1st Term">1st Term</option>
                                    <option value="2nd Term">2nd Term</option>
                                    <option value="3rd Term">3rd Term</option>
                                    <option value="Annual">Annual</option>
                                    <option value="One-time">One-time</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editFeeStatus" class="form-label">
                                    <i class="fas fa-flag me-1"></i>Status
                                </label>
                                <select class="form-select" id="editFeeStatus" name="status">
                                    <option value="pending">Pending</option>
                                    <option value="due">Due</option>
                                    <option value="overdue">Overdue</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editFeeNotes" class="form-label">
                                <i class="fas fa-sticky-note me-1"></i>Notes
                            </label>
                            <textarea class="form-control" id="editFeeNotes" name="notes" rows="3" placeholder="Add any notes or comments about this fee..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
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
                        <i class="fas fa-exclamation-triangle me-2"></i>Unassign Fee
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-warning me-2"></i>
                        <strong>Warning:</strong> This action cannot be undone!
                    </div>
                    <p>Are you sure you want to unassign the following fee?</p>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-1" id="modalFeeName">Fee Name</h6>
                            <p class="card-text">
                                <strong>Amount:</strong> <span id="modalFeeAmount">GH₵0.00</span><br>
                                <strong>Student:</strong> <?php echo htmlspecialchars($student_balance['student_name']); ?>
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        This will completely remove the fee assignment. If the student needs to pay this fee later, you will need to reassign it.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmUnassignBtn">
                        <i class="fas fa-trash me-1"></i>Yes, Unassign Fee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
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
            document.getElementById('modalFeeAmount').textContent = 'GH₵' + parseFloat(feeAmount).toFixed(2);
            
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
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
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
                this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processing...';
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
                    document.getElementById('confirmUnassignBtn').innerHTML = '<i class="fas fa-trash me-1"></i>Yes, Unassign Fee';
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
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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