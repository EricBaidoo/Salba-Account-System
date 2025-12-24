<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Get fees with their associated amounts for class-based and category fees
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type, f.description, f.created_at,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GH₵', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(fa.category, ':GH₵', FORMAT(fa.amount, 2))
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type, f.description, f.created_at
    ORDER BY f.id DESC";

$result = $conn->query($fees_query);

// Get summary statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_fees,
        SUM(CASE WHEN fee_type = 'fixed' THEN 1 ELSE 0 END) as fixed_fees,
        SUM(CASE WHEN fee_type = 'class_based' THEN 1 ELSE 0 END) as class_based_fees,
        SUM(CASE WHEN fee_type = 'category' THEN 1 ELSE 0 END) as category_fees
    FROM fees
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Fees - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-money-bill-wave me-2"></i>Fee Management</h1>
                    <p class="clean-page-subtitle">
                        View and manage all school fees
                        <?php $ct = getCurrentTerm($conn); $cy = getAcademicYear($conn); ?>
                        <span class="clean-badge clean-badge-primary ms-2"><i class="fas fa-calendar-alt me-1"></i><?php echo htmlspecialchars($ct); ?></span>
                        <span class="clean-badge clean-badge-info ms-1"><i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $cy)); ?></span>
                    </p>
                </div>
                <div>
                    <a href="add_fee_form.php" class="btn-clean-primary me-2">
                        <i class="fas fa-plus"></i> ADD FEE
                    </a>
                    <a href="assign_fee_form.php" class="btn-clean-success">
                        <i class="fas fa-user-tag"></i> ASSIGN
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Statistics Cards -->
        <div class="clean-stats-grid">
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['total_fees']; ?></div>
                <div class="clean-stat-label">Total Fees</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['fixed_fees']; ?></div>
                <div class="clean-stat-label">Fixed Fees</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['class_based_fees']; ?></div>
                <div class="clean-stat-label">Class-Based</div>
            </div>
            <div class="clean-stat-item">
                <div class="clean-stat-value"><?php echo $stats['category_fees']; ?></div>
                <div class="clean-stat-label">Category Fees</div>
            </div>
        </div>

        <!-- Fees Grid -->
        <div class="row">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="clean-card h-100">
                            <div class="p-4">
                                <!-- Fee Header -->
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($row['name']); ?></h5>
                                        <small class="text-muted">#<?php echo $row['id']; ?></small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="assign_fee_form.php?fee_id=<?php echo $row['id']; ?>">
                                                <i class="fas fa-user-tag me-2"></i>Assign to Student
                                            </a></li>
                                            <li><a class="dropdown-item" href="edit_fee.php?fee_id=<?php echo $row['id']; ?>">
                                                <i class="fas fa-edit me-2"></i>Edit Fee
                                            </a></li>
                                            <li><button class="dropdown-item" onclick="viewFeeDetails(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye me-2"></i>View Details
                                            </button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-danger" onclick="deleteFee(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash me-2"></i>Delete Fee
                                            </button></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Fee Type Badge -->
                                <div class="mb-3">
                                    <?php
                                    $type_class = '';
                                    $type_text = '';
                                    switch($row['fee_type']) {
                                        case 'fixed':
                                            $type_class = 'clean-badge-success';
                                            $type_text = 'Fixed Amount';
                                            break;
                                        case 'class_based':
                                            $type_class = 'clean-badge-primary';
                                            $type_text = 'Class Based';
                                            break;
                                        case 'category':
                                            $type_class = 'clean-badge-warning';
                                            $type_text = 'Category Based';
                                            break;
                                    }
                                    ?>
                                    <span class="clean-badge <?php echo $type_class; ?>">
                                        <?php echo $type_text; ?>
                                    </span>
                                </div>

                                <!-- Amount Display -->
                                <div class="mb-3">
                                    <?php if ($row['fee_type'] === 'fixed'): ?>
                                        <div class="d-flex align-items-center">
                                            <h3 class="text-success mb-0">GH₵<?php echo number_format($row['amount'] ?? 0, 2); ?></h3>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <h5 class="text-primary mb-1">Variable Amount</h5>
                                            <?php if (!empty($row['amount_details'])): ?>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars(str_replace(' | ', ' • ', $row['amount_details'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($row['description'])): ?>
                                    <p class="text-muted small mb-3"><?php echo htmlspecialchars($row['description']); ?></p>
                                <?php endif; ?>

                                <!-- Footer -->
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <small class="text-muted">
                                        <?php echo $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : 'N/A'; ?>
                                    </small>
                                    <a href="assign_fee_form.php?fee_id=<?php echo $row['id']; ?>" class="btn-clean-primary btn-clean-sm">
                                        <i class="fas fa-user-tag me-1"></i>Assign
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="clean-empty-state">
                        <div class="clean-empty-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <h4 class="clean-empty-title">No Fees Created Yet</h4>
                        <p class="clean-empty-text">Start by creating your first fee to manage student payments.</p>
                        <a href="add_fee_form.php" class="btn-clean-primary">
                            <i class="fas fa-plus me-2"></i>Create First Fee
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-5 pt-4 border-top">
            <a href="view_assigned_fees.php" class="btn-clean-outline me-3">
                <i class="fas fa-list-alt me-2"></i>View Fee Assignments
            </a>
            <a href="view_payments.php" class="btn-clean-success">
                <i class="fas fa-credit-card me-2"></i>View Payments
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFeeDetails(feeId) {
            // TODO: Implement fee details modal
            alert('View details for fee #' + feeId);
        }

        function editFee(feeId) {
            // TODO: Implement edit functionality
            alert('Edit fee #' + feeId);
        }

        function deleteFee(feeId, feeName) {
            if (confirm('Are you sure you want to delete the fee "' + feeName + '"?\n\nThis action cannot be undone and may affect existing assignments.')) {
                // TODO: Implement delete functionality
                alert('Delete fee #' + feeId);
            }
        }
    </script>
</body>
</html>