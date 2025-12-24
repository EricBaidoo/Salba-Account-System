<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Fetch all expenses
$result = $conn->query("SELECT e.*, ec.name AS category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id ORDER BY e.expense_date DESC, e.id DESC");

// Fetch summary by category
$summary = [];
$sum_query = $conn->query("SELECT ec.name AS category, SUM(e.amount) as total FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id GROUP BY ec.name ORDER BY ec.name");
while ($row = $sum_query->fetch_assoc()) {
    $summary[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Expenses - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @media print {
            .d-print-none { display: none !important; }
            .print-header { display: block !important; margin-bottom: 16px; }
        }
        @media screen {
            .print-header { display: none; }
        }
    </style>
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
                    <h1 class="clean-page-title"><i class="fas fa-list-alt me-2"></i>Expenses Overview</h1>
                    <p class="clean-page-subtitle">Track and analyze all expenses by category for better financial management</p>
                </div>
                <div class="d-flex gap-2 d-print-none">
                    <a href="#" onclick="window.print()" class="btn-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="add_expense_form.php" class="btn-clean-primary">
                        <i class="fas fa-plus"></i> ADD EXPENSE
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Category Summary -->
        <?php if (count($summary) > 0): ?>
        <div class="row mb-4 d-print-none">
            <?php foreach ($summary as $cat): ?>
                <div class="col-md-4 col-lg-3 mb-3">
                    <div class="clean-card text-center">
                        <div class="p-4">
                            <div class="mb-2"><i class="fas fa-folder-open fa-2x text-primary"></i></div>
                            <h5 class="mb-2"><?php echo htmlspecialchars($cat['category'] ?? 'Uncategorized'); ?></h5>
                            <div class="h3 text-success mb-0">GH₵<?php echo number_format($cat['total'], 2); ?></div>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="clean-alert clean-alert-info mb-4">
            <i class="fas fa-info-circle"></i>
            <span>No expenses recorded yet.</span>
        </div>
        <?php endif; ?>
        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-0"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Expenses Overview</div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Expenses Table -->
        <div class="clean-card">
            <div class="clean-card-header">
                <h5 class="clean-card-title"><i class="fas fa-table me-2"></i>All Expenses</h5>
            </div>
            <div class="clean-table-scroll">
                <table class="clean-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Expense Date</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><span class="clean-badge clean-badge-primary">#<?php echo $row['id']; ?></span></td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?? 'Uncategorized'); ?></td>
                            <td><strong class="text-success">GH₵<?php echo number_format($row['amount'], 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($row['expense_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td>
                                <div class="clean-actions">
                                    <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_expense.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm text-danger" onclick="return confirm('Delete this expense?');" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>