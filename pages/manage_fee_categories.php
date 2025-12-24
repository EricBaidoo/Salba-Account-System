<?php
// Admin page to manage fee categories
include '../includes/auth_check.php';
include '../includes/db_connect.php';

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container py-5">
    <h2 class="mb-4">Manage Fee Categories</h2>
    <form method="post" class="mb-4 p-3 bg-light rounded-3 border">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-bold">Category Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-5">
                <label class="form-label fw-bold">Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="col-md-2">
                <button type="submit" name="add_category" class="btn btn-success w-100">Add</button>
            </div>
        </div>
    </form>
    <table class="table table-bordered table-striped">
        <thead class="table-primary">
            <tr><th>Name</th><th>Description</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php while($cat = $categories->fetch_assoc()): ?>
            <tr>
                <form method="post">
                    <td>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($cat['name']); ?>" class="form-control" required>
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                    </td>
                    <td><input type="text" name="description" value="<?php echo htmlspecialchars($cat['description']); ?>" class="form-control"></td>
                    <td>
                        <button type="submit" name="edit_category" class="btn btn-primary btn-sm">Save</button>
                        <button type="submit" name="delete_category" class="btn btn-danger btn-sm" onclick="return confirm('Delete this category?');">Delete</button>
                    </td>
                </form>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
