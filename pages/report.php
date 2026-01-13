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

// DEBUG: Show what date range is being used
$debug_info = "<!-- DEBUG: Term='$selected_term', Year='$selected_academic_year', Start='$start_date', End='$end_date' -->";
echo $debug_info;

// Check actual data in database
$payment_check = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM payments WHERE payment_date BETWEEN '$start_date' AND '$end_date'");
$payment_data = $payment_check->fetch_assoc();
echo "<!-- Payments by DATE RANGE: Count={$payment_data['cnt']}, Total={$payment_data['total']} -->";

// Also check student_fees amount_paid
$term_filter_debug = ($selected_term !== 'All') ? "AND term = '" . $conn->real_escape_string($selected_term) . "'" : "";
$sf_check = $conn->query("SELECT COUNT(*) as cnt, SUM(amount_paid) as total FROM student_fees WHERE academic_year = '$selected_academic_year' $term_filter_debug AND amount_paid > 0");
$sf_data = $sf_check->fetch_assoc();
echo "<!-- Student_fees amount_paid for $selected_term: Count={$sf_data['cnt']}, Total={$sf_data['total']} -->";

// Check if there are payments OUTSIDE the term date range
$outside_check = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM payments WHERE payment_date NOT BETWEEN '$start_date' AND '$end_date' AND payment_date >= DATE_SUB('$start_date', INTERVAL 30 DAY) AND payment_date <= DATE_ADD('$end_date', INTERVAL 30 DAY)");
$outside_data = $outside_check->fetch_assoc();
echo "<!-- Payments NEAR term (±30 days): Count={$outside_data['cnt']}, Total={$outside_data['total']} -->";

$expense_check = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '$start_date' AND '$end_date'");
$expense_data = $expense_check->fetch_assoc();
echo "<!-- Expenses in range: Count={$expense_data['cnt']}, Total={$expense_data['total']} -->";

// OVERVIEW REPORT DATA
$total_income = 0;
$total_expenses = 0;
$income_by_category = [];
$expense_by_category = [];

if ($report_type === 'overview' || $report_type === 'income' || $report_type === 'expenses') {
    // Income from payments table - filtered by term and academic year like view_payments.php
    $payment_where = [];
    if ($selected_term !== 'All') {
        $payment_where[] = "p.term = '" . $conn->real_escape_string($selected_term) . "'";
    }
    $payment_where[] = "p.academic_year = '" . $conn->real_escape_string($selected_academic_year) . "'";
    $payment_where_sql = ' WHERE ' . implode(' AND ', $payment_where);
    
    // Total income from all payments (active students only) + general payments
    $income_total_result = $conn->query("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        LEFT JOIN students s ON p.student_id = s.id
        $payment_where_sql 
        AND (s.status = 'active' OR p.payment_type = 'general')
    ");
    $total_income = (float)$income_total_result->fetch_assoc()['total'];
    
    // Income breakdown by fee category - from student_fees.amount_paid + general payments linked to fee categories
    $term_filter = ($selected_term !== 'All') ? "AND sf.term = '" . $conn->real_escape_string($selected_term) . "'" : "";
    
    $income_sql = "
        SELECT f.name AS category,
               SUM(COALESCE(sf.amount_paid, 0) + COALESCE(gp.general_amount, 0)) AS total
        FROM fees f
        LEFT JOIN student_fees sf ON sf.fee_id = f.id
            AND sf.academic_year = '" . $conn->real_escape_string($selected_academic_year) . "'
            $term_filter
            AND sf.amount_paid > 0
        LEFT JOIN (
            SELECT fee_id, SUM(amount) as general_amount
            FROM payments
            WHERE payment_type = 'general'
            AND fee_id IS NOT NULL
            AND academic_year = '" . $conn->real_escape_string($selected_academic_year) . "'" .
            ($selected_term !== 'All' ? " AND term = '" . $conn->real_escape_string($selected_term) . "'" : "") .
            " GROUP BY fee_id
        ) gp ON f.id = gp.fee_id
        LEFT JOIN students s ON sf.student_id = s.id
        WHERE (sf.student_id IS NULL OR s.status = 'active')
        GROUP BY f.id, f.name
        HAVING total > 0
        
        UNION ALL
        
        SELECT 'General Payment (Unallocated)' AS category,
               SUM(amount) AS total
        FROM payments
        WHERE payment_type = 'general'
        AND fee_id IS NULL
        AND academic_year = '" . $conn->real_escape_string($selected_academic_year) . "'" .
        ($selected_term !== 'All' ? " AND term = '" . $conn->real_escape_string($selected_term) . "'" : "") .
        "
        ORDER BY category
    ";
    $income_result = $conn->query($income_sql);
    while ($row = $income_result->fetch_assoc()) {
        $income_by_category[] = $row;
    }

    // Expenses by category - filtered by date range
    $expense_result = $conn->query("
        SELECT COALESCE(ec.name, 'Uncategorized') AS category, SUM(e.amount) AS total
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY e.category_id, ec.id, ec.name
        HAVING total > 0
        ORDER BY ec.name
    ");
    while ($row = $expense_result->fetch_assoc()) {
        $expense_by_category[] = $row;
        $total_expenses += $row['total'];
    }
}

// BUDGET VS ACTUAL REPORT DATA
$budget_comparison = [];
if ($report_type === 'budget' || $report_type === 'overview') {
    $terms_to_check = $selected_term === 'All' ? $available_terms : [$selected_term];
    
    foreach ($terms_to_check as $term) {
        $term_budget = $conn->query("SELECT * FROM term_budgets WHERE term = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc();
        
        if ($term_budget) {
            $term_range = getTermDateRange($conn, $term, $selected_academic_year);
            
            // Get budgeted income (sum of all fee assignments for active students only)
            $income_budget_stmt = $conn->prepare("
                SELECT COALESCE(SUM(sf.amount), 0) as total 
                FROM student_fees sf 
                INNER JOIN students s ON sf.student_id = s.id
                WHERE sf.term = ? AND sf.academic_year = ?
                AND s.status = 'active'
            ");
            $income_budget_stmt->bind_param('ss', $term, $selected_academic_year);
            $income_budget_stmt->execute();
            $income_budget = (float)$income_budget_stmt->get_result()->fetch_assoc()['total'];
            $income_budget_stmt->close();
            
            // Get actual income - use term and academic_year fields from payments table (active students only)
            $income_actual_stmt = $conn->prepare("
                SELECT COALESCE(SUM(p.amount), 0) as total 
                FROM payments p
                INNER JOIN students s ON p.student_id = s.id
                WHERE p.term = ? AND p.academic_year = ?
                AND s.status = 'active'
            ");
            $income_actual_stmt->bind_param('ss', $term, $selected_academic_year);
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
        AND s.status = 'active'
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Reports - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .report-filters {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h6 {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .stat-card .amount {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        .variance-badge {
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
        }
        @media print {
            .d-print-none { display: none !important; }
            .report-filters { display: none !important; }
        }
    </style>
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex gap-2 mb-2 d-print-none">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="term_budget.php" class="clean-back-btn">
                    <i class="fas fa-calculator"></i> Budget
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-chart-line me-2"></i>Comprehensive Reports</h1>
                    <p class="clean-page-subtitle">Financial Analytics & Insights</p>
                </div>
                <button onclick="window.print()" class="btn btn-outline-primary d-print-none">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        
        <!-- Filters Section -->
        <div class="report-filters d-print-none">
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label"><i class="fas fa-chart-bar"></i> Report Type</label>
                        <select name="report_type" class="form-select" onchange="this.form.submit()">
                            <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Overview</option>
                            <option value="income" <?php echo $report_type === 'income' ? 'selected' : ''; ?>>Income Analysis</option>
                            <option value="expenses" <?php echo $report_type === 'expenses' ? 'selected' : ''; ?>>Expense Analysis</option>
                            <option value="budget" <?php echo $report_type === 'budget' ? 'selected' : ''; ?>>Budget vs Actual</option>
                            <option value="student_fees" <?php echo $report_type === 'student_fees' ? 'selected' : ''; ?>>Student Fee Collection</option>
                            <option value="transactions" <?php echo $report_type === 'transactions' ? 'selected' : ''; ?>>Payment Transactions</option>
                            <option value="expense_details" <?php echo $report_type === 'expense_details' ? 'selected' : ''; ?>>Expense Transactions</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-calendar-alt"></i> Academic Year</label>
                        <select name="academic_year" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($year_options as $yr): ?>
                                <option value="<?php echo htmlspecialchars($yr); ?>" <?php echo $yr === $selected_academic_year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(formatAcademicYearDisplay($conn, $yr)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label"><i class="fas fa-calendar"></i> Term</label>
                        <select name="term" class="form-select" onchange="this.form.submit()">
                            <option value="All" <?php echo $selected_term === 'All' ? 'selected' : ''; ?>>All Terms</option>
                            <?php foreach ($available_terms as $term): ?>
                                <option value="<?php echo htmlspecialchars($term); ?>" <?php echo $selected_term === $term ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($term); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Apply</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Report Content Based on Type -->
        <?php if ($report_type === 'overview'): ?>
            
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6><i class="fas fa-arrow-down"></i> TOTAL INCOME</h6>
                        <div class="amount positive">GH₵<?php echo number_format($total_income, 2); ?></div>
                        <small class="text-muted"><?php echo $selected_term === 'All' ? $display_academic_year : $selected_term; ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6><i class="fas fa-arrow-up"></i> TOTAL EXPENSES</h6>
                        <div class="amount negative">GH₵<?php echo number_format($total_expenses, 2); ?></div>
                        <small class="text-muted"><?php echo $selected_term === 'All' ? $display_academic_year : $selected_term; ?></small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6><i class="fas fa-balance-scale"></i> NET BALANCE</h6>
                        <div class="amount <?php echo ($total_income - $total_expenses) >= 0 ? 'positive' : 'negative'; ?>">
                            GH₵<?php echo number_format($total_income - $total_expenses, 2); ?>
                        </div>
                        <small class="text-muted">Income - Expenses</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6><i class="fas fa-percentage"></i> EXPENSE RATIO</h6>
                        <div class="amount" style="font-size: 1.5rem;">
                            <?php echo $total_income > 0 ? number_format(($total_expenses / $total_income) * 100, 1) : '0'; ?>%
                        </div>
                        <small class="text-muted">Of Total Income</small>
                    </div>
                </div>
            </div>

            <!-- Income & Expenses Breakdown -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="clean-card">
                        <div class="clean-card-header">
                            <h5 class="clean-card-title text-success"><i class="fas fa-coins me-2"></i>Income by Category</h5>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="clean-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($income_by_category as $row): 
                                        $percentage = $total_income > 0 ? ($row['total'] / $total_income * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                                        <td class="text-end"><strong class="text-success">GH₵<?php echo number_format($row['total'], 2); ?></strong></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-success">
                                        <th>Total</th>
                                        <th class="text-end">GH₵<?php echo number_format($total_income, 2); ?></th>
                                        <th class="text-end">100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="clean-card">
                        <div class="clean-card-header">
                            <h5 class="clean-card-title text-danger"><i class="fas fa-receipt me-2"></i>Expenses by Category</h5>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="clean-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expense_by_category as $row): 
                                        $percentage = $total_expenses > 0 ? ($row['total'] / $total_expenses * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></td>
                                        <td class="text-end"><strong class="text-danger">GH₵<?php echo number_format($row['total'], 2); ?></strong></td>
                                        <td class="text-end"><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-danger">
                                        <th>Total</th>
                                        <th class="text-end">GH₵<?php echo number_format($total_expenses, 2); ?></th>
                                        <th class="text-end">100%</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Comparison -->
            <?php if (!empty($budget_comparison)): ?>
            <div class="clean-card mb-4">
                <div class="clean-card-header">
                    <h5 class="clean-card-title"><i class="fas fa-chart-bar me-2"></i>Budget vs Actual Performance</h5>
                </div>
                <div class="table-responsive">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Term</th>
                                <th class="text-center">Income Budget</th>
                                <th class="text-center">Income Actual</th>
                                <th class="text-center">Variance</th>
                                <th class="text-center">Expense Budget</th>
                                <th class="text-center">Expense Actual</th>
                                <th class="text-center">Variance</th>
                                <th class="text-center">Net Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budget_comparison as $term => $data): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($term); ?></strong></td>
                                <td class="text-center">GH₵<?php echo number_format($data['income_budget'], 2); ?></td>
                                <td class="text-center <?php echo $data['income_variance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    GH₵<?php echo number_format($data['income_actual'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <span class="variance-badge <?php echo $data['income_variance'] >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                                        <?php echo $data['income_variance'] >= 0 ? '+' : ''; ?><?php echo number_format($data['income_variance_pct'], 1); ?>%
                                    </span>
                                </td>
                                <td class="text-center">GH₵<?php echo number_format($data['expenses_budget'], 2); ?></td>
                                <td class="text-center <?php echo $data['expenses_variance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    GH₵<?php echo number_format($data['expenses_actual'], 2); ?>
                                </td>
                                <td class="text-center">
                                    <span class="variance-badge <?php echo $data['expenses_variance'] >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                                        <?php echo $data['expenses_variance'] >= 0 ? '+' : ''; ?><?php echo number_format($data['expenses_variance_pct'], 1); ?>%
                                    </span>
                                </td>
                                <td class="text-center fw-bold <?php echo $data['net_actual'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    GH₵<?php echo number_format($data['net_actual'], 2); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($report_type === 'student_fees'): ?>
            
            <div class="clean-card">
                <div class="clean-card-header">
                    <h5 class="clean-card-title"><i class="fas fa-users me-2"></i>Student Fee Collection Summary</h5>
                </div>
                <div class="table-responsive">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th class="text-end">Total Assigned</th>
                                <th class="text-end">Total Paid</th>
                                <th class="text-end">Balance</th>
                                <th class="text-end">% Collected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            $grand_assigned = 0;
                            $grand_paid = 0;
                            $grand_balance = 0;
                            foreach ($fee_collection_summary as $row): 
                                $collection_pct = $row['total_assigned'] > 0 ? ($row['total_paid'] / $row['total_assigned'] * 100) : 0;
                                $grand_assigned += $row['total_assigned'];
                                $grand_paid += $row['total_paid'];
                                $grand_balance += $row['balance'];
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['class']); ?></td>
                                <td class="text-end">GH₵<?php echo number_format($row['total_assigned'], 2); ?></td>
                                <td class="text-end text-success">GH₵<?php echo number_format($row['total_paid'], 2); ?></td>
                                <td class="text-end <?php echo $row['balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    GH₵<?php echo number_format($row['balance'], 2); ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge <?php echo $collection_pct >= 100 ? 'bg-success' : ($collection_pct >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                        <?php echo number_format($collection_pct, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-primary">
                                <th colspan="3">TOTAL</th>
                                <th class="text-end">GH₵<?php echo number_format($grand_assigned, 2); ?></th>
                                <th class="text-end">GH₵<?php echo number_format($grand_paid, 2); ?></th>
                                <th class="text-end">GH₵<?php echo number_format($grand_balance, 2); ?></th>
                                <th class="text-end">
                                    <?php echo $grand_assigned > 0 ? number_format(($grand_paid / $grand_assigned * 100), 1) : '0'; ?>%
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php elseif ($report_type === 'transactions'): ?>
            
            <div class="clean-card">
                <div class="clean-card-header">
                    <h5 class="clean-card-title"><i class="fas fa-money-bill-wave me-2"></i>Payment Transactions</h5>
                </div>
                <div class="table-responsive">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Fee Type</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $trans_total = 0;
                            foreach ($payment_transactions as $row): 
                                $trans_total += $row['amount'];
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($row['class'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['fee_name'] ?? 'N/A'); ?></td>
                                <td class="text-end text-success fw-bold">GH₵<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['reference_number'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-success">
                                <th colspan="4">TOTAL (Last 500 transactions)</th>
                                <th class="text-end">GH₵<?php echo number_format($trans_total, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php elseif ($report_type === 'expense_details'): ?>
            
            <div class="clean-card">
                <div class="clean-card-header">
                    <h5 class="clean-card-title"><i class="fas fa-file-invoice me-2"></i>Expense Transactions</h5>
                </div>
                <div class="table-responsive">
                    <table class="clean-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $exp_total = 0;
                            foreach ($expense_transactions as $row): 
                                $exp_total += $row['amount'];
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['expense_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['category'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                                <td class="text-end text-danger fw-bold">GH₵<?php echo number_format($row['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['reference_number'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-danger">
                                <th colspan="3">TOTAL (Last 500 transactions)</th>
                                <th class="text-end">GH₵<?php echo number_format($exp_total, 2); ?></th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
