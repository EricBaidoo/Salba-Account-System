<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';
include '../../includes/budget_functions.php';

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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="budgets.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Budgets
                </a>
            </div>
            <div>
                <h1 class="clean-page-title"><i class="fas fa-edit mr-2"></i>Edit Budget</h1>
                <p class="clean-page-subtitle">Update budget details for <?php echo htmlspecialchars($budget['category']); ?> - <?php echo htmlspecialchars($budget['semester']); ?></p>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <div class="flex flex-wrap justify-center">
            <div class="lg:col-span-6">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-file-alt mr-2"></i>Budget Details</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <form action="process_budget.php" method="POST" onsubmit="return validateForm()">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($budget['id']); ?>">
                            
                            <!-- Category Selection -->
                            <div class="mb-">
                                <label for="category" class="block text-sm font-medium mb- required">Budget Category</label>
                                <select class="w-full px-3 py-2 border border-gray-300 rounded" id="category" name="category" required>
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
                            <div class="mb-">
                                <label for="description" class="block text-sm font-medium mb-">Description</label>
                                <textarea class="w-full px-3 py-2 border border-gray-300 rounded" id="description" name="description" rows="3"><?php echo htmlspecialchars($budget['description'] ?? ''); ?></textarea>
                            </div>

                            <!-- Budgeted Amount -->
                            <div class="mb-">
                                <label for="amount" class="block text-sm font-medium mb- required">Budgeted Amount (GHâ‚µ)</label>
                                <div class="input-group">
                                    <span class="input-group-text">GHâ‚µ</span>
                                    <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded" id="amount" name="amount" 
                                           value="<?php echo htmlspecialchars($budget['amount']); ?>" required>
                                </div>
                            </div>

                            <!-- Budget Period -->
                            <div class="flex flex-wrap">
                                <div class="md:col-span-6 mb-">
                                    <label for="start_date" class="block text-sm font-medium mb- required">Start Date</label>
                                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded" id="start_date" name="start_date" 
                                           value="<?php echo htmlspecialchars($budget['start_date']); ?>" required>
                                </div>
                                <div class="md:col-span-6 mb-">
                                    <label for="end_date" class="block text-sm font-medium mb- required">End Date</label>
                                    <input type="date" class="w-full px-3 py-2 border border-gray-300 rounded" id="end_date" name="end_date" 
                                           value="<?php echo htmlspecialchars($budget['end_date']); ?>" required>
                                </div>
                            </div>

                            <!-- Alert Threshold -->
                            <div class="mb-">
                                <label for="alert_threshold" class="block text-sm font-medium mb-">Alert Threshold (%)</label>
                                <div class="input-group">
                                    <input type="number" step="1" class="w-full px-3 py-2 border border-gray-300 rounded" id="alert_threshold" name="alert_threshold" 
                                           value="<?php echo htmlspecialchars($budget['alert_threshold'] ?? 80); ?>" min="0" max="100">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="px-3 py-2 rounded-clean-primary flex-grow-1">
                                    <i class="fas fa-save mr-2"></i>Update Budget
                                </button>
                                <a href="budgets.php" class="px-3 py-2 rounded-clean-outline flex-grow-1">
                                    <i class="fas fa-times mr-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

