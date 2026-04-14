<?php
/**
 * Financial Report Data Preparation Functions
 * Organizes complex queries and calculations for the financial report dashboard
 */

// Helper functions that are defined locally in this file
if (!function_exists('reportApplyTermYearScope')) {
    function reportApplyTermYearScope(array &$conditions, array &$params, string &$types, string $tableAlias, ?string $term, ?string $academic_year): void {
        if ($term !== null && $term !== '') {
            $conditions[] = $tableAlias . '.term = ?';
            $params[] = $term;
            $types .= 's';
        }

        if ($academic_year !== null && $academic_year !== '') {
            $conditions[] = '(' . $tableAlias . '.academic_year = ? OR ' . $tableAlias . '.academic_year IS NULL)';
            $params[] = $academic_year;
            $types .= 's';
        }
    }
}

if (!function_exists('reportApplyDateScope')) {
    function reportApplyDateScope(array &$conditions, array &$params, string &$types, string $dateColumn, ?string $dateFrom, ?string $dateTo): void {
        if ($dateFrom !== null && $dateFrom !== '') {
            $conditions[] = $dateColumn . ' >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo !== null && $dateTo !== '') {
            $conditions[] = $dateColumn . ' <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }
    }
}

if (!function_exists('reportFetchTotals')) {
    function reportFetchTotals(mysqli $conn, string $table, string $dateColumn, ?string $dateFrom, ?string $dateTo): float {
        $sql = 'SELECT COALESCE(SUM(amount), 0) AS total FROM ' . $table . ' WHERE 1=1';
        $conditions = [];
        $params = [];
        $types = '';
        reportApplyDateScope($conditions, $params, $types, $dateColumn, $dateFrom, $dateTo);

        if (!empty($conditions)) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (float)($row['total'] ?? 0);
    }
}

if (!function_exists('reportFetchMonthlyTotals')) {
    function reportFetchMonthlyTotals(mysqli $conn, string $table, string $dateColumn, string $fromDate, string $toDate): array {
        $sql = "SELECT DATE_FORMAT($dateColumn, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS total
                FROM $table
                WHERE $dateColumn BETWEEN ? AND ?
                GROUP BY DATE_FORMAT($dateColumn, '%Y-%m')
                ORDER BY month_key ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ss', $fromDate, $toDate);
        $stmt->execute();
        $result = $stmt->get_result();

        $monthly = [];
        while ($row = $result->fetch_assoc()) {
            $monthly[(string)($row['month_key'] ?? '')] = (float)($row['total'] ?? 0);
        }
        $stmt->close();

        return $monthly;
    }
}

if (!function_exists('reportBuildMonthBuckets')) {
    function reportBuildMonthBuckets(string $fromDate, string $toDate): array {
        $start = new DateTime(substr($fromDate, 0, 7) . '-01');
        $end = new DateTime(substr($toDate, 0, 7) . '-01');
        $end->modify('first day of next month');
        $period = new DatePeriod($start, new DateInterval('P1M'), $end);

        $buckets = [];
        foreach ($period as $month) {
            $key = $month->format('Y-m');
            $buckets[] = [
                'key' => $key,
                'label' => $month->format('M Y'),
            ];
        }

        return $buckets;
    }
}

if (!function_exists('reportTopCategory')) {
    function reportTopCategory(array $items): array {
        if (empty($items)) {
            return ['label' => 'N/A', 'amount' => 0.0];
        }

        return [
            'label' => (string)($items[0]['category'] ?? 'N/A'),
            'amount' => (float)($items[0]['amount'] ?? 0),
        ];
    }
}

if (!function_exists('reportGetAcademicYearRange')) {
    function reportGetAcademicYearRange(mysqli $conn, ?string $academic_year): array {
        $academic_year = trim((string)$academic_year);
        if ($academic_year === '') {
            $academic_year = function_exists('getAcademicYear') ? getAcademicYear($conn) : date('Y');
        }

        $start_date = function_exists('getAcademicYearStart') ? getAcademicYearStart($conn, $academic_year) : date('Y') . '-09-01';
        $start_ts = strtotime($start_date);
        if ($start_ts === false) {
            $start_ts = strtotime(date('Y') . '-09-01');
        }

        $end_ts = strtotime('+1 year -1 day', $start_ts);

        return [
            'start' => date('Y-m-d', $start_ts),
            'end' => date('Y-m-d', $end_ts),
        ];
    }
}

// Main data building functions

if (!function_exists('reportBuildCategoryData')) {
    function reportBuildCategoryData(mysqli $conn, string $tableAlias, string $selected_term, string $selected_year, string $date_from, string $date_to, int $limit = 10): array {
        if (strpos($tableAlias, 'expense') !== false) {
            $sql = "SELECT COALESCE(ec.name, 'Uncategorized') AS category_name, SUM(e.amount) AS amount
                    FROM expenses e
                    LEFT JOIN expense_categories ec ON e.category_id = ec.id";
            $date_column = 'e.expense_date';
            $table = 'e';
        } else {
            $sql = "SELECT COALESCE(f.name, 'Uncategorized') AS category_name, SUM(sf.amount) AS amount
                    FROM student_fees sf
                    JOIN fees f ON sf.fee_id = f.id
                    WHERE sf.status != 'cancelled'";
            $date_column = 'sf.assigned_date';
            $table = 'sf';
        }

        $conditions = [];
        $params = [];
        $types = '';

        if (strpos($tableAlias, 'expense') === false) {
            reportApplyTermYearScope($conditions, $params, $types, $table, $selected_term, $selected_year);
        }
        reportApplyDateScope($conditions, $params, $types, $date_column, $date_from ?: null, $date_to ?: null);

        if (!empty($conditions)) {
            $sql .= (strpos($tableAlias, 'expense') !== false ? ' WHERE ' : ' AND ') . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY ' . (strpos($tableAlias, 'expense') !== false ? 'ec.name' : 'f.name') . ' ORDER BY amount DESC LIMIT ' . (int)$limit;

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'category' => (string)($row['category_name'] ?? 'Uncategorized'),
                'amount' => (float)($row['amount'] ?? 0),
            ];
        }
        $stmt->close();

        return $data;
    }
}

if (!function_exists('reportBuildClassIncomeData')) {
    function reportBuildClassIncomeData(mysqli $conn, string $selected_term, string $selected_year, string $date_from, string $date_to, int $limit = 12): array {
        $sql = "SELECT COALESCE(s.class, 'Unassigned') AS class_name, SUM(sf.amount) AS amount
                FROM student_fees sf
                JOIN students s ON sf.student_id = s.id
                WHERE sf.status != 'cancelled'";
        $conditions = [];
        $params = [];
        $types = '';
        reportApplyTermYearScope($conditions, $params, $types, 'sf', $selected_term, $selected_year);
        reportApplyDateScope($conditions, $params, $types, 'sf.assigned_date', $date_from ?: null, $date_to ?: null);

        if (!empty($conditions)) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }
        $sql .= ' GROUP BY s.class ORDER BY amount DESC LIMIT ' . (int)$limit;

        $stmt = $conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'class' => (string)($row['class_name'] ?? 'Unassigned'),
                'amount' => (float)($row['amount'] ?? 0),
            ];
        }
        $stmt->close();

        return $data;
    }
}

if (!function_exists('reportBuildClassBalanceData')) {
    function reportBuildClassBalanceData(mysqli $conn, string $selected_term, string $selected_year): array {
        $rows = [];
        $class_result = $conn->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class ASC");
        
        if ($class_result) {
            while ($class_row = $class_result->fetch_assoc()) {
                $class_name = trim((string)($class_row['class'] ?? ''));
                if ($class_name === '') {
                    continue;
                }

                $balances = function_exists('getAllStudentBalances') ? getAllStudentBalances($conn, $class_name, 'active', $selected_term, $selected_year) : [];
                $class_total_fees = 0.0;
                $class_total_payments = 0.0;
                $class_outstanding = 0.0;
                $class_students = 0;
                $class_overdue_count = 0;

                foreach ($balances as $balance) {
                    $class_students++;
                    $class_total_fees += (float)($balance['total_fees'] ?? 0);
                    $class_total_payments += (float)($balance['total_payments'] ?? 0);
                    $net_balance_value = (float)($balance['net_balance'] ?? 0);
                    $class_outstanding += $net_balance_value;
                    if ($net_balance_value > 0) {
                        $class_overdue_count++;
                    }
                }

                $class_collection_rate = $class_total_fees > 0 ? round(($class_total_payments / $class_total_fees) * 100, 1) : 0;
                $rows[] = [
                    'class' => $class_name,
                    'students' => $class_students,
                    'fees' => $class_total_fees,
                    'payments' => $class_total_payments,
                    'outstanding' => $class_outstanding,
                    'collection_rate' => $class_collection_rate,
                    'overdue_count' => $class_overdue_count,
                ];
            }
            $class_result->close();
        }

        usort($rows, function (array $a, array $b): int {
            return ($b['outstanding'] <=> $a['outstanding']);
        });

        return $rows;
    }
}

if (!function_exists('reportBuildMonthlyTrendData')) {
    function reportBuildMonthlyTrendData(mysqli $conn, string $date_from, string $date_to): array {
        $income_monthly_raw = reportFetchMonthlyTotals($conn, 'payments', 'payment_date', $date_from, $date_to);
        $expense_monthly_raw = reportFetchMonthlyTotals($conn, 'expenses', 'expense_date', $date_from, $date_to);
        $month_buckets = reportBuildMonthBuckets($date_from, $date_to);

        $monthly_labels = [];
        $monthly_income_values = [];
        $monthly_expense_values = [];
        $monthly_net_values = [];
        
        foreach ($month_buckets as $bucket) {
            $key = $bucket['key'];
            $monthly_labels[] = $bucket['label'];
            $income_value = (float)($income_monthly_raw[$key] ?? 0);
            $expense_value = (float)($expense_monthly_raw[$key] ?? 0);
            $monthly_income_values[] = $income_value;
            $monthly_expense_values[] = $expense_value;
            $monthly_net_values[] = $income_value - $expense_value;
        }

        return [
            'labels' => $monthly_labels,
            'income' => $monthly_income_values,
            'expenses' => $monthly_expense_values,
            'net' => $monthly_net_values,
            'buckets' => $month_buckets,
        ];
    }
}

if (!function_exists('reportBuildBudgetData')) {
    function reportBuildBudgetData(mysqli $conn, string $selected_term, string $selected_year, float $income_total, float $expense_total): array {
        $income_budget = 0.0;
        $expense_budget = 0.0;

        $budget_stmt = $conn->prepare("SELECT id, expected_income FROM term_budgets WHERE term = ? AND academic_year = ? LIMIT 1");
        $budget_stmt->bind_param('ss', $selected_term, $selected_year);
        $budget_stmt->execute();
        $budget_row = $budget_stmt->get_result()->fetch_assoc();
        $budget_stmt->close();

        if ($budget_row) {
            $income_budget = (float)($budget_row['expected_income'] ?? 0);
            $budget_id = (int)($budget_row['id'] ?? 0);
            if ($budget_id > 0) {
                $expense_budget_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM term_budget_items WHERE term_budget_id = ? AND type = 'expense'");
                $expense_budget_stmt->bind_param('i', $budget_id);
                $expense_budget_stmt->execute();
                $expense_budget_row = $expense_budget_stmt->get_result()->fetch_assoc();
                $expense_budget_stmt->close();
                $expense_budget = (float)($expense_budget_row['total'] ?? 0);
            }
        }

        $income_collection_rate = $income_budget > 0 ? round(($income_total / $income_budget) * 100, 1) : 0;
        $budget_spend_rate = $expense_budget > 0 ? round(($expense_total / $expense_budget) * 100, 1) : 0;

        return [
            'income_budget' => $income_budget,
            'expense_budget' => $expense_budget,
            'income_collection_rate' => $income_collection_rate,
            'budget_spend_rate' => $budget_spend_rate,
            'income_variance_percent' => $income_budget > 0 ? (($income_total - $income_budget) / $income_budget) * 100 : 0,
            'expense_variance_percent' => $expense_budget > 0 ? (($expense_budget - $expense_total) / $expense_budget) * 100 : 0,
            'income_variance_abs' => abs($income_total - $income_budget),
            'expense_variance_abs' => abs($expense_budget - $expense_total),
        ];
    }
}

if (!function_exists('reportBuildOverdueStudents')) {
    function reportBuildOverdueStudents(mysqli $conn, string $selected_term, string $selected_year, int $limit = 8): array {
        $overdue_stmt = $conn->prepare("SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) AS student_name, s.class, sf.amount, sf.amount_paid, (sf.amount - sf.amount_paid) AS balance_due
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            WHERE sf.status != 'cancelled'
              AND sf.term = ?
              AND (sf.academic_year = ? OR sf.academic_year IS NULL)
              AND (sf.amount - sf.amount_paid) > 0
            ORDER BY balance_due DESC, s.class ASC, student_name ASC
            LIMIT ?");
        $overdue_stmt->bind_param('ssi', $selected_term, $selected_year, $limit);
        $overdue_stmt->execute();
        $overdue_result = $overdue_stmt->get_result();

        $students = [];
        while ($row = $overdue_result->fetch_assoc()) {
            $students[] = [
                'id' => (int)($row['id'] ?? 0),
                'student_name' => (string)($row['student_name'] ?? ''),
                'class' => (string)($row['class'] ?? ''),
                'balance_due' => (float)($row['balance_due'] ?? 0),
            ];
        }
        $overdue_stmt->close();

        return $students;
    }
}

if (!function_exists('reportBuildSummaryInsights')) {
    function reportBuildSummaryInsights(float $income_collection_rate, float $budget_spend_rate, float $income_budget, float $expense_budget, array $top_income_category, array $top_expense_category): array {
        return [
            [
                'label' => 'Collections vs budget',
                'value' => $income_collection_rate > 0 ? number_format($income_collection_rate, 1) . '%' : 'N/A',
                'note' => $income_budget > 0 ? 'Income collected against expected revenue' : 'No term budget set',
                'tone' => $income_collection_rate >= 100 ? 'green' : ($income_collection_rate >= 75 ? 'blue' : 'red'),
            ],
            [
                'label' => 'Spend rate',
                'value' => $budget_spend_rate > 0 ? number_format($budget_spend_rate, 1) . '%' : 'N/A',
                'note' => $expense_budget > 0 ? 'Expenses used against budget' : 'No expense budget set',
                'tone' => $budget_spend_rate <= 100 ? 'green' : 'red',
            ],
            [
                'label' => 'Top income category',
                'value' => $top_income_category['label'],
                'note' => 'Highest fee group by value',
                'tone' => 'blue',
            ],
            [
                'label' => 'Top expense category',
                'value' => $top_expense_category['label'],
                'note' => 'Largest cost driver in the period',
                'tone' => 'red',
            ],
        ];
    }
}
?>

