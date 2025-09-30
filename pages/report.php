<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
// Total paid (synced with dashboard and category breakdown)
$total_paid = $conn->query('
    SELECT SUM(total) AS total FROM (
        SELECT (
            SELECT COALESCE(SUM(sf.amount_paid), 0)
            FROM student_fees sf
            WHERE sf.fee_id = f.id
        ) +
        (
            SELECT COALESCE(SUM(p.amount), 0)
            FROM payments p
            WHERE p.fee_id = f.id AND p.payment_type = "general"
        ) AS total
        FROM fees f
    ) t
')->fetch_assoc()['total'] ?? 0;
// Income by fee category (for breakdown)
$income_by_category = $conn->query('
    SELECT f.name AS category,
        (
            SELECT COALESCE(SUM(sf.amount_paid), 0)
            FROM student_fees sf
            WHERE sf.fee_id = f.id
        ) +
        (
            SELECT COALESCE(SUM(p.amount), 0)
            FROM payments p
            WHERE p.fee_id = f.id AND p.payment_type = "general"
        ) AS total
    FROM fees f
    GROUP BY f.id
    ORDER BY f.name
');
$income_rows = [];
while ($row = $income_by_category->fetch_assoc()) {
    $income_rows[] = $row;
}
// Expenses by category
$expenses_by_category = $conn->query('
    SELECT ec.name AS category, SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    GROUP BY ec.name
    ORDER BY ec.name
');
$total_expenses = 0;
$expense_rows = [];
while ($row = $expenses_by_category->fetch_assoc()) {
    $expense_rows[] = $row;
    $total_expenses += $row['total'];
}
$balance = $total_paid - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Income & Expense Report</h2>
            <p class="lead mb-0">See a breakdown of all income and expenses by category.</p>
        </div>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="main-content p-4 mb-4">
                    <h4 class="mb-3 text-success"><i class="fas fa-arrow-down me-2"></i>Income by Category</h4>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Paid (GH₵)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($income_rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>GH₵<?php echo number_format($row['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th>Total Paid (All Payments)</th>
                                <th>GH₵<?php echo number_format($total_paid, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="main-content p-4 mb-4">
                    <h4 class="mb-3 text-danger"><i class="fas fa-arrow-up me-2"></i>Expenses by Category</h4>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Total Spent (GH₵)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expense_rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></td>
                                <td>GH₵<?php echo number_format($row['total'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <th>Total Expenses</th>
                                <th>GH₵<?php echo number_format($total_expenses, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="main-content p-4 mb-4 text-center">
            <h4 class="mb-3"><i class="fas fa-balance-scale me-2"></i>Net Balance</h4>
            <div class="display-6 fw-bold <?php echo (($total_paid - $total_expenses) >= 0) ? 'text-success' : 'text-danger'; ?>">
                GH₵<?php echo number_format($total_paid - $total_expenses, 2); ?>
            </div>
        </div>
        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>