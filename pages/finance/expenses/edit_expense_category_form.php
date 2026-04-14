<?php
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';
$id = intval($_GET['id'] ?? 0);
$message = '';
if ($id > 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE expense_categories SET name=? WHERE id=?");
            $stmt->bind_param('si', $name, $id);
            if ($stmt->execute()) {
                $message = '<div class="p-4 bg-green-100 text-green-700 rounded border border-green-200">Category updated!</div>';
            } else {
                $message = '<div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">Error: ' . htmlspecialchars($stmt->error) . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="p-4 bg-yellow-100 text-yellow-700 rounded border border-yellow-200">Name cannot be empty.</div>';
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="max-w-7xl mx-auto mt-5">
    <h2>Edit Expense Category</h2>
    <?php if ($message) echo $message; ?>
    <form method="POST">
        <div class="mb-">
            <label for="name" class="block text-sm font-medium mb-">Category Name</label>
            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" id="name" name="name" value="<?= htmlspecialchars($cat['name']) ?>" required>
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Update</button>
        <a href="add_expense_category_form.php" class="px-4 py-2 bg-gray-600 text-white rounded">Back</a>
    </form>
</div>
</body>
</html>

