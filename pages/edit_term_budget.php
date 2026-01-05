<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get term from URL or use current
$current_term = $_GET['term'] ?? getCurrentTerm($conn);
$academic_year = $_GET['academic_year'] ?? getAcademicYear($conn);

// Get existing budget if any
$existing = $conn->query("SELECT * FROM term_budgets WHERE term = '$current_term' AND academic_year = '$academic_year'")->fetch_assoc();

// Check if budget is locked
if ($existing && isset($existing['status']) && $existing['status'] === 'locked') {
    header("Location: term_budget.php?term=" . urlencode($current_term) . "&academic_year=" . urlencode($academic_year) . "&error=locked");
    exit;
}

// Get all fees for income breakdown
$fees = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");

// Get all expense categories
$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");

// Get previous term for auto-population
$terms = getAvailableTerms();
$current_term_index = array_search($current_term, $terms);
$previous_term = null;
$previous_academic_year = $academic_year;

if ($current_term_index > 0) {
    // Previous term in same academic year
    $previous_term = $terms[$current_term_index - 1];
} elseif ($current_term_index === 0) {
    // Previous term is last term of previous academic year
    $previous_term = $terms[count($terms) - 1];
    $year_parts = explode('/', $academic_year);
    $previous_academic_year = ($year_parts[0] - 1) . '/' . ($year_parts[1] - 1);
}

// If editing, get existing items
$income_items = [];
$expense_items = [];
if ($existing) {
    $result = $conn->query("SELECT * FROM term_budget_items WHERE term_budget_id = {$existing['id']} AND type = 'income' ORDER BY category");
    while ($row = $result->fetch_assoc()) {
        $income_items[] = $row;
    }
    
    $result = $conn->query("SELECT * FROM term_budget_items WHERE term_budget_id = {$existing['id']} AND type = 'expense' ORDER BY category");
    while ($row = $result->fetch_assoc()) {
        $expense_items[] = $row;
    }
} else {
    // Pre-populate from previous term's actual spending
    if ($previous_term) {
        require_once '../includes/budget_functions.php';
        $categories->data_seek(0);
        while ($cat = $categories->fetch_assoc()) {
            $prev_spending = getTermCategorySpending($conn, $cat['name'], $previous_term, $previous_academic_year);
            if ($prev_spending > 0) {
                $expense_items[] = [
                    'category' => $cat['name'],
                    'amount' => $prev_spending
                ];
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $existing ? 'Edit' : 'Set Up'; ?> Term Budget - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .budget-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 2fr 1fr 50px;
            gap: 10px;
            align-items: end;
        }
        .budget-item input {
            font-size: 0.95rem;
        }
        .budget-item .remove-btn {
            text-align: center;
        }
    </style>
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div>
                <h1 class="clean-page-title"><i class="fas fa-calculator me-2"></i><?php echo $existing ? 'Edit' : 'Set Up'; ?> Term Budget</h1>
                <p class="clean-page-subtitle"><?php echo htmlspecialchars($current_term); ?> - <?php echo htmlspecialchars($academic_year); ?></p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-file-alt me-2"></i>Budget Setup</h5>
                    </div>
                    <div class="clean-card-body">
                        <form id="budgetForm" action="process_term_budget.php" method="POST">
                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($current_term); ?>">
                            <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>">

                            <!-- Income by Fee Category -->
                            <div class="mb-4">
                                <label class="form-label" style="font-size: 1.1rem; font-weight: bold;">
                                    <i class="fas fa-arrow-down me-2 text-success"></i>Expected Income by Fee Category
                                </label>
                                <p class="text-muted small mb-3">Enter expected income for each fee type</p>

                                <div id="incomeItemsContainer">
                                    <?php 
                                    $fees->data_seek(0);
                                    while ($fee = $fees->fetch_assoc()): 
                                        // Calculate total assigned fees for this fee type and term (active students only)
                                        $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                                                          FROM student_fees sf 
                                                          INNER JOIN students s ON sf.student_id = s.id
                                                          WHERE sf.fee_id = {$fee['id']} 
                                                          AND sf.term = '$current_term' 
                                                          AND sf.academic_year = '$academic_year'
                                                          AND s.status = 'active'";
                                        $assigned_result = $conn->query($assigned_query);
                                        $assigned_total = (float)$assigned_result->fetch_assoc()['total'];
                                        
                                        // Use assigned total as default, or existing budget if already set
                                        $fee_amount = $assigned_total;
                                        foreach ($income_items as $item) {
                                            if ($item['category'] === $fee['name']) {
                                                $fee_amount = $item['amount'];
                                                break;
                                            }
                                        }
                                    ?>
                                    <div class="budget-item">
                                        <div>
                                            <label class="small text-muted d-block mb-1">Fee Category</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($fee['name']); ?>" readonly>
                                            <small class="text-muted">
                                                Current Assignments: GH₵<?php echo number_format($assigned_total, 2); ?>
                                                <?php if ($fee_amount != $assigned_total): ?>
                                                    <span class="text-warning ms-1">⚠ Budget differs from current assignments</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <label class="small text-muted d-block mb-1">Budgeted Amount (GH₵)</label>
                                            <input type="number" step="0.01" class="form-control income-amount" name="income_amount[]" 
                                                   value="<?php echo $fee_amount; ?>" placeholder="0.00">
                                            <input type="hidden" name="income_category[]" value="<?php echo htmlspecialchars($fee['name']); ?>">
                                        </div>
                                        <div class="remove-btn"></div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Expense Categories -->
                            <div class="mb-4">
                                <label class="form-label" style="font-size: 1.1rem; font-weight: bold;">
                                    <i class="fas fa-arrow-up me-2 text-danger"></i>Expense Budget by Category
                                </label>
                                <p class="text-muted small mb-3">Budgets pre-filled from previous term's spending</p>

                                <div id="budgetItemsContainer">
                                    <?php 
                                    // Show ALL expense categories
                                    $categories->data_seek(0);
                                    while ($cat = $categories->fetch_assoc()): 
                                        // Find existing amount or use previous term's spending
                                        $cat_amount = 0;
                                        $found = false;
                                        
                                        foreach ($expense_items as $item) {
                                            if ($item['category'] === $cat['name']) {
                                                $cat_amount = $item['amount'];
                                                $found = true;
                                                break;
                                            }
                                        }
                                        
                                        // If not found in existing items and we have previous term, get previous spending
                                        if (!$found && $previous_term) {
                                            require_once '../includes/budget_functions.php';
                                            $cat_amount = getTermCategorySpending($conn, $cat['name'], $previous_term, $previous_academic_year);
                                        }
                                    ?>
                                    <div class="budget-item">
                                        <div>
                                            <label class="small text-muted d-block mb-1">Category</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($cat['name']); ?>" readonly>
                                            <?php if (!$existing && $previous_term && $cat_amount > 0): ?>
                                            <small class="text-muted">Previous term spent: GH₵<?php echo number_format($cat_amount, 2); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <label class="small text-muted d-block mb-1">Budgeted Amount (GH₵)</label>
                                            <input type="number" step="0.01" class="form-control item-amount" name="amount[]" 
                                                   value="<?php echo $cat_amount; ?>" placeholder="0.00">
                                            <input type="hidden" name="category[]" value="<?php echo htmlspecialchars($cat['name']); ?>">
                                        </div>
                                        <div class="remove-btn"></div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="alert alert-light border">
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Note:</strong> The totals below show the <strong>budgeted amounts</strong> you can edit. 
                                    The view page shows <strong>current fee assignments</strong> which may differ if fees have been assigned/unassigned after budget creation.
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-success">Total Budgeted Income</h6>
                                        <h4 id="totalIncome" class="text-success">GH₵0.00</h4>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-danger">Total Budgeted Expenses</h6>
                                        <h4 id="totalExpenses" class="text-danger">GH₵0.00</h4>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <h6>Surplus / (Deficit)</h6>
                                    <h4 id="balance" class="status-good">GH₵0.00</h4>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn-clean-primary flex-grow-1">
                                    <i class="fas fa-save me-2"></i><?php echo $existing ? 'Update Budget' : 'Create Budget'; ?>
                                </button>
                                <a href="term_budget.php" class="btn-clean-outline flex-grow-1">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Template for new category items -->
    <template id="categoryTemplate">
        <div class="budget-item">
            <div>
                <label class="small text-muted d-block mb-1">Category</label>
                <select class="form-control category-select" name="category[]" required>
                    <option value="">-- Select Category --</option>
                    <?php 
                    $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): 
                    ?>
                        <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="small text-muted d-block mb-1">Amount (GH₵)</label>
                <input type="number" step="0.01" class="form-control item-amount" name="amount[]" placeholder="0.00" required>
            </div>
            <div class="remove-btn">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTotals() {
            const incomeAmounts = Array.from(document.querySelectorAll('.income-amount')).map(el => parseFloat(el.value) || 0);
            const income = incomeAmounts.reduce((a, b) => a + b, 0);
            const amounts = Array.from(document.querySelectorAll('.item-amount')).map(el => parseFloat(el.value) || 0);
            const expenses = amounts.reduce((a, b) => a + b, 0);
            const balance = income - expenses;

            document.getElementById('totalIncome').textContent = 'GH₵' + income.toFixed(2);
            document.getElementById('totalExpenses').textContent = 'GH₵' + expenses.toFixed(2);
            
            const balanceEl = document.getElementById('balance');
            balanceEl.textContent = 'GH₵' + balance.toFixed(2);
            balanceEl.className = balance >= 0 ? 'status-good' : 'status-alert';
        }

        function addCategory() {
            const template = document.getElementById('categoryTemplate');
            const clone = template.content.cloneNode(true);
            document.getElementById('budgetItemsContainer').appendChild(clone);
            
            // Add change listeners to the new inputs
            const newAmountInputs = document.querySelectorAll('.item-amount');
            newAmountInputs.forEach(input => input.addEventListener('change', updateTotals));
        }

        function removeItem(btn) {
            btn.closest('.budget-item').remove();
            updateTotals();
        }

        // Initialize
        document.querySelectorAll('.income-amount').forEach(input => input.addEventListener('change', updateTotals));
        document.querySelectorAll('.item-amount').forEach(input => input.addEventListener('change', updateTotals));
        
        // Initial calculation
        updateTotals();
    </script>

</body>
</html>
