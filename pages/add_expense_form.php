<?php 
include '../includes/auth_check.php'; 
include '../includes/db_connect.php';
// Fetch categories from DB
$cat_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense - Salba Montessori Accounting</title>
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
            <h2 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Add New Expense</h2>
            <p class="lead mb-0">Record a new expense and assign it to a category for better reporting.</p>
        </div>
        <div class="main-content p-4">
            <form action="add_expense.php" method="POST">
                <div class="mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="" disabled selected>Select category</option>
                        <?php while($cat_row = $cat_result->fetch_assoc()): ?>
                            <option value="<?= $cat_row['id'] ?>"><?= htmlspecialchars($cat_row['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                </div>
                <div class="mb-3">
                    <label for="expense_date" class="form-label">Expense Date</label>
                    <input type="date" class="form-control" id="expense_date" name="expense_date" required>
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description"></textarea>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Add Expense</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>