<?php
// add_expense_category_form.php
include '../../includes/auth_check.php';
include '../../includes/db_connect.php';

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $conn->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $stmt->bind_param('s', $name);
        if ($stmt->execute()) {
            $message = '<div class="p-4 bg-green-100 text-green-700 rounded border border-green-200">Category added successfully!</div>';
        } else {
            $message = '<div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">Error: ' . htmlspecialchars($stmt->error) . '</div>';
        }
        $stmt->close();
    } else {
        $message = '<div class="p-4 bg-yellow-100 text-yellow-700 rounded border border-yellow-200">Category name cannot be empty.</div>';
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-folder-plus mr-2"></i>Expense Categories</h1>
                <p class="clean-page-subtitle">Manage expense categories for accurate reporting</p>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
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
        <div class="flex flex-wrap">
            <div class="col-12">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-plus-circle mr-2"></i>Add New Category</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <form action="add_expense_category_form.php" method="POST">
                            <div class="flex flex-wrap gap-3 items-end">
                                <div class="col-md-9">
                                    <label for="name" class="clean-block text-sm font-medium mb-">
                                        <i class="fas fa-tag mr-2"></i>Category Name *
                                    </label>
                                    <input type="text" class="clean-w-full px-3 py-2 border border-gray-300 rounded" id="name" name="name" placeholder="Enter category name (e.g., Utilities, Salaries)" required>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="px-3 py-2 rounded-clean-primary w-full">
                                        <i class="fas fa-plus-circle mr-2"></i>Add Category
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Categories w-full border-collapse -->
        <div class="flex flex-wrap">
            <div class="col-12">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-list mr-2"></i>Existing Categories</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body p-0">
                        <?php if ($categories->num_rows > 0): ?>
                            <div class="clean-w-full border-collapse-scroll">
                                <table class="clean-w-full border-collapse">
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
                                                    <a href="edit_expense_category_form.php?id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-outline px-3 py-2 rounded-clean-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_expense_category.php?id=<?php echo $row['id']; ?>" class="px-3 py-2 rounded-clean-danger px-3 py-2 rounded-clean-sm" onclick="return confirm('Are you sure you want to delete this category?');" title="Delete">
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

    </body>
</html>

