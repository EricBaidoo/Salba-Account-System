<?php 
include '../includes/auth_check.php'; 
include '../includes/db_connect.php';
include '../includes/system_settings.php';

// Get current term and academic year
$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableTerms();

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
<body class="form-page-body">
    <div class="form-container">
        <div class="form-card">
            <div class="form-header">
                <a href="view_expenses.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Expenses
                </a>
                <div class="form-header-content">
                    <div class="form-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h1>Add New Expense</h1>
                    <p>Record and categorize your expense transactions</p>
                </div>
            </div>
            
            <form action="add_expense.php" method="POST">
                <div class="form-body">
                    <div class="clean-form-group">
                        <label for="category_id" class="clean-form-label">
                            <i class="fas fa-folder"></i>Expense Category
                            <span class="required-indicator">*</span>
                        </label>
                        <select class="clean-form-control" id="category_id" name="category_id" required>
                            <option value="" disabled selected>Select a category...</option>
                            <?php while($cat_row = $cat_result->fetch_assoc()): ?>
                                <option value="<?= $cat_row['id'] ?>"><?= htmlspecialchars($cat_row['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="clean-form-group">
                        <label for="amount" class="clean-form-label">
                            <i class="fas fa-dollar-sign"></i>Amount (GH₵)
                            <span class="required-indicator">*</span>
                        </label>
                        <input type="number" step="0.01" class="clean-form-control" id="amount" name="amount" placeholder="Enter amount (e.g., 150.00)" required>
                    </div>
                    
                    <div class="clean-form-group">
                        <label for="expense_date" class="clean-form-label">
                            <i class="fas fa-calendar"></i>Expense Date
                            <span class="required-indicator">*</span>
                        </label>
                        <input type="date" class="clean-form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="clean-form-group">
                        <label for="term" class="clean-form-label">
                            <i class="fas fa-calendar-alt"></i>Term
                            <span class="required-indicator">*</span>
                        </label>
                        <select class="clean-form-control" id="term" name="term" required>
                            <?php foreach ($available_terms as $term): ?>
                                <option value="<?php echo htmlspecialchars($term); ?>" <?php echo $term === $current_term ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term); ?><?php echo $term === $current_term ? ' (Current)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="clean-form-group">
                        <label for="academic_year" class="clean-form-label">
                            <i class="fas fa-graduation-cap"></i>Academic Year
                            <span class="required-indicator">*</span>
                        </label>
                        <input type="text" class="clean-form-control" id="academic_year" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>" readonly>
                        <small class="text-muted">Current: <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)); ?></small>
                    </div>
                    
                    <div class="clean-form-group mb-0">
                        <label for="description" class="clean-form-label">
                            <i class="fas fa-file-alt"></i>Description
                        </label>
                        <textarea class="clean-form-control" id="description" name="description" rows="3" placeholder="Enter additional details about this expense (optional)"></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="view_expenses.php" class="btn-clean-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn-clean-primary">
                        <i class="fas fa-plus-circle"></i> Add Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>