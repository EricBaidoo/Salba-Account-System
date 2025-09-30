<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';
$id = intval($_GET['id'] ?? 0);
$message = '';
if ($id > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE expense_categories SET name=? WHERE id=?");
            $stmt->bind_param('si', $name, $id);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Category updated!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-warning">Name cannot be empty.</div>';
        }
    }
    $cat = $conn->query("SELECT * FROM expense_categories WHERE id=$id")->fetch_assoc();
} else {
    die('Invalid category ID.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Expense Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Expense Category</h2>
    <?php if ($message) echo $message; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="name" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="add_expense_category_form.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</body>
</html>
