<?php
// add_expense_category_form.php
include '../includes/auth_check.php';
include '../includes/db_connect.php';

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Category added successfully!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-warning">Category name cannot be empty.</div>';
    }
}
// Fetch all categories
$categories = $conn->query("SELECT * FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense Category - Salba Montessori Accounting</title>
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
            <h2 class="mb-0"><i class="fas fa-folder-plus me-2"></i>Add Expense Category</h2>
            <p class="lead mb-0">Manage expense categories for accurate reporting.</p>
        </div>
        <?php if ($message) echo $message; ?>
        <div class="main-content p-4 mb-4">
            <form action="add_expense_category_form.php" method="POST" class="row g-3 justify-content-center">
                <div class="col-md-6">
                    <label for="name" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus-circle me-1"></i>Add Category</button>
                </div>
            </form>
        </div>
        <div class="main-content p-4">
            <h4 class="mb-3"><i class="fas fa-list me-2"></i>Existing Categories</h4>
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; while($row = $categories->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td>
                            <a href="edit_expense_category_form.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="delete_expense_category.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
