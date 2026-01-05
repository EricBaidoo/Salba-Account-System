<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
include '../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Handle budget lock/unlock actions
if ((isset($_GET['action']) && $_GET['action'] === 'lock') && isset($_GET['term']) && isset($_GET['academic_year'])) {
    $term = $_GET['term'];
    $year = $_GET['academic_year'];
    $user = $_SESSION['username'] ?? 'System';
    
    $conn->query("UPDATE term_budgets 
                 SET status = 'locked', locked_at = NOW(), locked_by = '$user' 
                 WHERE term = '$term' AND academic_year = '$year'");
    header("Location: term_budget.php?term=" . urlencode($term) . "&academic_year=" . urlencode($year) . "&locked=1");
    exit;
}

if ((isset($_GET['action']) && $_GET['action'] === 'unlock') && isset($_GET['term']) && isset($_GET['academic_year'])) {
    $term = $_GET['term'];
    $year = $_GET['academic_year'];
    
    $conn->query("UPDATE term_budgets 
                 SET status = 'draft', locked_at = NULL, locked_by = NULL 
                 WHERE term = '$term' AND academic_year = '$year'");
    header("Location: term_budget.php?term=" . urlencode($term) . "&academic_year=" . urlencode($year) . "&unlocked=1");
    exit;
}

$lock_notification = '';
if ($_GET['locked'] ?? false) {
    $lock_notification = '<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-lock"></i> Budget is now LOCKED. No further edits allowed.</div>';
}
if ($_GET['unlocked'] ?? false) {
    $lock_notification = '<div class="alert alert-info alert-dismissible fade show"><i class="fas fa-lock-open"></i> Budget UNLOCKED. You can now make edits.</div>';
}

// Get selected term and academic year (allow user to change)
$system_term = getCurrentTerm($conn);
$system_academic_year = getAcademicYear($conn);

$selected_term = $_GET['term'] ?? $system_term;
$selected_academic_year = $_GET['academic_year'] ?? $system_academic_year;
$current_term = $selected_term;
$academic_year = $selected_academic_year;

// Get all available terms for dropdown
$available_terms = getAvailableTerms();

// Get all available academic years
$years_result = $conn->query("SELECT DISTINCT academic_year FROM student_fees ORDER BY academic_year DESC LIMIT 5");
$available_years = [$system_academic_year]; // Always include current year
while ($year_row = $years_result->fetch_assoc()) {
    if (!in_array($year_row['academic_year'], $available_years)) {
        $available_years[] = $year_row['academic_year'];
    }
}

// Get or create term budget
$term_budget = $conn->query("SELECT * FROM term_budgets WHERE term = '$current_term' AND academic_year = '$academic_year'")->fetch_assoc();
$is_locked = $term_budget && isset($term_budget['status']) && $term_budget['status'] === 'locked';

// Get ALL fee categories with assigned amounts as budget
$income_items = [];
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");

// Debug: Check what's in student_fees for this term
$debug_all = "SELECT term, academic_year, COUNT(*) as count, SUM(amount) as total 
              FROM student_fees 
              WHERE term LIKE '%2%' OR academic_year = '$academic_year'
              GROUP BY term, academic_year";
$debug_result = $conn->query($debug_all);
echo "<!-- DEBUG: Looking for term='$current_term' and academic_year='$academic_year' -->";
echo "<!-- Available data: ";
while ($d = $debug_result->fetch_assoc()) {
    echo "Term: '{$d['term']}', Year: '{$d['academic_year']}', Count: {$d['count']}, Total: {$d['total']} | ";
}
echo "-->";

while ($fee = $fees_result->fetch_assoc()) {
    $row = [];
    $row['category'] = $fee['name'];
    
    // Get assigned fees total - THIS IS THE BUDGET (active students only)
    $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                      FROM student_fees sf 
                      INNER JOIN students s ON sf.student_id = s.id
                      WHERE sf.fee_id = {$fee['id']} 
                      AND sf.term = '$current_term' 
                      AND sf.academic_year = '$academic_year'
                      AND s.status = 'active'";
    $assigned_result = $conn->query($assigned_query);
    $row['amount'] = (float)$assigned_result->fetch_assoc()['total']; // Assigned = Budgeted
    $row['assigned'] = $row['amount'];
    
    // Get actual income collected
    require_once '../includes/term_helpers.php';
    $range = getTermDateRange($conn, $current_term, $academic_year);
    $actual_result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE fee_id = {$fee['id']} AND payment_date BETWEEN '{$range['start']}' AND '{$range['end']}'");
    $row['actual'] = (float)$actual_result->fetch_assoc()['total'];
    
    $row['variance'] = $row['actual'] - $row['amount'];
    $row['variance_percent'] = $row['amount'] > 0 ? round(($row['actual'] / $row['amount']) * 100, 2) : 0;
    $income_items[] = $row;
}

// Get ALL expense categories with budgeted amounts from previous term actual spending
$expense_items = [];
$categories_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");

// Get previous term
$terms = getAvailableTerms();
$current_term_index = array_search($current_term, $terms);
$previous_term = null;
$previous_academic_year = $academic_year;

if ($current_term_index > 0) {
    // Previous term in same academic year
    $previous_term = $terms[$current_term_index - 1];
} elseif ($current_term_index === 0) {
    // Previous term is Term 3 of previous academic year
    $previous_term = 'Term 3';
    $year_parts = explode('/', $academic_year);
    $previous_academic_year = ($year_parts[0] - 1) . '/' . ($year_parts[1] - 1);
}

while ($category = $categories_result->fetch_assoc()) {
    $row = [];
    $row['category'] = $category['name'];
    
    // Get previous term's actual spending as budget
    if ($previous_term) {
        $row['amount'] = getTermCategorySpending($conn, $category['name'], $previous_term, $previous_academic_year);
    } else {
        $row['amount'] = 0;
    }
    
    // Override with manually set budget if exists
    if ($term_budget) {
        $budget_query = "SELECT amount FROM term_budget_items 
                        WHERE term_budget_id = {$term_budget['id']} 
                        AND type = 'expense' 
                        AND category = '" . $conn->real_escape_string($category['name']) . "'";
        $budget_result = $conn->query($budget_query);
        if ($budget_row = $budget_result->fetch_assoc()) {
            $row['amount'] = (float)$budget_row['amount'];
        }
    }
    
    // Get actual spending for current term
    $row['actual'] = getTermCategorySpending($conn, $category['name'], $current_term, $academic_year);
    $row['variance'] = $row['amount'] - $row['actual'];
    $row['variance_percent'] = $row['amount'] > 0 ? round(($row['actual'] / $row['amount']) * 100, 2) : 0;
    $expense_items[] = $row;
}

// Calculate totals
$total_income_budgeted = array_sum(array_map(fn($b) => (float)$b['amount'], $income_items));
$total_income_actual = array_sum(array_map(fn($b) => (float)$b['actual'], $income_items));
$total_expense_budgeted = array_sum(array_map(fn($b) => (float)$b['amount'], $expense_items));
$total_expense_actual = array_sum(array_map(fn($b) => (float)$b['actual'], $expense_items));
$net_budgeted = $total_income_budgeted - $total_expense_budgeted;
$net_actual = $total_income_actual - $total_expense_actual;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Term Budget - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .income-card {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #333;
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .income-card h6 {
            font-size: 0.9rem;
            margin-bottom: 10px;
            opacity: 0.8;
        }
        .income-card .amount {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .budget-row {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            background: white;
        }
        .budget-row:hover {
            background: #f9f9f9;
        }
        .budget-row .category {
            flex: 1;
        }
        .budget-row .numbers {
            flex: 3;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
            gap: 15px;
            text-align: right;
        }
        .budget-row.header {
            background: #f8f9fa;
            font-weight: bold;
            border-top: 2px solid #ddd;
        }
        .budget-row.total {
            background: #e7f3ff;
            font-weight: bold;
            border-top: 2px solid #0066cc;
        }
        .status-good { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-alert { color: #dc3545; }
        .progress-mini {
            height: 6px;
            margin-top: 3px;
        }
        @media print {
            .d-print-none { display: none !important; }
        }
    </style>
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex gap-2 mb-2">
                <a href="dashboard.php" class="clean-back-btn d-print-none">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="report.php" class="clean-back-btn d-print-none">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-calculator me-2"></i>Term Budget</h1>
                    <p class="clean-page-subtitle"><?php echo htmlspecialchars($current_term); ?> - <?php echo htmlspecialchars($academic_year); ?></p>
                </div>
                <div class="d-flex gap-2 d-print-none">
                    <a href="download_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="btn-clean-outline">
                        <i class="fas fa-download"></i> DOWNLOAD PDF
                    </a>
                    <a href="#" onclick="window.print()" class="btn-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <?php if (!$is_locked): ?>
                        <?php if (!$term_budget): ?>
                            <a href="edit_term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="btn-clean-primary">
                                <i class="fas fa-plus"></i> SET UP BUDGET
                            </a>
                        <?php else: ?>
                            <a href="edit_term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>" class="btn-clean-primary">
                                <i class="fas fa-edit"></i> EDIT BUDGET
                            </a>
                        <?php endif; ?>
                        <?php if ($term_budget): ?>
                            <a href="term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>&action=lock" class="btn btn-warning" onclick="return confirm('Lock this budget? You will not be able to edit it.');">
                                <i class="fas fa-lock"></i> LOCK BUDGET
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-danger" style="padding: 8px 12px; font-size: 0.9rem;">
                            <i class="fas fa-lock"></i> LOCKED BY <?php echo htmlspecialchars($term_budget['locked_by']); ?> ON <?php echo date('d M Y H:i', strtotime($term_budget['locked_at'])); ?>
                        </span>
                        <a href="term_budget.php?term=<?php echo urlencode($current_term); ?>&academic_year=<?php echo urlencode($academic_year); ?>&action=unlock" class="btn btn-secondary" onclick="return confirm('Unlock this budget to allow edits again?');">
                            <i class="fas fa-lock-open"></i> UNLOCK
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <?php echo $lock_notification; ?>

        <!-- INCOME BUDGET -->
        <div class="clean-card mb-4">
            <div class="clean-card-header" style="background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);">
                <h5 class="clean-card-title" style="color: #333;"><i class="fas fa-coins me-2"></i>INCOME BUDGET - <?php echo htmlspecialchars($current_term); ?></h5>
            </div>
            <div class="clean-card-body p-0">
                <?php if (count($income_items) > 0): ?>
                <table class="table table-hover mb-0">
                    <thead style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <tr>
                            <th style="width: 60%;">Fee Category</th>
                            <th class="text-end" style="width: 40%;">Budgeted Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($income_items as $item): 
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['category']); ?></strong></td>
                            <td class="text-end"><h5 class="mb-0">GH₵<?php echo number_format($item['amount'], 2); ?></h5></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: #e7f3ff; border-top: 3px solid #0066cc; font-weight: bold; font-size: 1.2em;">
                        <tr>
                            <td><strong>TOTAL EXPECTED INCOME</strong></td>
                            <td class="text-end"><h4 class="mb-0 text-primary">GH₵<?php echo number_format($total_income_budgeted, 2); ?></h4></td>
                        </tr>
                    </tfoot>
                </table>
                <?php else: ?>
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No income budget set up yet. <a href="edit_term_budget.php">Click here to set up your budget</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EXPENSE BUDGET -->
        <div class="clean-card mb-4">
            <div class="clean-card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h5 class="clean-card-title" style="color: white;"><i class="fas fa-file-invoice-dollar me-2"></i>EXPENSE BUDGET</h5>
            </div>
            <div class="clean-card-body p-0">
                <?php if (count($expense_items) > 0): ?>
                <table class="table table-hover mb-0">
                    <thead style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                        <tr>
                            <th style="width: 60%;">Expense Category</th>
                            <th class="text-end" style="width: 40%;">Budgeted Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expense_items as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['category']); ?></strong></td>
                            <td class="text-end"><h5 class="mb-0">GH₵<?php echo number_format($item['amount'], 2); ?></h5></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot style="background: #ffe7e7; border-top: 3px solid #dc3545; font-weight: bold; font-size: 1.2em;">
                        <tr>
                            <td><strong>TOTAL BUDGETED EXPENSES</strong></td>
                            <td class="text-end"><h4 class="mb-0 text-danger">GH₵<?php echo number_format($total_expense_budgeted, 2); ?></h4></td>
                        </tr>
                    </tfoot>
                </table>

                <?php else: ?>
                <div class="p-4 text-center">
                    <p class="text-muted">No expense categories budgeted yet. <a href="edit_term_budget.php">Add categories to your budget</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Summary -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="clean-card text-center">
                    <div class="p-4">
                        <h6 class="text-muted mb-2">Budgeted Net Position</h6>
                        <div class="h3 mb-2 <?php echo $net_budgeted >= 0 ? 'text-success' : 'text-danger'; ?>">
                            GH₵<?php echo number_format($net_budgeted, 2); ?>
                        </div>
                        <small class="text-muted">Budgeted Income - Budgeted Expenses</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="clean-card text-center">
                    <div class="p-4">
                        <h6 class="text-muted mb-2">Actual Net Position</h6>
                        <div class="h3 mb-2 <?php echo $net_actual >= 0 ? 'text-success' : 'text-danger'; ?>">
                            GH₵<?php echo number_format($net_actual, 2); ?>
                        </div>
                        <small class="text-muted">Actual Income - Actual Expenses</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="clean-card text-center">
                    <div class="p-4">
                        <h6 class="text-muted mb-2">Budget Status</h6>
                        <div class="h3 mb-2">
                            <?php 
                            if ($total_expense_actual <= $total_expense_budgeted && $total_income_actual >= $total_income_budgeted) {
                                echo '<span class="status-good">✓ On Track</span>';
                            } elseif ($total_expense_actual <= $total_expense_budgeted * 1.1) {
                                echo '<span class="status-warning">⚠ Caution</span>';
                            } else {
                                echo '<span class="status-alert">✗ Over Budget</span>';
                            }
                            ?>
                        </div>
                        <small class="text-muted">Overall health</small>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
