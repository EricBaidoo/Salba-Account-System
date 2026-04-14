<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

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
        require_once '../../includes/budget_functions.php';
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            <div>
                <h1 class="clean-page-title"><i class="fas fa-calculator mr-2"></i><?php echo $existing ? 'Edit' : 'Set Up'; ?> Term Budget</h1>
                <p class="clean-page-subtitle"><?php echo htmlspecialchars($current_term); ?> - <?php echo htmlspecialchars($academic_year); ?></p>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <div class="flex flex-wrap justify-center">
            <div class="col-lg-8">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-file-alt mr-2"></i>Budget Setup</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <form id="budgetForm" action="process_term_budget.php" method="POST">
                            <input type="hidden" name="term" value="<?php echo htmlspecialchars($current_term); ?>">
                            <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>">

                            <!-- Income by Fee Category -->
                            <div class="mb-">
                                <label class="block text-sm font-medium mb-" style="font-size: 1.1rem; font-weight: bold;">
                                    <i class="fas fa-arrow-down mr-2 text-green-600"></i>Expected Income by Fee Category
                                </label>
                                <p class="text-gray-600 small mb-">Enter expected income for each fee type</p>

                                <div id="incomeItemsmax-w-7xl mx-auto">
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
                                            <label class="small text-gray-600 block mb-">Fee Category</label>
                                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo htmlspecialchars($fee['name']); ?>" readonly>
                                            <small class="text-gray-600">
                                                Current Assignments: GHâ‚µ<?php echo number_format($assigned_total, 2); ?>
                                                <?php if ($fee_amount != $assigned_total): ?>
                                                    <span class="text-yellow-600 ml-1">âš  Budget differs from current assignments</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <label class="small text-gray-600 block mb-">Budgeted Amount (GHâ‚µ)</label>
                                            <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded income-amount" name="income_amount[]" 
                                                   value="<?php echo $fee_amount; ?>" placeholder="0.00">
                                            <input type="hidden" name="income_category[]" value="<?php echo htmlspecialchars($fee['name']); ?>">
                                        </div>
                                        <div class="remove-px-3 py-2 rounded"></div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Expense Categories -->
                            <div class="mb-">
                                <label class="block text-sm font-medium mb-" style="font-size: 1.1rem; font-weight: bold;">
                                    <i class="fas fa-arrow-up mr-2 text-red-600"></i>Expense Budget by Category
                                </label>
                                <p class="text-gray-600 small mb-">Budgets pre-filled from previous term's spending</p>

                                <div id="budgetItemsmax-w-7xl mx-auto">
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
                                            require_once '../../includes/budget_functions.php';
                                            $cat_amount = getTermCategorySpending($conn, $cat['name'], $previous_term, $previous_academic_year);
                                        }
                                    ?>
                                    <div class="budget-item">
                                        <div>
                                            <label class="small text-gray-600 block mb-">Category</label>
                                            <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded" value="<?php echo htmlspecialchars($cat['name']); ?>" readonly>
                                            <?php if (!$existing && $previous_term && $cat_amount > 0): ?>
                                            <small class="text-gray-600">Previous term spent: GHâ‚µ<?php echo number_format($cat_amount, 2); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <label class="small text-gray-600 block mb-">Budgeted Amount (GHâ‚µ)</label>
                                            <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded item-amount" name="amount[]" 
                                                   value="<?php echo $cat_amount; ?>" placeholder="0.00">
                                            <input type="hidden" name="category[]" value="<?php echo htmlspecialchars($cat['name']); ?>">
                                        </div>
                                        <div class="remove-px-3 py-2 rounded"></div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="p-4 bg-gray-100 text-gray-700 rounded border border-gray-300">
                                <div class="alert alert-info mb-">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Note:</strong> The totals below show the <strong>budgeted amounts</strong> you can edit. 
                                    The view page shows <strong>current fee assignments</strong> which may differ if fees have been assigned/unassigned after budget creation.
                                </div>
                                <div class="flex flex-wrap">
                                    <div class="md:col-span-6">
                                        <h6 class="text-green-600">Total Budgeted Income</h6>
                                        <h4 id="totalIncome" class="text-green-600">GHâ‚µ0.00</h4>
                                    </div>
                                    <div class="md:col-span-6">
                                        <h6 class="text-red-600">Total Budgeted Expenses</h6>
                                        <h4 id="totalExpenses" class="text-red-600">GHâ‚µ0.00</h4>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-top">
                                    <h6>Surplus / (Deficit)</h6>
                                    <h4 id="balance" class="status-good">GHâ‚µ0.00</h4>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex gap-2 mt-4">
                                <button type="submit" class="px-3 py-2 rounded-clean-primary flex-grow-1">
                                    <i class="fas fa-save mr-2"></i><?php echo $existing ? 'Update Budget' : 'Create Budget'; ?>
                                </button>
                                <a href="term_budget.php" class="px-3 py-2 rounded-clean-outline flex-grow-1">
                                    <i class="fas fa-times mr-2"></i>Cancel
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
                <label class="small text-gray-600 block mb-">Category</label>
                <select class="w-full px-3 py-2 border border-gray-300 rounded category-select" name="category[]" required>
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
                <label class="small text-gray-600 block mb-">Amount (GHâ‚µ)</label>
                <input type="number" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded item-amount" name="amount[]" placeholder="0.00" required>
            </div>
            <div class="remove-px-3 py-2 rounded">
                <button type="button" class="px-3 py-2 rounded px-3 py-2 rounded-sm px-3 py-2 rounded-outline-danger" onclick="removeItem(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    </template>

        <script>
        function updateTotals() {
            const incomeAmounts = Array.from(document.querySelectorAll('.income-amount')).map(el => parseFloat(el.value) || 0);
            const income = incomeAmounts.reduce((a, b) => a + b, 0);
            const amounts = Array.from(document.querySelectorAll('.item-amount')).map(el => parseFloat(el.value) || 0);
            const expenses = amounts.reduce((a, b) => a + b, 0);
            const balance = income - expenses;

            document.getElementById('totalIncome').textContent = 'GHâ‚µ' + income.toFixed(2);
            document.getElementById('totalExpenses').textContent = 'GHâ‚µ' + expenses.toFixed(2);
            
            const balanceEl = document.getElementById('balance');
            balanceEl.textContent = 'GHâ‚µ' + balance.toFixed(2);
            balanceEl.className = balance >= 0 ? 'status-good' : 'status-alert';
        }

        function addCategory() {
            const template = document.getElementById('categoryTemplate');
            const clone = template.content.cloneNode(true);
            document.getElementById('budgetItemsmax-w-7xl mx-auto').appendChild(clone);
            
            // Add change listeners to the new inputs
            const newAmountInputs = document.querySelectorAll('.item-amount');
            newAmountInputs.forEach(input => input.addEventListener('change', updateTotals));
        }

        function removeItem(px-3 py-2 rounded) {
            px-3 py-2 rounded.closest('.budget-item').remove();
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

