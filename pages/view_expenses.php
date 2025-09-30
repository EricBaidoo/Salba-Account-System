<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}

// Fetch all expenses
$result = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC, id DESC");

// Fetch summary by category
$summary = [];
$sum_query = $conn->query("SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY category");
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
</head>
<body>
    <div class="container mt-5">
        <div class="mb-3 text-end">
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-home me-1"></i>Back to Dashboard</a>
        </div>
        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-0"><i class="fas fa-list-alt me-2"></i>Expenses Overview</h2>
            <p class="lead mb-0">Track and analyze all expenses by category for better financial management.</p>
        </div>
        <div class="row mb-4">
            <?php if (count($summary) > 0): ?>
                <?php foreach ($summary as $cat): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card dashboard-card bg-primary text-white h-100">
                            <div class="card-body text-center">
                                <h5 class="card-title mb-2"><i class="fas fa-folder-open me-2"></i><?php echo htmlspecialchars($cat['category']); ?></h5>
                                <div class="display-6">GH₵<?php echo number_format($cat['total'], 2); ?></div>
                                <div class="small">Total Spent</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No expenses recorded yet.</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="main-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0"><i class="fas fa-table me-2"></i>All Expenses</h4>
                <a href="add_expense_form.php" class="btn btn-primary"><i class="fas fa-plus-circle me-1"></i>Add New Expense</a>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
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
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td>GH₵<?php echo number_format($row['amount'], 2); ?></td>
                            <td><?php echo $row['expense_date']; ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                                <td>
                                    <a href="edit_expense.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                    <a href="delete_expense.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this expense?');"><i class="fas fa-trash"></i></a>
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