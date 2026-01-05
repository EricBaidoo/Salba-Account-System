<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
include '../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: budgets.php');
    exit;
}

$budget = getBudgetById($conn, $id);
if (!$budget) {
    header('Location: budgets.php');
    exit;
}

// Fetch expense categories
$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Budget - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="budgets.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Budgets
                </a>
            </div>
            <div>
                <h1 class="clean-page-title"><i class="fas fa-edit me-2"></i>Edit Budget</h1>
                <p class="clean-page-subtitle">Update budget details for <?php echo htmlspecialchars($budget['category']); ?> - <?php echo htmlspecialchars($budget['term']); ?></p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-file-alt me-2"></i>Budget Details</h5>
                    </div>
                    <div class="clean-card-body">
                        <form action="process_budget.php" method="POST" onsubmit="return validateForm()">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($budget['id']); ?>">
                            
                            <!-- Category Selection -->
                            <div class="mb-3">
                                <label for="category" class="form-label required">Budget Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <?php 
                                    $categories->data_seek(0);
                                    while ($cat = $categories->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                            <?php echo $cat['name'] === $budget['category'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($budget['description'] ?? ''); ?></textarea>
                            </div>

                            <!-- Budgeted Amount -->
                            <div class="mb-3">
                                <label for="amount" class="form-label required">Budgeted Amount (GH₵)</label>
                                <div class="input-group">
                                    <span class="input-group-text">GH₵</span>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($budget['amount']); ?>" required>
                                </div>
                            </div>

                            <!-- Budget Period -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label required">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($budget['start_date']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label required">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($budget['end_date']); ?>" required>
                                </div>
                            </div>

                            <!-- Alert Threshold -->
                            <div class="mb-3">
                                <label for="alert_threshold" class="form-label">Alert Threshold (%)</label>
                                <div class="input-group">
                                    <input type="number" step="1" class="form-control" id="alert_threshold" name="alert_threshold" 
                                           value="<?php echo htmlspecialchars($budget['alert_threshold'] ?? 80); ?>" min="0" max="100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn-clean-primary flex-grow-1">
                                    <i class="fas fa-save me-2"></i>Update Budget
                                </button>
                                <a href="budgets.php" class="btn-clean-outline flex-grow-1">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateForm() {
            const amount = parseFloat(document.getElementById('amount').value);
            const threshold = parseInt(document.getElementById('alert_threshold').value);

            if (amount <= 0) {
                alert('Budgeted amount must be greater than 0');
                return false;
            }

            if (threshold < 0 || threshold > 100) {
                alert('Alert threshold must be between 0 and 100');
                return false;
            }

            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (startDate >= endDate) {
                alert('End date must be after start date');
                return false;
            }

            return true;
        }
    </script>

</body>
</html>
