<?php
require_once '../vendor/autoload.php';
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
include '../includes/system_settings.php';
include '../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$term = $_GET['term'] ?? getCurrentTerm($conn);
$academic_year = $_GET['academic_year'] ?? getAcademicYear($conn);

// Get term budget
$budget_query = "SELECT * FROM term_budgets WHERE term = ? AND academic_year = ?";
$budget_stmt = $conn->prepare($budget_query);
$budget_stmt->bind_param('ss', $term, $academic_year);
$budget_stmt->execute();
$term_budget = $budget_stmt->get_result()->fetch_assoc();

// Get budget items - ALWAYS show current assignments (same as term_budget.php view)
$income_items = [];
$expense_items = [];

// Get income from current fee assignments (active students only)
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");
while ($fee = $fees_result->fetch_assoc()) {
    $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                      FROM student_fees sf 
                      INNER JOIN students s ON sf.student_id = s.id
                      WHERE sf.fee_id = {$fee['id']} 
                      AND sf.term = '$term' 
                      AND sf.academic_year = '$academic_year'
                      AND s.status = 'active'";
    $assigned_result = $conn->query($assigned_query);
    $amount = (float)$assigned_result->fetch_assoc()['total'];
    
    if ($amount > 0) {
        $income_items[] = ['category' => $fee['name'], 'amount' => $amount];
    }
}

// Get actual income collected from payments
require_once '../includes/term_helpers.php';
$range = getTermDateRange($conn, $term, $academic_year);

// Get expenses - either from saved budget or from previous term actual spending
if ($term_budget) {
    $items_query = "SELECT * FROM term_budget_items WHERE term_budget_id = ? AND type = 'expense' ORDER BY category ASC";
    $items_stmt = $conn->prepare($items_query);
    $items_stmt->bind_param('i', $term_budget['id']);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        $expense_items[] = $item;
    }
}

// If no saved expense budget, get from previous term spending
if (empty($expense_items)) {
    $expense_cats_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
    while ($cat = $expense_cats_result->fetch_assoc()) {
        $prev_term = getPreviousTerm($term);
        $prev_year = ($prev_term === 'Third Term') ? ($academic_year - 1) : $academic_year;
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
<table width="100%" style="border-bottom: 3px solid #2c3e50; padding-bottom: 10px; margin-bottom: 10px;">
    <tr>
        <td style="text-align: center;">
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
        <td width="33%" align="right">Term Budget Report</td>
    </tr>
</table>
');

// Build PDF content
$html = '
<style>
    body { 
        font-family: "DejaVu Sans", sans-serif; 
        font-size: 10pt; 
        color: #2c3e50;
        line-height: 1.4;
    }
    h1 { 
        color: #2c3e50; 
        text-align: center; 
        margin: 0 0 5px 0; 
        font-size: 24pt;
        font-weight: bold;
        letter-spacing: 2px;
    }
    h2 { 
        color: #34495e; 
        text-align: center; 
        margin: 0 0 15px 0; 
        font-size: 14pt;
        font-weight: normal;
    }
    h3 { 
        color: white; 
        margin: 25px 0 0 0; 
        padding: 12px 15px; 
        font-size: 11pt;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .income-header { 
        background: #27ae60;
        border-left: 5px solid #229954;
    }
    .expense-header { 
        background: #e74c3c;
        border-left: 5px solid #c0392b;
    }
    
    table { 
        width: 100%; 
        border-collapse: collapse; 
        margin: 0 0 20px 0;
        border: 1px solid #bdc3c7;
    }
    th { 
        background: #ecf0f1;
        padding: 10px 12px; 
        text-align: left; 
        border-bottom: 2px solid #95a5a6; 
        font-size: 9pt;
        font-weight: bold;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    td { 
        padding: 9px 12px; 
        border-bottom: 1px solid #ecf0f1; 
        font-size: 10pt;
        color: #34495e;
    }
    tr:nth-child(even) td { 
        background-color: #f8f9fa; 
    }
    .text-end { 
        text-align: right; 
    }
    .total-row { 
        background: #27ae60 !important;
        color: white !important;
        font-weight: bold; 
        font-size: 11pt;
    }
    .total-row td { 
        border-bottom: none; 
        padding: 12px;
    }
    .expense-total-row {
        background: #e74c3c !important;
    }
    .summary-box { 
        background: #ecf0f1;
        padding: 20px; 
        border-radius: 0;
        margin-top: 25px;
        border: 2px solid #bdc3c7;
        border-left: 5px solid #3498db;
    }
    .summary-box h3 {
        background: transparent;
        color: #2c3e50;
        padding: 0 0 12px 0;
        margin: 0 0 15px 0;
        border-bottom: 2px solid #bdc3c7;
        font-size: 12pt;
        text-align: center;
        letter-spacing: 1px;
    }
    .summary-box table { 
        border: none;
        margin-bottom: 0;
        background: transparent;
    }
    .summary-box td { 
        background: transparent !important; 
        border: none !important;
        font-size: 11pt;
        padding: 8px 5px;
    }
    .summary-box tr:last-child td {
        padding-top: 15px;
        border-top: 3px solid #2c3e50 !important;
        font-size: 13pt;
        font-weight: bold;
    }
    .status-badge { 
        display: inline-block; 
        padding: 6px 20px; 
        border-radius: 3px; 
        font-size: 9pt; 
        font-weight: bold;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }
    .status-locked { 
        background: #e74c3c; 
        color: white; 
    }
    .status-draft { 
        background: #95a5a6; 
        color: white; 
    }
    .amount-cell { 
        font-family: "Courier New", monospace; 
        font-weight: 600;
        color: #2c3e50;
    }
    .category-cell {
        font-weight: 600;
        color: #34495e;
    }
    .empty-message {
        text-align: center; 
        color: #95a5a6; 
        padding: 25px;
        font-style: italic;
    }
</style>

<h1>TERM BUDGET REPORT</h1>
<h2>' . htmlspecialchars($term) . ' &bull; ' . htmlspecialchars($academic_year) . '</h2>
';

if ($term_budget) {
    $status_class = ($term_budget['status'] === 'locked') ? 'status-locked' : 'status-draft';
    $status_text = strtoupper($term_budget['status']);
    $html .= '<p style="text-align: center; margin: 0 0 20px 0;"><span class="status-badge ' . $status_class . '">' . $status_text . '</span></p>';
    
    if ($term_budget['status'] === 'locked') {
        $html .= '<p style="text-align: center; font-size: 9pt; color: #7f8c8d; margin: -15px 0 20px 0;">Locked by ' . htmlspecialchars($term_budget['locked_by']) . ' on ' . date('d M Y H:i', strtotime($term_budget['locked_at'])) . '</p>';
    }
}

// INCOME SECTION
$html .= '
<h3 class="income-header">INCOME BUDGET</h3>
<table>
    <thead>
        <tr>
            <th style="width: 65%;">Fee Category</th>
            <th class="text-end" style="width: 35%;">Budgeted Amount (GH₵)</th>
        </tr>
    </thead>
    <tbody>';

if (count($income_items) > 0) {
    foreach ($income_items as $item) {
        $html .= '
        <tr>
            <td class="category-cell">' . htmlspecialchars($item['category']) . '</td>
            <td class="text-end amount-cell">' . number_format($item['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="empty-message">No income items budgeted for this term</td></tr>';
}

$html .= '
        <tr class="total-row">
            <td>TOTAL INCOME</td>
            <td class="text-end">GH₵ ' . number_format($total_income, 2) . '</td>
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
            <th class="text-end" style="width: 35%;">Budgeted Amount (GH₵)</th>
        </tr>
    </thead>
    <tbody>';

if (count($expense_items) > 0) {
    foreach ($expense_items as $item) {
        $html .= '
        <tr>
            <td class="category-cell">' . htmlspecialchars($item['category']) . '</td>
            <td class="text-end amount-cell">' . number_format($item['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2" class="empty-message">No expense items budgeted for this term</td></tr>';
}

$html .= '
        <tr class="total-row expense-total-row">
            <td>TOTAL EXPENSES</td>
            <td class="text-end">GH₵ ' . number_format($total_expenses, 2) . '</td>
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
            <td class="text-end amount-cell" style="color: #27ae60;">GH₵ ' . number_format($total_income, 2) . '</td>
        </tr>
        <tr>
            <td style="font-weight: 600;">Total Budgeted Expenses:</td>
            <td class="text-end amount-cell" style="color: #e74c3c;">GH₵ ' . number_format($total_expenses, 2) . '</td>
        </tr>
        <tr>
            <td style="color: ' . $net_color . '; font-weight: bold;">Projected Net Balance:</td>
            <td class="text-end amount-cell" style="color: ' . $net_color . ';">GH₵ ' . number_format($net_balance, 2) . '</td>
        </tr>
    </table>
</div>';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Output PDF
$filename = 'Budget_' . str_replace(' ', '_', $term) . '_' . $academic_year . '.pdf';
$mpdf->Output($filename, 'D'); // D = download
