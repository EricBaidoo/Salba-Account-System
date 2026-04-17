<?php
require_once '../vendor/autoload.php';
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$semester = $_GET['semester'] ?? getCurrentSemester($conn);
$academic_year = $_GET['academic_year'] ?? getAcademicYear($conn);

// Get semester budget
$budget_query = "SELECT * FROM semester_budgets WHERE semester = ? AND academic_year = ?";
$budget_stmt = $conn->prepare($budget_query);
$budget_stmt->bind_param('ss', $semester, $academic_year);
$budget_stmt->execute();
$semester_budget = $budget_stmt->get_result()->fetch_assoc();

// Get budget items - ALWAYS show current assignments (same as semester_budget.php view)
$income_items = [];
$expense_items = [];

// Get income from current fee assignments (active students only)
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");
while ($fee = $fees_result->fetch_assoc()) {
    $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                      FROM student_fees sf 
                      INNER JOIN students s ON sf.student_id = s.id
                      WHERE sf.fee_id = {$fee['id']} 
                      AND sf.semester = '$semester' 
                      AND sf.academic_year = '$academic_year'
                      AND s.status = 'active'";
    $assigned_result = $conn->query($assigned_query);
    $amount = (float)$assigned_result->fetch_assoc()['total'];
    
    if ($amount > 0) {
        $income_items[] = ['category' => $fee['name'], 'amount' => $amount];
    }
}

// Get actual income collected from payments
require_once '../../../includes/term_helpers.php';
$range = getTermDateRange($conn, $semester, $academic_year);

// Get expenses - either from saved budget or from previous semester actual spending
if ($semester_budget) {
    $items_query = "SELECT * FROM semester_budget_items WHERE semester_budget_id = ? AND type = 'expense' ORDER BY category ASC";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param('i', $semester_budget['id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $expense_items[] = $item;
    }
}

// If no saved expense budget, get from previous semester spending
if (empty($expense_items)) {
    $expense_cats_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
    while ($cat = $expense_cats_result->fetch_assoc()) {
        $prev_term = getPreviousTerm($semester);
        $prev_year = ($prev_term === 'Third Semester') ? ($academic_year - 1) : $academic_year;
        $amount = getTermCategorySpending($conn, $cat['id'], $prev_term, $prev_year);
        
        if ($amount > 0) {
            $expense_items[] = ['category' => $cat['name'], 'amount' => $amount];
        }
    }
}

// Calculate totals
$total_income = array_sum(array_column($income_items, 'amount'));
$total_expenses = array_sum(array_column($expense_items, 'amount'));
$net_balance = $total_income - $total_expenses;

// Initialize mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 20,
    'margin_right' => 20,
    'margin_top' => 48,
    'margin_bottom' => 25,
    'margin_header' => 10,
    'margin_footer' => 10
]);

// Set header
$school_name = getSystemSetting($conn, 'school_name') ?? 'School Management System';
$school_address = getSystemSetting($conn, 'school_address') ?? '';
$school_phone = getSystemSetting($conn, 'school_phone') ?? '';
$school_email = getSystemSetting($conn, 'school_email') ?? '';

$header = '
<table width="100%" class="border-b-4 border-gray-800 pb-2.5 mb-2.5">
    <tr>
        <td class="text-center">
            <h2 style="margin: 0; color: #2c3e50; font-size: 22px; font-weight: bold; letter-spacing: 1px;">' . htmlspecialchars($school_name) . '</h2>
            <p style="margin: 5px 0 2px 0; font-size: 10px; color: #7f8c8d;">' . htmlspecialchars($school_address) . '</p>
            <p style="margin: 0; font-size: 9px; color: #95a5a6;">' . htmlspecialchars($school_phone) . ' | ' . htmlspecialchars($school_email) . '</p>
        </td>
    </tr>
</table>
';

$mpdf->SetHTMLHeader($header);

// Set footer
$mpdf->SetHTMLFooter('
<table width="100%" style="border-top: 1px solid #ddd; padding-top: 5px; font-size: 9px; color: #666;">
    <tr>
        <td width="33%">Generated: ' . date('d/m/Y H:i') . '</td>
        <td width="33%" align="center">Page {PAGENO} of {nbpg}</td>
        <td width="33%" align="right">Semester Budget Report</td>
    </tr>
</table>
');

// Build PDF content
$html = '

<h1>TERM BUDGET REPORT</h1>
<h2>' . htmlspecialchars($semester) . ' &bull; ' . htmlspecialchars($academic_year) . '</h2>
';

if ($semester_budget) {
    $status_class = ($semester_budget['status'] === 'locked') ? 'status-locked' : 'status-draft';
    $status_text = strtoupper($semester_budget['status']);
    $html .= '<p style="text-align: center; margin: 0 0 20px 0;"><span class="status-badge ' . $status_class . '">' . $status_text . '</span></p>';
    
    if ($semester_budget['status'] === 'locked') {
        $html .= '<p style="text-align: center; font-size: 9pt; color: #7f8c8d; margin: -15px 0 20px 0;">Locked by ' . htmlspecialchars($semester_budget['locked_by']) . ' on ' . date('d M Y H:i', strtotime($semester_budget['locked_at'])) . '</p>';
    }
}

// INCOME SECTION
$html .= '
<h3 class="income-header">INCOME BUDGET</h3>
<table>
    <thead>
        <tr>
            <th style="width: 65%;">Fee Category</th>
            <th class="text-right" style="width: 35%;">Budgeted Amount (GH₵)</th>
        </tr>
    </thead>
    <tbody>';

if (count($income_items) > 0) {
    foreach ($income_items as $item) {
        $html .= '
        <tr>
            <td class="category-cell">' . htmlspecialchars($item['category']) . '</td>
            <td class="text-right amount-cell">' . number_format($item['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="empty-message">No income items budgeted for this semester</td></tr>';
}

$html .= '
        <tr class="total-row">
            <td>TOTAL INCOME</td>
            <td class="text-right">GH₵ ' . number_format($total_income, 2) . '</td>
        </tr>
    </tbody>
</table>';

// EXPENSE SECTION
$html .= '
<h3 class="expense-header">EXPENSE BUDGET</h3>
<table>
    <thead>
        <tr>
            <th style="width: 65%;">Expense Category</th>
            <th class="text-right" style="width: 35%;">Budgeted Amount (GH₵)</th>
        </tr>
    </thead>
    <tbody>';

if (count($expense_items) > 0) {
    foreach ($expense_items as $item) {
        $html .= '
        <tr>
            <td class="category-cell">' . htmlspecialchars($item['category']) . '</td>
            <td class="text-right amount-cell">' . number_format($item['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="empty-message">No expense items budgeted for this semester</td></tr>';
}

$html .= '
        <tr class="total-row expense-total-row">
            <td>TOTAL EXPENSES</td>
            <td class="text-right">GH₵ ' . number_format($total_expenses, 2) . '</td>
        </tr>
    </tbody>
</table>';

// SUMMARY BOX
$net_color = ($net_balance >= 0) ? '#27ae60' : '#e74c3c';
$html .= '
<div class="summary-box">
    <h3>BUDGET SUMMARY</h3>
    <table>
        <tr>
            <td style="font-weight: 600;">Total Expected Income:</td>
            <td class="text-right amount-cell" style="color: #27ae60;">GH₵ ' . number_format($total_income, 2) . '</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Total Budgeted Expenses:</td>
            <td class="text-right amount-cell" style="color: #e74c3c;">GH₵ ' . number_format($total_expenses, 2) . '</td>
        </tr>
        <tr>
            <td style="color: ' . $net_color . '; font-weight: bold;">Projected Net Balance:</td>
            <td class="text-right amount-cell" style="color: ' . $net_color . ';">GH₵ ' . number_format($net_balance, 2) . '</td>
        </tr>
    </table>
</div>';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Output PDF
$filename = 'Budget_' . str_replace(' ', '_', $semester) . '_' . $academic_year . '.pdf';
$mpdf->Output($filename, 'D'); // D = download
