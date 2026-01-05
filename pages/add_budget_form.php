<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$current_term = getCurrentTerm($conn);
$academic_year = getAcademicYear($conn);

// Fetch expense categories for budget categories
$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Budget - Salba Montessori Accounting</title>
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
                <h1 class="clean-page-title"><i class="fas fa-plus-circle me-2"></i>Create New Budget</h1>
                <p class="clean-page-subtitle">Set up a new budget for <?php echo htmlspecialchars($current_term); ?> (<?php echo htmlspecialchars($academic_year); ?>)</p>
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
                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($current_term); ?>">
                            <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>">
                            
                            <!-- Category Selection -->
                            <div class="mb-3">
                                <label for="category" class="form-label required">Budget Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">-- Select Category --</option>
                                    <?php while ($cat = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted">Select from existing expense categories or create a custom one</small>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="e.g., Staff salaries for this term"></textarea>
                                <small class="text-muted">Optional: Provide additional context for this budget</small>
                            </div>

                            <!-- Budgeted Amount -->
                            <div class="mb-3">
                                <label for="amount" class="form-label required">Budgeted Amount (GH₵)</label>
                                <div class="input-group">
                                    <span class="input-group-text">GH₵</span>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="0.00" required>
                                </div>
                                <small class="text-muted">Enter the total budget amount for this category</small>
                            </div>

                            <!-- Budget Period -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label required">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label required">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>

                            <!-- Alert Threshold -->
                            <div class="mb-3">
                                <label for="alert_threshold" class="form-label">Alert Threshold (%)</label>
                                <div class="input-group">
                                    <input type="number" step="1" class="form-control" id="alert_threshold" name="alert_threshold" value="80" min="0" max="100">
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Alert when spending reaches this percentage of the budget (default: 80%)</small>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn-clean-primary flex-grow-1">
                                    <i class="fas fa-save me-2"></i>Create Budget
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
