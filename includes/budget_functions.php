<?php
/**
 * Budget Management Functions
 * Handles all budget-related operations and calculations
 */

/**
 * Get spending for a category in a semester
 */
function getTermCategorySpending($conn, $category, $semester, $academic_year) {
    require_once __DIR__ . '/term_helpers.php';
    $range = getTermDateRange($conn, $semester, $academic_year);
    
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(e.amount), 0) AS total 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE ec.name = ? AND e.expense_date BETWEEN ? AND ?
    ");
    
    $stmt->bind_param('sss', $category, $range['start'], $range['end']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (float)($row['total'] ?? 0);
}

/**
 * Get all budgets for a specific academic year (all terms)
 */
function getBudgetsByYear($conn, $academic_year) {
    $stmt = $conn->prepare("SELECT * FROM budgets WHERE academic_year = ? ORDER BY semester DESC, created_at DESC");
    $stmt->bind_param('s', $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $budgets = [];
    while ($row = $result->fetch_assoc()) {
        $budgets[] = $row;
    }
    $stmt->close();
    return $budgets;
}

/**
 * Get a specific budget by ID
 */
function getBudgetById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM budgets WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $budget = $result->fetch_assoc();
    $stmt->close();
    return $budget;
}

/**
 * Calculate total amount spent against a specific budget
 */
function getBudgetSpent($conn, $budget_id, $fiscal_year) {
    $budget = getBudgetById($conn, $budget_id);
    
    if (!$budget) {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(e.amount), 0) AS total 
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE ec.name = ? AND e.expense_date BETWEEN ? AND ?
    ");
    
    $stmt->bind_param('sss', $budget['category'], $budget['start_date'], $budget['end_date']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return (float)($row['total'] ?? 0);
}

/**
 * Get budget status based on spending percentage
 */
function getBudgetStatus($variance_percent) {
    if ($variance_percent <= 60) {
        return 'On Track';
    } elseif ($variance_percent <= 90) {
        return 'Warning';
    } else {
        return 'Over Budget';
    }
}

/**
 * Get budgets that have exceeded their alert threshold for a semester
 */
function getAlertedBudgets($conn, $semester, $academic_year) {
    $budgets = getBudgetsByTerm($conn, $semester, $academic_year);
    $alerted = [];
    
    foreach ($budgets as $budget) {
        $spent = getBudgetSpent($conn, $budget['id'], $budget['semester']);
        $percent = ((float)$budget['amount'] > 0) ? round(($spent / (float)$budget['amount']) * 100, 2) : 0;
        
        if ($percent >= $budget['alert_threshold']) {
            $budget['spent'] = $spent;
            $budget['percent'] = $percent;
            $alerted[] = $budget;
        }
    }
    
    return $alerted;
}

/**
 * Get budget summary statistics for a semester
 */
function getBudgetSummary($conn, $semester, $academic_year) {
    $budgets = getBudgetsByTerm($conn, $semester, $academic_year);
    
    $summary = [
        'total_budgeted' => 0,
        'total_spent' => 0,
        'total_remaining' => 0,
        'budget_count' => count($budgets),
        'over_budget_count' => 0,
        'on_track_count' => 0
    ];
    
    foreach ($budgets as $budget) {
        $spent = getBudgetSpent($conn, $budget['id'], $semester);
        $summary['total_budgeted'] += (float)$budget['amount'];
        $summary['total_spent'] += $spent;
        
        $percent = ((float)$budget['amount'] > 0) ? round(($spent / (float)$budget['amount']) * 100, 2) : 0;
        if ($percent > 100) {
            $summary['over_budget_count']++;
        } else {
            $summary['on_track_count']++;
        }
    }
    
    $summary['total_remaining'] = $summary['total_budgeted'] - $summary['total_spent'];
    
    return $summary;
}

/**
 * Get variance report for budgets in a semester
 */
function getVarianceReport($conn, $semester, $academic_year) {
    $budgets = getBudgetsByTerm($conn, $semester, $academic_year);
    $variance = [];
    
    foreach ($budgets as $budget) {
        $spent = getBudgetSpent($conn, $budget['id'], $semester);
        $variance_amount = (float)$budget['amount'] - $spent;
        $variance_percent = ((float)$budget['amount'] > 0) ? round(($spent / (float)$budget['amount']) * 100, 2) : 0;
        
        $variance[] = [
            'id' => $budget['id'],
            'category' => $budget['category'],
            'budgeted' => (float)$budget['amount'],
            'spent' => $spent,
            'variance_amount' => $variance_amount,
            'variance_percent' => $variance_percent,
            'status' => getBudgetStatus($variance_percent)
        ];
    }
    
    usort($variance, fn($a, $b) => $b['variance_percent'] - $a['variance_percent']);
    return $variance;
}
?>
