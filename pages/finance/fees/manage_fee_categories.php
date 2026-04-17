<?php
// Admin page to manage fee categories
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        if ($name) {
            $stmt = $conn->prepare("INSERT INTO fee_categories (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $name, $desc);
            $stmt->execute();
            $stmt->close();
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        if ($id && $name) {
            $stmt = $conn->prepare("UPDATE fee_categories SET name=?, description=? WHERE id=?");
            $stmt->bind_param('ssi', $name, $desc, $id);
            $stmt->execute();
            $stmt->close();
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = intval($_POST['id']);
        if ($id) {
            $stmt = $conn->prepare("DELETE FROM fee_categories WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: manage_fee_categories.php');
    exit;
}

// Fetch all categories
$categories = $conn->query("SELECT * FROM fee_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Fee Categories</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
        <link rel="stylesheet" href="../../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="max-w-7xl mx-auto py-5">
    <h2 class="mb-">Manage Fee Categories</h2>
    <form method="post" class="mb- p-3 bg-light rounded-3 border">
        <div class="flex flex-wrap gap-2 items-end">
            <div class="col-md-5">
                <label class="block text-sm font-medium mb- fw-bold">Category Name</label>
                <input type="text" name="name" class="w-full px-3 py-2 border border-gray-300 rounded" required>
            </div>
            <div class="col-md-5">
                <label class="block text-sm font-medium mb- fw-bold">Description</label>
                <input type="text" name="description" class="w-full px-3 py-2 border border-gray-300 rounded">
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_category" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 w-full">Add</button>
            </div>
        </div>
    </form>
    <table class="w-full border-collapse w-full border-collapse-bordered w-full border-collapse-striped">
        <thead class="w-full border-collapse-primary">
            <tr><th>Name</th><th>Description</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php while($cat = $categories->fetch_assoc()): ?>
            <tr>
                <form method="post">
                    <td>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded" required>
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                    </td>
                    <td><input type="text" name="description" value="<?php echo htmlspecialchars($cat['description']); ?>" class="w-full px-3 py-2 border border-gray-300 rounded"></td>
                    <td>
                        <button type="submit" name="edit_category" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 px-3 py-2 rounded-sm">Save</button>
                        <button type="submit" name="delete_category" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 px-3 py-2 rounded-sm" onclick="return confirm('Delete this category?');">Delete</button>
                    </td>
                </form>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>

