<?php
require_once '../../../vendor/autoload.php';
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
$income_items   = [];
$expense_items  = [];
$total_waivers  = 0;

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

    if ($fee['name'] === 'Waivers & Scholarships') {
        $total_waivers = abs($amount); // capture separately, don't add to income
        continue;
    }

    if ($amount > 0) {
        $income_items[] = ['category' => $fee['name'], 'amount' => $amount];
    }
}

// Get actual income collected from payments
require_once '../../../includes/semester_helpers.php';
$range = getSemesterDateRange($conn, $semester, $academic_year);

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
        $prev_term = getPreviousSemester($semester);
        $prev_year = ($prev_term === 'Third Semester') ? ($academic_year - 1) : $academic_year;
        $amount = getSemesterCategorySpending($conn, $cat['id'], $prev_term, $prev_year);
        
        if ($amount > 0) {
            $expense_items[] = ['category' => $cat['name'], 'amount' => $amount];
        }
    }
}

// Calculate totals
$total_income   = array_sum(array_column($income_items, 'amount'));
$total_expenses = array_sum(array_column($expense_items, 'amount'));
$net_income     = $total_income - $total_waivers;  // after deducting waivers
$net_balance    = $net_income - $total_expenses;

// Initialize mPDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 18,
    'margin_right' => 18,
    'margin_top' => 15,
    'margin_bottom' => 15,
    'margin_header' => 0,
    'margin_footer' => 0
]);

// Set header
$school_name    = getSystemSetting($conn, 'school_name')    ?? 'School Management System';
$school_address = getSystemSetting($conn, 'school_address') ?? '';
$school_phone   = getSystemSetting($conn, 'school_phone')   ?? '';
$school_email   = getSystemSetting($conn, 'school_email')   ?? '';

// ── CSS stylesheet (no inline styles anywhere) ──────────────────────────────
$css = '
body            { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1a1a2e; }

h1              { font-size: 17pt; font-weight: bold; text-align: center;
                  color: #1a1a2e; margin: 0 0 2pt 0; letter-spacing: 1pt; }
h2.subtitle     { font-size: 11pt; font-weight: normal; text-align: center;
                  color: #6c757d; margin: 0 0 14pt 0; }
h3.section-head { font-size: 9pt; font-weight: bold; text-transform: uppercase;
                  letter-spacing: 1pt; padding: 6pt 8pt; margin: 18pt 0 0 0;
                  border-radius: 3pt; }
h3.income-head  { background: #e8f5e9; color: #1b5e20; border-left: 4pt solid #2e7d32; }
h3.expense-head { background: #fce4ec; color: #880e4f; border-left: 4pt solid #c2185b; }
h3.summary-head { background: #e8eaf6; color: #1a237e; border-left: 4pt solid #3949ab; }
h3.waiver-head  { background: #fff8e1; color: #e65100; border-left: 4pt solid #ff8f00; }
td.waiver-amt   { text-align: right; color: #bf360c; font-weight: bold; }
td.net-income   { font-weight: bold; border-top: 1pt solid #90a4ae; }
td.net-income-amt { text-align: right; font-weight: bold; border-top: 1pt solid #90a4ae; }

table           { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
th              { font-size: 8.5pt; font-weight: bold; padding: 6pt 8pt;
                  background: #37474f; color: #ffffff; text-align: left; }
th.right        { text-align: right; }
td              { padding: 5pt 8pt; border-bottom: 0.5pt solid #e0e0e0; font-size: 9pt; }
td.right        { text-align: right; }
td.indent       { padding-left: 20pt; font-size: 8pt; color: #616161; }
tr.alt          { background: #f9f9f9; }
tr.total        { background: #eceff1; font-weight: bold; border-top: 1pt solid #90a4ae; }
td.total-label  { font-weight: bold; font-size: 9pt; }
td.net-positive { font-weight: bold; color: #1b5e20; font-size: 10pt; }
td.net-negative { font-weight: bold; color: #b71c1c; font-size: 10pt; }

.divider        { border: none; border-top: 0.75pt solid #cfd8dc; margin: 10pt 0; }

/* header */
.hdr-wrap       { background: #1a237e; padding: 8pt 10pt; }
.hdr-logo       { width: 38pt; height: 38pt; }
.hdr-name       { font-size: 13pt; font-weight: bold; color: #ffffff; vertical-align: middle; padding-left: 8pt; }
.hdr-tagline    { font-size: 7pt; color: #9fa8da; padding-left: 8pt; }
.hdr-contact    { font-size: 7pt; color: #9fa8da; text-align: right; vertical-align: middle; }
/* footer */
.ftr            { font-size: 7.5pt; color: #9e9e9e; }
.ftr-center     { text-align: center; }
.ftr-right      { text-align: right; }

/* status badge */
.badge          { font-size: 7.5pt; font-weight: bold; padding: 2pt 7pt;
                  border-radius: 3pt; text-transform: uppercase; }
.badge-draft    { background: #fff3e0; color: #e65100; }
.badge-locked   { background: #e8f5e9; color: #2e7d32; }
';

$mpdf->WriteHTML($css, 1); // 1 = stylesheet only

// ── Logo path ────────────────────────────────────────────────────────────────
$logo_path = __DIR__ . '/../../../assets/img/salba_logo.jpg';
$logo_tag  = file_exists($logo_path)
    ? '<img src="' . $logo_path . '" class="hdr-logo">'
    : '';

// ── Build HTML body ───────────────────────────────────────────────────────────
// School header block (first page only — lives in body, not SetHTMLHeader)
$html = '
<table width="100%" style="background:#1a237e; padding:10pt 12pt; border-bottom:3pt solid #ff8f00; margin-bottom:16pt;">
    <tr>
        <td width="48pt" style="vertical-align:middle;">' . $logo_tag . '</td>
        <td style="vertical-align:middle; padding-left:10pt;">
            <div class="hdr-name">' . htmlspecialchars($school_name) . '</div>
            <div class="hdr-tagline">' . htmlspecialchars($school_address) . '</div>
        </td>
        <td class="hdr-contact">' . htmlspecialchars($school_phone) . '<br>' . htmlspecialchars($school_email) . '</td>
    </tr>
</table>

<table width="100%" style="margin-bottom:14pt; border-bottom:2pt solid #e8eaf6;">
    <tr>
        <td style="padding:4pt 0 10pt 0;">
            <p style="font-size:6pt; font-weight:bold; color:#9fa8da; letter-spacing:2pt;
                      text-transform:uppercase; margin:0 0 3pt 0;">Financial Document</p>
            <p style="font-size:18pt; font-weight:bold; color:#1a237e;
                      margin:0 0 3pt 0; letter-spacing:0.5pt;">Semester Budget Report</p>
            <p style="font-size:10pt; color:#607d8b; margin:0;">' . htmlspecialchars($semester) . ' &nbsp;&bull;&nbsp; ' . htmlspecialchars($academic_year) . '</p>
        </td>
        <td width="110pt" style="text-align:right; vertical-align:bottom; padding-bottom:10pt;">
            <p style="font-size:7pt; color:#9e9e9e; margin:0;">Prepared on</p>
            <p style="font-size:9pt; font-weight:bold; color:#37474f; margin:0;">' . date('d M Y') . '</p>
        </td>
    </tr>
</table>
';

if ($semester_budget && isset($semester_budget['status'])) {
    $badge_class = ($semester_budget['status'] === 'locked') ? 'badge-locked' : 'badge-draft';
    $html .= '<p class="text-center"><span class="badge ' . $badge_class . '">' . strtoupper($semester_budget['status']) . '</span></p>';
}

// INCOME SECTION
$html .= '
<h3 class="section-head income-head">Income Budget</h3>
<table>
    <thead>
        <tr>
            <th style="width:65%">Fee Category</th>
            <th class="right" style="width:35%">Budgeted Amount (GH&#8373;)</th>
        </tr>
    </thead>
    <tbody>';

if (count($income_items) > 0) {
    foreach ($income_items as $i => $item) {
        $row_class = ($i % 2 === 1) ? ' class="alt"' : '';
        $html .= '
        <tr' . $row_class . '>
            <td>' . htmlspecialchars($item['category']) . '</td>
            <td class="right">' . number_format($item['amount'], 2) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="2">No income items budgeted for this semester.</td></tr>';
}

$html .= '
        <tr class="total">
            <td class="total-label">TOTAL INCOME</td>
            <td class="right total-label">GH&#8373; ' . number_format($total_income, 2) . '</td>
        </tr>
    </tbody>
</table>';

// WAIVERS DEDUCTION
$html .= '
<h3 class="section-head waiver-head">Waivers &amp; Scholarships</h3>
<table>
    <tbody>
        <tr>
            <td style="width:65%">Fee Waivers &amp; Scholarship Deductions</td>
            <td class="waiver-amt" style="width:35%">(GH&#8373; ' . number_format($total_waivers, 2) . ')</td>
        </tr>
        <tr class="total">
            <td class="net-income">Net Income After Waivers</td>
            <td class="net-income-amt">GH&#8373; ' . number_format($net_income, 2) . '</td>
        </tr>
    </tbody>
</table>';

// EXPENSE SECTION — with sub-item breakdown
$html .= '
<h3 class="section-head expense-head">Expense Budget</h3>
<table>
    <thead>
        <tr>
            <th style="width:65%">Expense Category / Item</th>
            <th class="right" style="width:35%">Budgeted Amount (GH&#8373;)</th>
        </tr>
    </thead>
    <tbody>';

if (count($expense_items) > 0) {
    $row_idx = 0;
    foreach ($expense_items as $item) {
        $row_class = ($row_idx % 2 === 1) ? ' class="alt"' : '';
        $html .= '
        <tr' . $row_class . '>
            <td><strong>' . htmlspecialchars($item['category']) . '</strong></td>
            <td class="right"><strong>' . number_format($item['amount'], 2) . '</strong></td>
        </tr>';
        $row_idx++;

        // Sub-items
        $item_id_for_sources = isset($item['id']) ? (int)$item['id'] : 0;
        if (!$item_id_for_sources && $semester_budget) {
            $cat_esc_pdf = $conn->real_escape_string($item['category']);
            $bi_row = $conn->query("SELECT id FROM semester_budget_items WHERE semester_budget_id={$semester_budget['id']} AND category='$cat_esc_pdf' AND type='expense'")->fetch_assoc();
            $item_id_for_sources = $bi_row ? (int)$bi_row['id'] : 0;
        }
        if ($item_id_for_sources) {
            $sub_res = $conn->query("SELECT source, amount FROM semester_budget_item_sources WHERE budget_item_id=$item_id_for_sources ORDER BY id ASC");
            while ($sub = $sub_res->fetch_assoc()) {
                $html .= '
        <tr>
            <td class="indent">&rsaquo; ' . htmlspecialchars($sub['source']) . '</td>
            <td class="right indent">' . number_format($sub['amount'], 2) . '</td>
        </tr>';
                $row_idx++;
            }
        }
    }
} else {
    $html .= '<tr><td colspan="2">No expense items budgeted for this semester.</td></tr>';
}

$html .= '
        <tr class="total">
            <td class="total-label">TOTAL EXPENSES</td>
            <td class="right total-label">GH&#8373; ' . number_format($total_expenses, 2) . '</td>
        </tr>
    </tbody>
</table>';
// SUMMARY SECTION
$net_class = ($net_balance >= 0) ? 'net-positive' : 'net-negative';
$html .= '
<h3 class="section-head summary-head">Budget Summary</h3>
<table>
    <tbody>
        <tr>
            <td style="width:65%">Total Expected Income</td>
            <td class="right" style="width:35%">GH&#8373; ' . number_format($total_income, 2) . '</td>
        </tr>
        <tr class="alt">
            <td>Less: Waivers &amp; Scholarships</td>
            <td class="waiver-amt">(GH&#8373; ' . number_format($total_waivers, 2) . ')</td>
        </tr>
        <tr>
            <td>Net Income After Waivers</td>
            <td class="right">GH&#8373; ' . number_format($net_income, 2) . '</td>
        </tr>
        <tr class="alt">
            <td>Total Budgeted Expenses</td>
            <td class="right">GH&#8373; ' . number_format($total_expenses, 2) . '</td>
        </tr>
        <tr class="total">
            <td class="' . $net_class . '">Net Budget (Surplus / Deficit)</td>
            <td class="right ' . $net_class . '">GH&#8373; ' . number_format($net_balance, 2) . '</td>
        </tr>
    </tbody>
</table>';

// Write HTML to PDF
$mpdf->WriteHTML($html, 2); // 2 = HTML body only (CSS already loaded)

// Output PDF
$filename = 'Budget_' . str_replace(' ', '_', $semester) . '_' . $academic_year . '.pdf';
$mpdf->Output($filename, 'D');
