<?php
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
$id = intval($_GET['id'] ?? 0);
$message = '';
if ($id > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Use category_id (int) to match current DB schema
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $amount = floatval($_POST['amount']);
        $expense_date = $_POST['expense_date'];
        $description = trim($_POST['description']);
        
        $stmt = $conn->prepare("UPDATE expenses SET category_id=?, amount=?, expense_date=?, description=? WHERE id=?");
        $stmt->bind_param('idssi', $category_id, $amount, $expense_date, $description, $id);
        if ($stmt->execute()) {
            $message = '<div class="p-4 bg-green-100 text-green-700 rounded border border-green-200">Expense updated!</div>';
        } else {
            $message = '<div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">Error: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    }
    $exp = $conn->query("SELECT * FROM expenses WHERE id=$id")->fetch_assoc();
    $cat_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
} else {
    die('Invalid expense ID.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Expense</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="max-w-7xl mx-auto mt-5">
    <h2>Edit Expense</h2>
    <?php if ($message) echo $message; ?>
    <form method="POST">
        <div class="mb-">
            <label for="category_id" class="block text-sm font-medium mb-">Category</label>
            <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="category_id" name="category_id" required>
                <?php while($cat_row = $cat_result->fetch_assoc()): ?>
                    <option value="<?= $cat_row['id'] ?>" <?= ($exp['category_id'] == $cat_row['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat_row['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-">
            <label for="amount" class="block text-sm font-medium mb-">Amount</label>
            <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded" id="amount" name="amount" value="<?= htmlspecialchars($exp['amount']) ?>" required>
        </div>
        <div class="mb-">
            <label for="expense_date" class="block text-sm font-medium mb-">Expense Date</label>
            <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded" id="expense_date" name="expense_date" value="<?= htmlspecialchars($exp['expense_date']) ?>" required>
        </div>
        <div class="mb-">
            <label for="description" class="block text-sm font-medium mb-">Description</label>
            <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="description" name="description"><?= htmlspecialchars($exp['description']) ?></textarea>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update</button>
        <a href="view_expenses.php" class="px-4 py-2 bg-gray-600 text-white rounded">Back</a>
    </form>
</div>
</body>
</html>

