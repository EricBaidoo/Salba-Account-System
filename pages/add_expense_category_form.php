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
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-folder-plus me-2"></i>Expense Categories</h1>
                <p class="clean-page-subtitle">Manage expense categories for accurate reporting</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <?php if (strpos($message, 'success') !== false): ?>
                <div class="clean-alert clean-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span>Category added successfully!</span>
                </div>
            <?php elseif (strpos($message, 'danger') !== false): ?>
                <div class="clean-alert clean-alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?php echo strip_tags($message); ?></span>
                </div>
            <?php else: ?>
                <div class="clean-alert clean-alert-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Category name cannot be empty.</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Add Category Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-plus-circle me-2"></i>Add New Category</h5>
                    </div>
                    <div class="clean-card-body">
                        <form action="add_expense_category_form.php" method="POST">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-9">
                                    <label for="name" class="clean-form-label">
                                        <i class="fas fa-tag me-2"></i>Category Name *
                                    </label>
                                    <input type="text" class="clean-form-control" id="name" name="name" placeholder="Enter category name (e.g., Utilities, Salaries)" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn-clean-primary w-100">
                                        <i class="fas fa-plus-circle me-2"></i>Add Category
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Categories Table -->
        <div class="row">
            <div class="col-12">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-list me-2"></i>Existing Categories</h5>
                    </div>
                    <div class="clean-card-body p-0">
                        <?php if ($categories->num_rows > 0): ?>
                            <div class="clean-table-scroll">
                                <table class="clean-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Category Name</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $categories->data_seek(0);
                                        $i = 1; 
                                        while($row = $categories->fetch_assoc()): 
                                        ?>
                                        <tr>
                                            <td><span class="clean-badge clean-badge-primary">#<?php echo $i++; ?></span></td>
                                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                            <td>
                                                <div class="clean-actions">
                                                    <a href="edit_expense_category_form.php?id=<?php echo $row['id']; ?>" class="btn-clean-outline btn-clean-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_expense_category.php?id=<?php echo $row['id']; ?>" class="btn-clean-danger btn-clean-sm" onclick="return confirm('Are you sure you want to delete this category?');" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="clean-empty-state">
                                <div class="clean-empty-icon"><i class="fas fa-folder-open"></i></div>
                                <h4 class="clean-empty-title">No Categories Yet</h4>
                                <p class="clean-empty-text">Add your first expense category above to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
