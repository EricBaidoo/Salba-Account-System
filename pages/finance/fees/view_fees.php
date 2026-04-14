<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
require_finance_access();
// Load category id=>name map
include_once '../../includes/fee_category_map.php';

// Get fees with their associated amounts for class-based and category fees
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type, f.description, f.created_at,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GHâ‚µ', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(fa.category, ':GHâ‚µ', FORMAT(fa.amount, 2))
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-money-bill-wave mr-2"></i>Fee Management</h1>
                    <p class="clean-page-subtitle">
                        View and manage all school fees
                        <?php $ct = getCurrentTerm($conn); $cy = getAcademicYear($conn); ?>
                        <span class="clean-badge clean-badge-primary ml-2"><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($ct); ?></span>
                        <span class="clean-badge clean-badge-info ml-1"><i class="fas fa-graduation-cap mr-1"></i><?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $cy)); ?></span>
                    </p>
                </div>
                <div>
                    <a href="add_fee_form.php" class="px-3 py-2 rounded-clean-primary mr-2">
                        <i class="fas fa-plus"></i> ADD FEE
                    </a>
                    <a href="assign_fee_form.php" class="px-3 py-2 rounded-clean-success">
                        <i class="fas fa-user-tag"></i> ASSIGN
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
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
        <div class="flex flex-wrap">
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="lg:col-span-6 xl:col-span-4 mb-">
                        <div class="clean-bg-white rounded shadow h-full">
                            <div class="p-4">
                                <!-- Fee Header -->
                                <div class="flex justify-between items-start mb-">
                                    <div>
                                        <h5 class="bg-white rounded shadow-title mb-"><?php echo htmlspecialchars($row['name']); ?></h5>
                                        <small class="text-gray-600">#<?php echo $row['id']; ?></small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="assign_fee_form.php?fee_id=<?php echo $row['id']; ?>">
                                                <i class="fas fa-user-tag mr-2"></i>Assign to Student
                                            </a></li>
                                            <li><a class="dropdown-item" href="edit_fee.php?fee_id=<?php echo $row['id']; ?>">
                                                <i class="fas fa-edit mr-2"></i>Edit Fee
                                            </a></li>
                                            <li><button class="dropdown-item" onclick="viewFeeDetails(<?php echo $row['id']; ?>)">
                                                <i class="fas fa-eye mr-2"></i>View Details
                                            </button></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><button class="dropdown-item text-red-600" onclick="deleteFee(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash mr-2"></i>Delete Fee
                                            </button></li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Fee Type Badge -->
                                <div class="mb-">
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
                                <div class="mb-">
                                    <?php if ($row['fee_type'] === 'fixed'): ?>
                                        <div class="flex items-center">
                                            <h3 class="text-green-600 mb-">GHâ‚µ<?php echo number_format($row['amount'] ?? 0, 2); ?></h3>
                                        </div>
                                    <?php else: ?>
                                        <div>
                                            <h5 class="text-primary mb-">Variable Amount</h5>
                                            <?php if (!empty($row['amount_details'])): ?>
                                                <small class="text-gray-600">
                                                    <?php
                                                    // Replace category IDs with names for display
                                                    $details = explode(' | ', $row['amount_details']);
                                                    $out = [];
                                                    foreach ($details as $d) {
                                                        if (preg_match('/^([0-9]+):GHâ‚µ/', $d, $m) && isset($category_map[$m[1]])) {
                                                            $out[] = str_replace($m[1], $category_map[$m[1]], $d);
                                                        } else {
                                                            $out[] = $d;
                                                        }
                                                    }
                                                    echo htmlspecialchars(implode(' â€¢ ', $out));
                                                    ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Description -->
                                <?php if (!empty($row['description'])): ?>
                                    <p class="text-gray-600 small mb-"><?php echo htmlspecialchars($row['description']); ?></p>
                                <?php endif; ?>

                                <!-- Footer -->
                                <div class="flex justify-between items-center mt-auto">
                                    <small class="text-gray-600">
                                        <?php echo $row['created_at'] ? date('M j, Y', strtotime($row['created_at'])) : 'N/A'; ?>
                                    </small>
                                    <a href="assign_fee_form.php?fee_id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-primary px-3 py-2 rounded-clean-sm">
                                        <i class="fas fa-user-tag mr-1"></i>Assign
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
                        <a href="add_fee_form.php" class="px-3 py-2 rounded-clean-primary">
                            <i class="fas fa-plus mr-2"></i>Create First Fee
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-5 pt-4 border-top">
            <a href="view_assigned_fees.php" class="px-3 py-2 rounded-clean-outline mr-3">
                <i class="fas fa-list-alt mr-2"></i>View Fee Assignments
            </a>
            <a href="../payments/view_payments.php" class="px-3 py-2 rounded-clean-success">
                <i class="fas fa-credit-bg-white rounded shadow mr-2"></i>View Payments
            </a>
        </div>
    </div>

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
