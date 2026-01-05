<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/system_settings.php';
include '../includes/term_helpers.php';
include '../includes/budget_functions.php';

// Get system defaults
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
$default_term = getCurrentTerm($conn);

// Get filter parameters
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$selected_term = $_GET['term'] ?? 'All';
$report_type = $_GET['report_type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Build academic year options
$year_options = [];
$yrs1 = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs1) {
    while ($yr = $yrs1->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
}
$yrs2 = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs2) {
    while ($yr = $yrs2->fetch_assoc()) {
        if (!empty($yr['academic_year']) && !in_array($yr['academic_year'], $year_options, true)) {
            $year_options[] = $yr['academic_year'];
        }
    }
}
if (!in_array($default_academic_year, $year_options, true)) {
    array_unshift($year_options, $default_academic_year);
}

// Get available terms
$available_terms = getAvailableTerms();

// Determine date range based on filters
if ($date_from && $date_to) {
    $start_date = $date_from;
    $end_date = $date_to;
} elseif ($selected_term !== 'All') {
    $range = getTermDateRange($conn, $selected_term, $selected_academic_year);
    $start_date = $range['start'];
    $end_date = $range['end'];
} else {
    // Full academic year
    $parts = explode('/', $selected_academic_year);
    $startYear = intval($parts[0]);
    $start_month = getSystemSetting($conn, 'academic_year_start_month', '09');
    $start_day = getSystemSetting($conn, 'academic_year_start_day', '01');
    $start_date = sprintf('%04d-%02d-%02d', $startYear, (int)$start_month, (int)$start_day);
    $end_date = date('Y-m-d', strtotime($start_date . ' +1 year -1 day'));
}

// OVERVIEW REPORT DATA
$total_income = 0;
$total_expenses = 0;
$income_by_category = [];
$expense_by_category = [];

if ($report_type === 'overview' || $report_type === 'income' || $report_type === 'expenses') {
    // Income by category
    $income_stmt = $conn->prepare("
        SELECT f.name AS category,
               COALESCE(SUM(p.amount), 0) AS total
        FROM fees f
        LEFT JOIN payments p ON p.fee_id = f.id AND p.payment_date BETWEEN ? AND ?
        GROUP BY f.id, f.name
        ORDER BY f.name
    ");
    $income_stmt->bind_param('ss', $start_date, $end_date);
    $income_stmt->execute();
    $income_result = $income_stmt->get_result();
    while ($row = $income_result->fetch_assoc()) {
        $income_by_category[] = $row;
        $total_income += $row['total'];
    }
    $income_stmt->close();

    // Expenses by category
    $expense_stmt = $conn->prepare("
        SELECT ec.name AS category, COALESCE(SUM(e.amount), 0) AS total
        FROM expense_categories ec
        LEFT JOIN expenses e ON e.category_id = ec.id AND e.expense_date BETWEEN ? AND ?
        GROUP BY ec.id, ec.name
        ORDER BY ec.name
    ");
    $expense_stmt->bind_param('ss', $start_date, $end_date);
    $expense_stmt->execute();
    $expense_result = $expense_stmt->get_result();
    while ($row = $expense_result->fetch_assoc()) {
        $expense_by_category[] = $row;
        $total_expenses += $row['total'];
    }
    $expense_stmt->close();
}

// BUDGET VS ACTUAL REPORT DATA
$budget_comparison = [];
if ($report_type === 'budget' || $report_type === 'overview') {
    $terms_to_check = $selected_term === 'All' ? $available_terms : [$selected_term];
    
    foreach ($terms_to_check as $term) {
        $term_budget = $conn->query("SELECT * FROM term_budgets WHERE term = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc();
        
        if ($term_budget) {
            $term_range = getTermDateRange($conn, $term, $selected_academic_year);
            
            // Get budgeted income (sum of all fee assignments)
            $income_budget_stmt = $conn->prepare("
                SELECT COALESCE(SUM(sf.amount), 0) as total 
                FROM student_fees sf 
                WHERE sf.term = ? AND sf.academic_year = ?
            ");
            $income_budget_stmt->bind_param('ss', $term, $selected_academic_year);
            $income_budget_stmt->execute();
            $income_budget = (float)$income_budget_stmt->get_result()->fetch_assoc()['total'];
            $income_budget_stmt->close();
            
            // Get actual income
            $income_actual_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
            $income_actual_stmt->bind_param('ss', $term_range['start'], $term_range['end']);
            $income_actual_stmt->execute();
            $income_actual = (float)$income_actual_stmt->get_result()->fetch_assoc()['total'];
            $income_actual_stmt->close();
            
            // Get budgeted expenses
            $expenses_budget = (float)$conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM term_budget_items WHERE term_budget_id = {$term_budget['id']} AND type = 'expense'")->fetch_assoc()['total'];
            
            // Get actual expenses
            $expenses_actual_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
            $expenses_actual_stmt->bind_param('ss', $term_range['start'], $term_range['end']);
            $expenses_actual_stmt->execute();
            $expenses_actual = (float)$expenses_actual_stmt->get_result()->fetch_assoc()['total'];
            $expenses_actual_stmt->close();
            
            $budget_comparison[$term] = [
                'income_budget' => $income_budget,
                'income_actual' => $income_actual,
                'income_variance' => $income_actual - $income_budget,
                'income_variance_pct' => $income_budget > 0 ? (($income_actual - $income_budget) / $income_budget * 100) : 0,
                'expenses_budget' => $expenses_budget,
                'expenses_actual' => $expenses_actual,
                'expenses_variance' => $expenses_budget - $expenses_actual,
                'expenses_variance_pct' => $expenses_budget > 0 ? (($expenses_budget - $expenses_actual) / $expenses_budget * 100) : 0,
                'net_budget' => $income_budget - $expenses_budget,
                'net_actual' => $income_actual - $expenses_actual
            ];
        }
    }
}

// STUDENT FEE COLLECTION REPORT
$fee_collection_summary = [];
if ($report_type === 'student_fees') {
    $collection_stmt = $conn->prepare("
        SELECT 
            s.id,
            s.name AS student_name,
            s.class,
            COALESCE(SUM(sf.amount), 0) AS total_assigned,
            COALESCE(SUM(sf.amount_paid), 0) AS total_paid,
            COALESCE(SUM(sf.amount - sf.amount_paid), 0) AS balance
        FROM students s
        LEFT JOIN student_fees sf ON s.id = sf.student_id 
            AND sf.academic_year = ? 
            " . ($selected_term !== 'All' ? "AND sf.term = ?" : "") . "
        WHERE s.status = 'active'
        GROUP BY s.id, s.name, s.class
        HAVING total_assigned > 0
        ORDER BY s.class, s.name
    ");
    
    if ($selected_term !== 'All') {
        $collection_stmt->bind_param('ss', $selected_academic_year, $selected_term);
    } else {
        $collection_stmt->bind_param('s', $selected_academic_year);
    }
    
    $collection_stmt->execute();
    $collection_result = $collection_stmt->get_result();
    while ($row = $collection_result->fetch_assoc()) {
        $fee_collection_summary[] = $row;
    }
    $collection_stmt->close();
}

// PAYMENT TRANSACTIONS REPORT
$payment_transactions = [];
if ($report_type === 'transactions') {
    $trans_stmt = $conn->prepare("
        SELECT 
            p.id,
            p.payment_date,
            s.name AS student_name,
            s.class,
            f.name AS fee_name,
            p.amount,
            p.payment_method,
            p.reference_number
        FROM payments p
        LEFT JOIN students s ON p.student_id = s.id
        LEFT JOIN fees f ON p.fee_id = f.id
        WHERE p.payment_date BETWEEN ? AND ?
        ORDER BY p.payment_date DESC, p.id DESC
        LIMIT 500
    ");
    $trans_stmt->bind_param('ss', $start_date, $end_date);
    $trans_stmt->execute();
    $trans_result = $trans_stmt->get_result();
    while ($row = $trans_result->fetch_assoc()) {
        $payment_transactions[] = $row;
    }
    $trans_stmt->close();
}

// EXPENSE TRANSACTIONS REPORT
$expense_transactions = [];
if ($report_type === 'expense_details') {
    $exp_stmt = $conn->prepare("
        SELECT 
            e.id,
            e.expense_date,
            ec.name AS category,
            e.description,
            e.amount,
            e.payment_method,
            e.reference_number
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ?
        ORDER BY e.expense_date DESC, e.id DESC
        LIMIT 500
    ");
    $exp_stmt->bind_param('ss', $start_date, $end_date);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    while ($row = $exp_result->fetch_assoc()) {
        $expense_transactions[] = $row;
    }
    $exp_stmt->close();
}

?>
$startYear = intval($parts[0]);
$endPart = isset($parts[1]) ? $parts[1] : ($startYear + 1);
if (strlen($endPart) === 2) {
    $century = substr((string)$startYear, 0, 2);
    $endYear = intval($century . $endPart);
} else {
    $endYear = intval($endPart);
}
$start_month = getSystemSetting($conn, 'academic_year_start_month', '09');
$start_day = getSystemSetting($conn, 'academic_year_start_day', '01');
$start_date = sprintf('%04d-%02d-%02d', $startYear, (int)$start_month, (int)$start_day);
$end_date = date('Y-m-d', strtotime($start_date . ' +1 year -1 day'));

$expenses_by_category = null;
$stmtExpenses = $conn->prepare('
    SELECT ec.name AS category, SUM(e.amount) AS total
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    WHERE e.expense_date BETWEEN ? AND ?
    GROUP BY ec.name
    ORDER BY ec.name
');
if ($stmtExpenses) {
    $stmtExpenses->bind_param('ss', $start_date, $end_date);
    $stmtExpenses->execute();
    $expenses_by_category = $stmtExpenses->get_result();
}
$total_expenses = 0;
$expense_rows = [];
while ($row = $expenses_by_category->fetch_assoc()) {
    $expense_rows[] = $row;
    $total_expenses += $row['total'];
}
$balance = $total_paid - $total_expenses;

// Get budget data for current year
$budget_data = [];
$current_term = getCurrentTerm($conn);
$terms = ['First Term', 'Second Term', 'Third Term'];
foreach ($terms as $term) {
    $term_budget = $conn->query("SELECT * FROM term_budgets WHERE term = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc();
    if ($term_budget) {
        $term_range = getTermDateRange($conn, $term, $selected_academic_year);
        
        // Get budgeted vs actual for this term
        $income_budget = (float)$term_budget['expected_income'];
        $income_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_date BETWEEN ? AND ?");
        $income_stmt->bind_param('ss', $term_range['start'], $term_range['end']);
        $income_stmt->execute();
        $income_actual = (float)$income_stmt->get_result()->fetch_assoc()['total'];
        $income_stmt->close();
        
        $expenses_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
        $expenses_stmt->bind_param('ss', $term_range['start'], $term_range['end']);
        $expenses_stmt->execute();
        $expenses_actual = (float)$expenses_stmt->get_result()->fetch_assoc()['total'];
        $expenses_stmt->close();
        
        // Get total budgeted expenses
        $expenses_budget = (float)$conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM term_budget_items WHERE term_budget_id = {$term_budget['id']} AND type = 'expense'")->fetch_assoc()['total'];
        
        $budget_data[$term] = [
            'income_budget' => $income_budget,
            'income_actual' => $income_actual,
            'expenses_budget' => $expenses_budget,
            'expenses_actual' => $expenses_actual
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <div>
                <h1 class="clean-page-title"><i class="fas fa-chart-bar me-2"></i>Income & Expense Report</h1>
                <p class="clean-page-subtitle">Breakdown for Academic Year <?php echo htmlspecialchars($display_academic_year); ?></p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <div class="row mb-3">
            <div class="col-md-6">
                <form method="GET" class="d-flex align-items-center gap-2">
                    <label for="academic_year" class="me-2 fw-semibold"><i class="fas fa-calendar"></i> Academic Year</label>
                    <select name="academic_year" id="academic_year" class="form-select" style="max-width: 220px;">
                        <?php foreach ($year_options as $yr): ?>
                            <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo ($yr === $selected_academic_year) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $yr)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </form>
            </div>
            <div class="col-md-6 text-end">
                <a href="term_budget.php" class="btn btn-outline-info">
                    <i class="fas fa-balance-scale me-2"></i>View Budget
                </a>
            </div>
        </div>
        <div class="row mb-4">
            <div class="col-md-6 mb-4">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title text-success"><i class="fas fa-arrow-down me-2"></i>Income by Category (<?php echo htmlspecialchars($display_academic_year); ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="clean-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Total Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($income_rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                                    <td class="text-end"><strong class="text-success">GH₵<?php echo number_format($row['total'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-success">
                                    <th>Total Paid (<?php echo htmlspecialchars($display_academic_year); ?>)</th>
                                    <th class="text-end"><strong>GH₵<?php echo number_format($total_paid, 2); ?></strong></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title text-danger"><i class="fas fa-arrow-up me-2"></i>Expenses by Category (<?php echo htmlspecialchars($display_academic_year); ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="clean-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Total Spent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expense_rows as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></td>
                                    <td class="text-end"><strong class="text-danger">GH₵<?php echo number_format($row['total'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-danger">
                                    <th>Total Expenses</th>
                                    <th class="text-end"><strong>GH₵<?php echo number_format($total_expenses, 2); ?></strong></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budget vs Actual Section -->
        <?php if (!empty($budget_data)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-chart-bar me-2"></i>Budget vs Actual by Term (<?php echo htmlspecialchars($display_academic_year); ?>)</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="clean-table">
                            <thead>
                                <tr>
                                    <th>Term</th>
                                    <th colspan="2" class="text-center">Income</th>
                                    <th colspan="2" class="text-center">Expenses</th>
                                    <th class="text-center">Net</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th class="text-center">Budgeted</th>
                                    <th class="text-center">Actual</th>
                                    <th class="text-center">Budgeted</th>
                                    <th class="text-center">Actual</th>
                                    <th class="text-center">Variance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($budget_data as $term => $data): 
                                    $net_variance = ($data['income_actual'] - $data['expenses_actual']) - ($data['income_budget'] - $data['expenses_budget']);
                                    $net_variance_class = $net_variance >= 0 ? 'text-success' : 'text-danger';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($term); ?></strong></td>
                                    <td class="text-center">GH₵<?php echo number_format($data['income_budget'], 2); ?></td>
                                    <td class="text-center <?php echo $data['income_actual'] >= $data['income_budget'] ? 'text-success' : 'text-warning'; ?>">
                                        GH₵<?php echo number_format($data['income_actual'], 2); ?>
                                    </td>
                                    <td class="text-center">GH₵<?php echo number_format($data['expenses_budget'], 2); ?></td>
                                    <td class="text-center <?php echo $data['expenses_actual'] <= $data['expenses_budget'] ? 'text-success' : 'text-danger'; ?>">
                                        GH₵<?php echo number_format($data['expenses_actual'], 2); ?>
                                    </td>
                                    <td class="text-center fw-bold <?php echo $net_variance_class; ?>">
                                        GH₵<?php echo number_format($net_variance, 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="clean-card text-center">
            <div class="p-5">
                <h4 class="mb-3"><i class="fas fa-balance-scale me-2"></i>Net Balance</h4>
                <div class="display-6 fw-bold <?php echo (($total_paid - $total_expenses) >= 0) ? 'text-success' : 'text-danger'; ?>">
                    GH₵<?php echo number_format($total_paid - $total_expenses, 2); ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>