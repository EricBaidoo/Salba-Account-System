<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}

// Handle budget lock/unlock actions
if ((isset($_GET['action']) && $_GET['action'] === 'lock') && isset($_GET['semester']) && isset($_GET['academic_year'])) {
    $semester = $conn->real_escape_string($_GET['semester']);
    $year = $conn->real_escape_string($_GET['academic_year']);
    $user = $_SESSION['username'] ?? 'System';
    
    $conn->query("UPDATE semester_budgets 
                 SET status = 'locked', locked_at = NOW(), locked_by = '$user' 
                 WHERE semester = '$semester' AND academic_year = '$year'");
    header("Location: semester_budget.php?semester=" . urlencode($semester) . "&academic_year=" . urlencode($year) . "&locked=1");
    exit;
}

if ((isset($_GET['action']) && $_GET['action'] === 'unlock') && isset($_GET['semester']) && isset($_GET['academic_year'])) {
    $semester = $conn->real_escape_string($_GET['semester']);
    $year = $conn->real_escape_string($_GET['academic_year']);
    
    $conn->query("UPDATE semester_budgets 
                 SET status = 'draft', locked_at = NULL, locked_by = NULL 
                 WHERE semester = '$semester' AND academic_year = '$year'");
    header("Location: semester_budget.php?semester=" . urlencode($semester) . "&academic_year=" . urlencode($year) . "&unlocked=1");
    exit;
}

// Get selected semester and academic year
$system_term = getCurrentSemester($conn);
$system_academic_year = getAcademicYear($conn);

$selected_term = $_GET['semester'] ?? $system_term;
$selected_academic_year = $_GET['academic_year'] ?? $system_academic_year;
$current_term = $selected_term;
$academic_year = $selected_academic_year;

// Academic years for period picker
$yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
$all_years = [];
while ($yr = $yrs_rs->fetch_assoc()) $all_years[] = $yr['academic_year'];
if (!in_array($academic_year, $all_years)) array_unshift($all_years, $academic_year);

// Semesters from settings dictionary
$sem_rs = $conn->query("SELECT semester_name FROM academic_semester_dictionary ORDER BY id ASC");
$semesters_list = [];
while ($s = $sem_rs->fetch_assoc()) $semesters_list[] = $s['semester_name'];
if (empty($semesters_list)) $semesters_list = ['First Semester', 'Second Semester', 'Trimester'];
if (!in_array($current_term, $semesters_list)) array_unshift($semesters_list, $current_term);

// Get or create semester budget
$budget_stmt = $conn->prepare("SELECT * FROM semester_budgets WHERE semester = ? AND academic_year = ?");
$budget_stmt->bind_param("ss", $current_term, $academic_year);
$budget_stmt->execute();
$semester_budget = $budget_stmt->get_result()->fetch_assoc();
$is_locked = $semester_budget && isset($semester_budget['status']) && $semester_budget['status'] === 'locked';

// Financial Projections (Income)
$income_items = [];
$total_waivers_budget = 0;
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");
while ($fee = $fees_result->fetch_assoc()) {
    // Budgeted = Total Assigned Bills
    $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                      FROM student_fees sf 
                      INNER JOIN students s ON sf.student_id = s.id
                      WHERE sf.fee_id = {$fee['id']} 
                      AND sf.semester = '{$conn->real_escape_string($current_term)}' 
                      AND sf.academic_year = '{$conn->real_escape_string($academic_year)}'
                      AND s.status = 'active'";
    $amt = (float)$conn->query($assigned_query)->fetch_assoc()['total'];
    
    if ($fee['name'] === 'Waivers & Scholarships') {
        $total_waivers_budget = abs($amt);
        continue; // Skip adding to normal income streams
    }

    $row = ['category' => $fee['name']];
    $row['amount'] = $amt;
    
    // Actual = Payments Collected
    require_once '../../../includes/semester_helpers.php';
    $range = getSemesterDateRange($conn, $current_term, $academic_year);
    $actual_result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE fee_id = {$fee['id']} AND payment_date BETWEEN '{$range['start']}' AND '{$range['end']}'");
    $row['actual'] = (float)$actual_result->fetch_assoc()['total'];
    
    $income_items[] = $row;
}

// Expenditure Estimates
$expense_items = [];
$categories_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
while ($category = $categories_result->fetch_assoc()) {
    $row = ['category' => $category['name'], 'amount' => 0];
    
    // Load from manual budget if exists
    if ($semester_budget) {
        $budget_query = "SELECT amount FROM semester_budget_items 
                        WHERE semester_budget_id = {$semester_budget['id']} 
                        AND type = 'expense' 
                        AND category = '" . $conn->real_escape_string($category['name']) . "'";
        $res = $conn->query($budget_query);
        if ($b_row = $res->fetch_assoc()) $row['amount'] = (float)$b_row['amount'];
    }
    
    $row['actual'] = getSemesterCategorySpending($conn, $category['name'], $current_term, $academic_year);
    $expense_items[] = $row;
}

// Totals
$total_income_budget = array_sum(array_column($income_items, 'amount')); // Gross
$total_income_actual = array_sum(array_column($income_items, 'actual'));
$total_expense_budget = array_sum(array_column($expense_items, 'amount'));
$total_expense_actual = array_sum(array_column($expense_items, 'actual'));

$net_budgeted = ($total_income_budget - $total_waivers_budget) - $total_expense_budget;
$net_actual = $total_income_actual - $total_expense_actual;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Budget | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .gradient-income { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .gradient-expense { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); }
        @media print {
            .no-print { display: none !important; }
            .ml-72 { margin-left: 0 !important; }
            .p-10 { padding: 1.5rem !important; }
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <div class="no-print"><?php include '../../../includes/sidebar_admin_modern.php'; ?></div>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <!-- Header Section -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 no-print">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                    <a href="../dashboard.php" class="hover:text-purple-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                    <span>/</span>
                    <span class="text-purple-600">Budgets</span>
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($current_term) ?> <span class="text-purple-600">Budget</span></h1>
                <p class="text-slate-500 mt-2 font-medium"><?= htmlspecialchars($current_term) ?> | <?= htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)) ?></p>
            </div>
            <div class="flex items-center gap-3">
                <!-- Period Picker -->
                <form method="GET" action="semester_budget.php" class="flex items-center gap-2">
                    <select name="semester" onchange="this.form.submit()" class="text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer">
                        <?php foreach ($semesters_list as $s): ?>
                            <option value="<?= htmlspecialchars($s) ?>" <?= $s === $current_term ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="academic_year" onchange="this.form.submit()" class="text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-purple-500 cursor-pointer">
                        <?php foreach ($all_years as $yr): ?>
                            <option value="<?= htmlspecialchars($yr) ?>" <?= $yr === $academic_year ? 'selected' : '' ?>><?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                 <?php if (!$is_locked): ?>
                    <a href="edit_semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="bg-purple-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-purple-600/20 hover:bg-purple-700 transition-all leading-none">
                        <i class="fas fa-edit mr-2"></i> Edit Budget
                    </a>
                    <?php if ($semester_budget): ?>
                        <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>&action=lock" class="bg-white text-slate-600 border border-slate-200 font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none" onclick="return confirm('Lock this budget? No further changes will be allowed until unlocked.');">
                            <i class="fas fa-lock mr-2"></i> Lock Budget
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-slate-900 text-white px-6 py-4 rounded-2xl flex items-center gap-3 shadow-xl">
                        <i class="fas fa-shield-halved text-emerald-400"></i>
                         <div class="flex flex-col">
                            <span class="text-[0.5625rem] font-black uppercase tracking-widest text-slate-500 leading-none mb-1">Budget Locked</span>
                            <span class="text-[0.625rem] font-bold"><?= htmlspecialchars($semester_budget['locked_by']) ?></span>
                         </div>
                         <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>&action=unlock" class="ml-4 w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-all" title="Request Unlock">
                            <i class="fas fa-lock-open text-xs"></i>
                         </a>
                    </div>
                <?php endif; ?>
                <a href="download_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="w-12 h-12 bg-white border border-slate-100 rounded-2xl flex items-center justify-center text-slate-400 hover:text-purple-600 transition-all shadow-sm" title="Download Budget PDF">
                    <i class="fas fa-file-pdf"></i>
                </a>
            </div>
        </header>

        <!-- Dynamic Feedback -->
        <?php if (isset($_GET['locked'])): ?>
            <div class="mb-8 p-6 bg-emerald-50 border border-emerald-100 rounded-[2rem] flex items-center gap-4 text-emerald-700 no-print">
                <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center text-xl shadow-lg shadow-emerald-500/20">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h5 class="font-black text-sm uppercase tracking-tight">Budget Locked</h5>
                    <p class="text-xs font-semibold opacity-80">Budget has been locked for the current semester.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <?php
            $net_income_after_waivers = $total_income_budget - $total_waivers_budget;
            $income_pct  = $net_income_after_waivers > 0 ? min(100, round(($total_income_actual / $net_income_after_waivers) * 100)) : 0;
            $expense_pct = $total_expense_budget > 0 ? min(100, round(($total_expense_actual / $total_expense_budget) * 100)) : 0;
            $is_surplus  = $net_budgeted >= 0;
            $is_healthy  = $total_expense_actual <= $total_expense_budget && $net_budgeted >= 0;
        ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-10">

            <!-- Card 1: Income -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fas fa-coins text-sm"></i>
                    </div>
                    <span class="text-[0.5rem] font-black text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-1 rounded-lg uppercase tracking-wide">Income</span>
                </div>
                <div>
                    <p class="text-[0.5625rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Income Budgeted</p>
                    <h2 class="text-2xl font-black text-slate-900">GH₵ <?= number_format($total_income_budget, 2) ?></h2>
                </div>
                <div>
                    <div class="flex justify-between text-[0.5625rem] font-bold text-slate-500 mb-1.5">
                        <span>Collected so far</span>
                        <span class="text-emerald-600 font-black"><?= $income_pct ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-emerald-500 h-2 rounded-full transition-all" style="width:<?= $income_pct ?>%"></div>
                    </div>
                    <p class="text-[0.5625rem] font-bold text-slate-400 mt-1.5">GH₵ <?= number_format($total_income_actual, 2) ?> received</p>
                </div>
            </div>

            <!-- Card 2: Waivers -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fas fa-hand-holding-heart text-sm"></i>
                    </div>
                    <span class="text-[0.5rem] font-black text-amber-600 bg-amber-50 border border-amber-100 px-2 py-1 rounded-lg uppercase tracking-wide">Deductions</span>
                </div>
                <div>
                    <p class="text-[0.5625rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Waivers &amp; Scholarships</p>
                    <h2 class="text-2xl font-black text-slate-900">(GH₵ <?= number_format($total_waivers_budget, 2) ?>)</h2>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3">
                    <p class="text-[0.5rem] font-black text-amber-500 uppercase tracking-widest mb-0.5">Net Income After Waivers</p>
                    <p class="text-sm font-black text-amber-800">GH₵ <?= number_format($net_income_after_waivers, 2) ?></p>
                </div>
            </div>

            <!-- Card 3: Expenses -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fas fa-receipt text-sm"></i>
                    </div>
                    <span class="text-[0.5rem] font-black text-rose-600 bg-rose-50 border border-rose-100 px-2 py-1 rounded-lg uppercase tracking-wide">Expenses</span>
                </div>
                <div>
                    <p class="text-[0.5625rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Expenses Budgeted</p>
                    <h2 class="text-2xl font-black text-slate-900">GH₵ <?= number_format($total_expense_budget, 2) ?></h2>
                </div>
                <div>
                    <div class="flex justify-between text-[0.5625rem] font-bold text-slate-500 mb-1.5">
                        <span>Spent so far</span>
                        <span class="font-black <?= $expense_pct > 90 ? 'text-rose-600' : 'text-slate-500' ?>"><?= $expense_pct ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="<?= $expense_pct > 90 ? 'bg-rose-500' : 'bg-indigo-500' ?> h-2 rounded-full transition-all" style="width:<?= $expense_pct ?>%"></div>
                    </div>
                    <p class="text-[0.5625rem] font-bold text-slate-400 mt-1.5">GH₵ <?= number_format($total_expense_actual, 2) ?> spent</p>
                </div>
            </div>

            <!-- Card 4: Net Budget -->
            <div class="<?= $is_surplus ? 'bg-emerald-800' : 'bg-rose-800' ?> rounded-2xl shadow-xl text-white p-6 flex flex-col gap-4">
                <div class="flex items-start justify-between">
                    <div class="w-10 h-10 <?= $is_surplus ? 'bg-emerald-700 text-emerald-200' : 'bg-rose-700 text-rose-200' ?> rounded-xl flex items-center justify-center shrink-0">
                        <i class="fas <?= $is_surplus ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?> text-sm"></i>
                    </div>
                    <span class="text-[0.5rem] font-black <?= $is_surplus ? 'text-emerald-300 bg-emerald-700 border-emerald-600' : 'text-rose-300 bg-rose-700 border-rose-600' ?> border px-2 py-1 rounded-lg uppercase tracking-wide">
                        <?= $is_surplus ? 'Surplus' : 'Deficit' ?>
                    </span>
                </div>
                <div>
                    <p class="text-[0.5625rem] font-bold text-white/50 uppercase tracking-widest mb-1">Net Budget</p>
                    <h2 class="text-2xl font-black text-white">GH₵ <?= number_format(abs($net_budgeted), 2) ?></h2>
                    <p class="text-[0.5625rem] font-bold text-white/40 mt-0.5">Income minus planned expenses</p>
                </div>
                <div class="flex items-center gap-2.5 bg-white/10 rounded-xl px-3 py-2.5 mt-auto">
                    <i class="fas <?= $is_healthy ? 'fa-circle-check text-emerald-300' : 'fa-triangle-exclamation text-amber-300' ?> text-sm"></i>
                    <div>
                        <p class="text-[0.5rem] font-black text-white/40 uppercase tracking-widest leading-none mb-0.5">Budget Health</p>
                        <p class="text-[0.625rem] font-black text-white"><?= $is_healthy ? 'On Track' : 'Needs Attention' ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytical Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Income Ledger -->
            <div>
                <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
                    Income Summary <span class="flex-1 h-[0.0625rem] bg-slate-100"></span>
                </h3>
                <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-left text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Fee Category</th>
                                <th class="px-8 py-5 text-right text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Budgeted Amount (GHS)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($income_items as $item): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5 text-sm font-bold text-slate-800"><?= htmlspecialchars($item['category']) ?></td>
                                <td class="px-8 py-5 text-right font-black text-slate-900"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-emerald-50/30">
                                <td class="px-8 py-6 text-[0.625rem] font-black text-emerald-700 uppercase tracking-widest">Total Income</td>
                                <td class="px-8 py-6 text-right text-lg font-black text-emerald-600">GHS <?= number_format($total_income_budget, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Expense Ledger -->
            <div>
                <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
                    Expense Budget <span class="flex-1 h-[0.0625rem] bg-slate-100"></span>
                </h3>
                <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-left text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Expense Category</th>
                                <th class="px-4 py-5 text-center text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Items</th>
                                <th class="px-8 py-5 text-right text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest">Budgeted Amount (GHS)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($expense_items as $item):
                                // Count sub-items
                                $item_count = 0;
                                if (isset($semester_budget['id'])) {
                                    $cat_esc2 = $conn->real_escape_string($item['category']);
                                    $bi2 = $conn->query("SELECT id FROM semester_budget_items WHERE semester_budget_id={$semester_budget['id']} AND category='$cat_esc2' AND type='expense'")->fetch_assoc();
                                    if ($bi2) $item_count = (int)$conn->query("SELECT COUNT(*) as c FROM semester_budget_item_sources WHERE budget_item_id={$bi2['id']}")->fetch_assoc()['c'];
                                }
                                $detail_url = 'budget_category_detail.php?semester=' . urlencode($current_term) . '&academic_year=' . urlencode($academic_year) . '&category=' . urlencode($item['category']);
                            ?>
                            <tr class="hover:bg-rose-50/30 transition-colors group">
                                <td class="px-8 py-5">
                                    <a href="<?= $detail_url ?>" class="flex items-center gap-3 text-sm font-bold text-slate-800 hover:text-rose-600 transition-colors">
                                        <?= htmlspecialchars($item['category']) ?>
                                        <i class="fas fa-arrow-right text-[0.5rem] text-slate-300 group-hover:text-rose-500 transition-colors"></i>
                                    </a>
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <?php if ($item_count > 0): ?>
                                        <span class="text-[0.5rem] font-black text-rose-600 bg-rose-50 border border-rose-100 px-2 py-0.5 rounded-full"><?= $item_count ?> item<?= $item_count != 1 ? 's' : '' ?></span>
                                    <?php else: ?>
                                        <span class="text-[0.5rem] font-black text-slate-300 uppercase">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-8 py-5 text-right font-black text-slate-900"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-rose-50/30">
                                <td class="px-8 py-6 text-[0.625rem] font-black text-rose-700 uppercase tracking-widest">Total Expenses</td>
                                <td class="px-8 py-6 text-right text-lg font-black text-rose-600">GHS <?= number_format($total_expense_budget, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer Audit -->
        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            <span>Budget &middot; Salba Montessori &middot; v9.5.0</span>
            <div class="flex gap-6">
                <a href="../dashboard.php" class="hover:text-purple-600 transition-colors">Dashboard Control</a>
                <a href="../reports/report.php" class="hover:text-purple-600 transition-colors">Detailed Analytics</a>
            </div>
        </footer>
    </main>
</body>
</html>
