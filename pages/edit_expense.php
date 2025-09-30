<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';
$id = intval($_GET['id'] ?? 0);
$message = '';
if ($id > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $category = trim($_POST['category']);
        $amount = floatval($_POST['amount']);
        $expense_date = $_POST['expense_date'];
        $description = trim($_POST['description']);
        $stmt = $conn->prepare("UPDATE expenses SET category=?, amount=?, expense_date=?, description=? WHERE id=?");
        $stmt->bind_param('sdssi', $category, $amount, $expense_date, $description, $id);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Expense updated!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    }
    $exp = $conn->query("SELECT * FROM expenses WHERE id=$id")->fetch_assoc();
    $cat_result = $conn->query("SELECT name FROM expense_categories ORDER BY name ASC");
} else {
    die('Invalid expense ID.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Expense</h2>
    <?php if ($message) echo $message; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select class="form-select" id="category" name="category" required>
                <?php while($cat_row = $cat_result->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($cat_row['name']) ?>" <?= $exp['category'] == $cat_row['name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat_row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" class="form-control" id="amount" name="amount" value="<?= htmlspecialchars($exp['amount']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="expense_date" class="form-label">Expense Date</label>
            <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?= htmlspecialchars($exp['expense_date']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description"><?= htmlspecialchars($exp['description']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="view_expenses.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
