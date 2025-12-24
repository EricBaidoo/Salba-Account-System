<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';
include '../includes/system_settings.php';

// Academic year selection
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
$selected_academic_year = isset($_GET['academic_year']) && $_GET['academic_year'] !== ''
    ? $_GET['academic_year']
    : $default_academic_year;
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Build academic year options from data + default
$year_options = [];
$yrs1 = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs1) {
    while ($yr = $yrs1->fetch_assoc()) {
        if (!empty($yr['academic_year'])) { $year_options[] = $yr['academic_year']; }
    }
    $yrs1->close();
}
$yrs2 = $conn->query("SELECT DISTINCT academic_year FROM payments WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs2) {
    while ($yr = $yrs2->fetch_assoc()) {
        if (!empty($yr['academic_year']) && !in_array($yr['academic_year'], $year_options, true)) {
            $year_options[] = $yr['academic_year'];
        }
    }
    $yrs2->close();
}
if (!in_array($default_academic_year, $year_options, true)) {
    array_unshift($year_options, $default_academic_year);
}
// Total paid (synced with dashboard and category breakdown)
// Total paid for selected academic year
$total_paid = 0;
$stmtTotal = $conn->prepare('
    SELECT SUM(total) AS total FROM (
        SELECT COALESCE(sf_sum.total,0) + COALESCE(p_sum.total,0) AS total
        FROM fees f
        LEFT JOIN (
            SELECT fee_id, SUM(amount_paid) AS total
            FROM student_fees
            WHERE academic_year = ?
            GROUP BY fee_id
        ) sf_sum ON sf_sum.fee_id = f.id
        LEFT JOIN (
            SELECT fee_id, SUM(amount) AS total
            FROM payments
            WHERE payment_type = "general" AND academic_year = ?
            GROUP BY fee_id
        ) p_sum ON p_sum.fee_id = f.id
    ) t
');
if ($stmtTotal) {
    $stmtTotal->bind_param('ss', $selected_academic_year, $selected_academic_year);
    $stmtTotal->execute();
    $res = $stmtTotal->get_result();
    $row = $res->fetch_assoc();
    $total_paid = $row ? ($row['total'] ?? 0) : 0;
    $stmtTotal->close();
}
// Income by fee category (for breakdown)
$income_by_category = null;
$stmtIncome = $conn->prepare('
    SELECT f.name AS category,
           COALESCE(sf_sum.total,0) + COALESCE(p_sum.total,0) AS total
    FROM fees f
    LEFT JOIN (
        SELECT fee_id, SUM(amount_paid) AS total
        FROM student_fees
        WHERE academic_year = ?
        GROUP BY fee_id
    ) sf_sum ON sf_sum.fee_id = f.id
    LEFT JOIN (
        SELECT fee_id, SUM(amount) AS total
        FROM payments
        WHERE payment_type = "general" AND academic_year = ?
        GROUP BY fee_id
    ) p_sum ON p_sum.fee_id = f.id
    ORDER BY f.name
');
if ($stmtIncome) {
    $stmtIncome->bind_param('ss', $selected_academic_year, $selected_academic_year);
    $stmtIncome->execute();
    $income_by_category = $stmtIncome->get_result();
}
$income_rows = [];
while ($row = $income_by_category->fetch_assoc()) {
    $income_rows[] = $row;
}
// Expenses by category (filtered to academic year date window if possible)
// Derive date range from selected academic year and optional settings
$parts = explode('/', $selected_academic_year);
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