<?php
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';
include '../../../includes/budget_functions.php';

// System defaults
$default_academic_year = getSystemSetting($conn, 'academic_year', date('Y') . '/' . (date('Y') + 1));
$default_term = getCurrentSemester($conn);

// Filters
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$selected_term = $_GET['semester'] ?? 'All';
$report_type = $_GET['report_type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

// Build options
$year_options = [];
$yrs1 = $conn->query("SELECT DISTINCT academic_year FROM student_fees ORDER BY academic_year DESC");
while ($yr = $yrs1->fetch_assoc()) if ($yr['academic_year']) $year_options[] = $yr['academic_year'];
$yrs2 = $conn->query("SELECT DISTINCT academic_year FROM payments ORDER BY academic_year DESC");
while ($yr = $yrs2->fetch_assoc()) if ($yr['academic_year'] && !in_array($yr['academic_year'], $year_options)) $year_options[] = $yr['academic_year'];
if (!in_array($default_academic_year, $year_options)) array_unshift($year_options, $default_academic_year);

$available_terms = getAvailableSemesters($conn);

// Logic and Data Fetching
$total_income = 0; $total_expenses = 0;
$income_by_category = []; $expense_by_category = [];

if ($report_type === 'overview' || $report_type === 'income' || $report_type === 'expenses') {
    // Total Income
    $income_total_stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        WHERE p.academic_year = ? 
        " . ($selected_term !== 'All' ? "AND p.semester = ? " : "") . "
    ");
    if ($selected_term !== 'All') $income_total_stmt->bind_param('ss', $selected_academic_year, $selected_term);
    else $income_total_stmt->bind_param('s', $selected_academic_year);
    $income_total_stmt->execute();
    $total_income = (float)$income_total_stmt->get_result()->fetch_assoc()['total'];

    // Income categories
    $filter_term = ($selected_term !== 'All');
    $income_union_sql = "
        (SELECT f.name AS category, SUM(pa.amount) AS total FROM payment_allocations pa JOIN student_fees sf ON pa.student_fee_id = sf.id JOIN payments p ON pa.payment_id = p.id JOIN fees f ON sf.fee_id = f.id WHERE p.academic_year = ? ".($filter_term?"AND p.semester = ? ":"")." GROUP BY f.id)
        UNION ALL
        (SELECT CONCAT(f.name, ' (General)') AS category, SUM(p.amount) AS total FROM payments p JOIN fees f ON p.fee_id = f.id WHERE p.payment_type = 'general' AND p.academic_year = ? ".($filter_term?"AND p.semester = ? ":"")." GROUP BY f.id)
        ORDER BY total DESC";
    $inc_stmt = $conn->prepare($income_union_sql);
    if($filter_term) $inc_stmt->bind_param('ssss', $selected_academic_year, $selected_term, $selected_academic_year, $selected_term);
    else $inc_stmt->bind_param('ss', $selected_academic_year, $selected_academic_year);
    $inc_stmt->execute(); $inc_res = $inc_stmt->get_result();
    while($r = $inc_res->fetch_assoc()) if($r['total']>0) $income_by_category[] = $r;

    // Expenses
    $exp_stmt = $conn->prepare("SELECT ec.name AS category, SUM(e.amount) AS total FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.academic_year = ? " . ($selected_term !== 'All' ? "AND e.semester = ? " : "") . " GROUP BY e.category_id ORDER BY total DESC");
    if($selected_term !== 'All') $exp_stmt->bind_param('ss', $selected_academic_year, $selected_term);
    else $exp_stmt->bind_param('s', $selected_academic_year);
    $exp_stmt->execute(); $exp_res = $exp_stmt->get_result();
    while($r = $exp_res->fetch_assoc()) { $expense_by_category[] = $r; $total_expenses += $r['total']; }

    // Fetch Total Waivers Given
    $waivers_stmt = $conn->prepare("
        SELECT COALESCE(SUM(sf.amount), 0) as total 
        FROM student_fees sf
        JOIN fees f ON sf.fee_id = f.id
        WHERE f.name = 'Waivers & Scholarships'
        AND sf.academic_year = ? 
        " . ($selected_term !== 'All' ? "AND sf.semester = ? " : "") . "
    ");
    if($selected_term !== 'All') $waivers_stmt->bind_param('ss', $selected_academic_year, $selected_term);
    else $waivers_stmt->bind_param('s', $selected_academic_year);
    $waivers_stmt->execute();
    $total_waivers = abs((float)$waivers_stmt->get_result()->fetch_assoc()['total']);
}

$budget_comparison = [];
if ($report_type === 'budget' || $report_type === 'overview') {
    $terms = ($selected_term === 'All') ? $available_terms : [$selected_term];
    foreach($terms as $term) {
        $sb = $conn->query("SELECT id FROM semester_budgets WHERE semester = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc();
        if($sb) {
             $e_bud = (float)$conn->query("SELECT SUM(amount) as t FROM semester_budget_items WHERE semester_budget_id = {$sb['id']} AND type = 'expense'")->fetch_assoc()['t'];
             $e_act = (float)$conn->query("SELECT SUM(amount) as t FROM expenses WHERE semester = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc()['t'];
             $i_act = (float)$conn->query("SELECT SUM(amount) as t FROM payments WHERE semester = '$term' AND academic_year = '$selected_academic_year'")->fetch_assoc()['t'];
             $budget_comparison[$term] = ['e_bud'=>$e_bud, 'e_act'=>$e_act, 'i_act'=>$i_act];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Intelligence Hub | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .nav-pill { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .nav-pill.active { background: #10b981; color: white; box-shadow: 0 10px 25px rgba(16, 185, 129, 0.2); }
        @media print { .no-print { display: none !important; } .ml-72 { margin-left: 0 !important; } .p-10 { padding: 20px !important; } }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider no-print">
                    <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                    <span>/</span>
                    <span class="text-indigo-600">Financial Analytics</span>
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Financial <span class="text-indigo-600">Analytics</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Multi-dimensional reporting for institutional fiscal oversight.</p>
            </div>
            <div class="no-print flex gap-4">
                 <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none shadow-sm">
                    <i class="fas fa-print mr-2"></i> Print Report
                 </button>
            </div>
        </header>

        <!-- Navigation Context -->
        <nav class="no-print flex flex-wrap gap-2 mb-12 bg-white p-2 rounded-[2rem] border border-slate-100 shadow-sm w-fit">
            <?php 
            $navs = [
                'overview' => ['Overview', 'chart-pie'],
                'income' => ['Income', 'coins'],
                'expenses' => ['Expenses', 'receipt'],
                'budget' => ['Budget vs Actual', 'calculator'],
                'student_fees' => ['Collections Summary', 'users'],
                'transactions' => ['Transactions', 'list-check']
            ];
            foreach($navs as $type => $info): ?>
                <a href="?report_type=<?= $type ?>&semester=<?= $selected_term ?>&academic_year=<?= $selected_academic_year ?>" 
                   class="nav-pill px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center gap-3 <?= $report_type===$type?'active':'text-slate-500 hover:bg-slate-50' ?>">
                    <i class="fas fa-<?= $info[1] ?> text-xs"></i> <?= $info[0] ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Filter Console -->
        <section class="no-print bg-white rounded-[2rem] p-8 border border-slate-100 shadow-sm mb-12">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <input type="hidden" name="report_type" value="<?= $report_type ?>">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Fiscal Year</label>
                    <select name="academic_year" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                        <?php foreach($year_options as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Trimester Context</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                        <option value="All">All Trimesters</option>
                        <?php foreach($available_terms as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $selected_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Date Scope (Optional)</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="<?= $date_from ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-3 text-[10px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                        <input type="date" name="date_to" value="<?= $date_to ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-3 text-[10px] font-bold outline-none focus:ring-2 focus:ring-indigo-500 text-slate-700">
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest px-4 py-4 rounded-xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all leading-none">Apply Scopes</button>
            </form>
        </section>

        <!-- Report Content -->
        <?php if($report_type === 'overview'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Summary Metrics -->
                <div class="lg:col-span-12 grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="bg-emerald-500 p-8 rounded-[2rem] shadow-xl shadow-emerald-500/20 text-white flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 text-emerald-400/30 text-7xl"><i class="fas fa-arrow-trend-up"></i></div>
                        <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest mb-2 relative z-10">Aggregate Income</p>
                        <h3 class="text-3xl font-black italic relative z-10">₵<?= number_format($total_income, 2) ?></h3>
                    </div>
                    <div class="bg-rose-500 p-8 rounded-[2rem] shadow-xl shadow-rose-500/20 text-white flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 text-rose-400/30 text-7xl"><i class="fas fa-arrow-trend-down"></i></div>
                        <p class="text-[10px] font-black text-rose-100 uppercase tracking-widest mb-2 relative z-10">Aggregate Expenditure</p>
                        <h3 class="text-3xl font-black italic relative z-10">₵<?= number_format($total_expenses, 2) ?></h3>
                    </div>
                    <div class="bg-pink-500 p-8 rounded-[2rem] shadow-xl shadow-pink-500/20 text-white flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 text-pink-400/30 text-7xl"><i class="fas fa-hand-holding-heart"></i></div>
                        <p class="text-[10px] font-black text-pink-100 uppercase tracking-widest mb-2 relative z-10">Waivers Granted</p>
                        <h3 class="text-3xl font-black italic relative z-10">₵<?= number_format($total_waivers, 2) ?></h3>
                    </div>
                    <div class="bg-indigo-900 p-8 rounded-[2rem] shadow-xl shadow-indigo-900/20 text-white flex flex-col justify-between relative overflow-hidden">
                        <div class="absolute -right-6 -top-6 text-indigo-800/50 text-7xl"><i class="fas fa-scale-balanced"></i></div>
                        <p class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-2 relative z-10">Net Liquidity</p>
                        <h3 class="text-3xl font-black italic relative z-10">₵<?= number_format($total_income - $total_expenses, 2) ?></h3>
                    </div>
                </div>

                <!-- Category Matrix -->
                <div class="lg:col-span-6 bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><i class="fas fa-chart-pie mr-2 text-emerald-500"></i> Income Breakdown</h4>
                    </div>
                    <div class="p-8 space-y-6">
                        <?php foreach($income_by_category as $row): 
                            $pct = $total_income > 0 ? ($row['total']/$total_income)*100 : 0;
                        ?>
                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <p class="text-xs font-bold text-slate-700 uppercase leading-none"><?= htmlspecialchars($row['category']) ?></p>
                                    <p class="text-xs font-black text-emerald-600">₵<?= number_format($row['total'], 2) ?></p>
                                </div>
                                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden flex items-center">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($income_by_category)): ?>
                            <p class="text-center text-slate-400 text-sm italic py-8">No income data available for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lg:col-span-6 bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><i class="fas fa-chart-pie mr-2 text-rose-500"></i> Expenditure Breakdown</h4>
                    </div>
                    <div class="p-8 space-y-6">
                        <?php foreach($expense_by_category as $row): 
                            $pct = $total_expenses > 0 ? ($row['total']/$total_expenses)*100 : 0;
                        ?>
                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <p class="text-xs font-bold text-slate-700 uppercase leading-none"><?= htmlspecialchars($row['category']) ?></p>
                                    <p class="text-xs font-black text-rose-600">₵<?= number_format($row['total'], 2) ?></p>
                                </div>
                                <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden flex items-center">
                                    <div class="h-full bg-rose-500 rounded-full" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($expense_by_category)): ?>
                            <p class="text-center text-slate-400 text-sm italic py-8">No expenditure data available for this period.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Budget Overview (Condensed) -->
                <?php if(!empty($budget_comparison)): ?>
                <div class="lg:col-span-12 bg-slate-900 rounded-[2rem] p-8 md:p-10 text-white shadow-xl shadow-slate-900/10">
                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                        <i class="fas fa-bullseye text-indigo-400"></i> Budget Performance Benchmarks
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach($budget_comparison as $term => $data): 
                            $e_pct = $data['e_bud']>0 ? ($data['e_act']/$data['e_bud'])*100 : 0;
                        ?>
                            <div class="bg-slate-800/50 rounded-2xl p-6 border border-slate-700">
                                <h5 class="text-lg font-black tracking-tight text-white mb-6"><?= htmlspecialchars($term) ?></h5>
                                <div class="flex justify-between py-3 border-b border-slate-700">
                                    <span class="text-[10px] font-black text-slate-400 uppercase">Expense Consumption</span>
                                    <span class="text-[10px] font-black <?= $e_pct>100?'text-rose-400':'text-emerald-400' ?>"><?= round($e_pct) ?>% Utilized</span>
                                </div>
                                <div class="flex justify-between py-3 border-b border-slate-700">
                                    <span class="text-[10px] font-black text-slate-400 uppercase">Actual Revenue</span>
                                    <span class="text-xs font-black text-indigo-400">₵<?= number_format($data['i_act'], 2) ?></span>
                                </div>
                                <div class="flex justify-between py-3 pt-4 mt-2 border-t border-slate-600">
                                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Realized Net</span>
                                    <span class="text-sm font-black text-white">₵<?= number_format($data['i_act'] - $data['e_act'], 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif($report_type === 'income' || $report_type === 'expenses'): ?>
            <!-- Detailed Table View -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><?= $navs[$report_type][0] ?> Summary Table</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-8 py-4">Category / Item</th>
                                <th class="px-8 py-4 text-right">Amount (₵)</th>
                                <th class="px-8 py-4 text-right">Percentage (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php 
                            $data_source = $report_type === 'income' ? $income_by_category : $expense_by_category;
                            $grand_total = $report_type === 'income' ? $total_income : $total_expenses;
                            $color_class = $report_type === 'income' ? 'text-emerald-600' : 'text-rose-600';
                            $bg_class = $report_type === 'income' ? 'bg-emerald-50' : 'bg-rose-50';

                            foreach($data_source as $row): 
                                $pct = $grand_total > 0 ? ($row['total'] / $grand_total) * 100 : 0;
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-4 font-bold text-slate-800 text-sm"><?= htmlspecialchars($row['category']) ?></td>
                                <td class="px-8 py-4 text-right font-black <?= $color_class ?> text-sm"><?= number_format($row['total'], 2) ?></td>
                                <td class="px-8 py-4 text-right font-bold text-slate-500 text-xs">
                                    <div class="flex items-center justify-end gap-3">
                                        <span><?= number_format($pct, 1) ?>%</span>
                                        <div class="w-16 h-1.5 <?= $bg_class ?> rounded-full overflow-hidden">
                                            <div class="h-full <?= str_replace('text-', 'bg-', $color_class) ?>" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($data_source)): ?>
                            <tr>
                                <td colspan="3" class="px-8 py-12 text-center text-slate-400 italic font-medium">No records found for the selected criteria.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <?php if(!empty($data_source)): ?>
                        <tfoot class="bg-slate-50 border-t-2 border-slate-100">
                            <tr>
                                <td class="px-8 py-5 font-black text-slate-900 uppercase tracking-widest text-xs text-right">Grand Total:</td>
                                <td class="px-8 py-5 text-right font-black text-lg <?= $color_class ?> underline decoration-double">₵<?= number_format($grand_total, 2) ?></td>
                                <td class="px-8 py-5"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        <?php elseif($report_type === 'transactions'): ?>
            <!-- Recent Transactions Table -->
            <?php
            // Fetch top 100 recent transactions based on filters
            $limit = 100;
            
            // Build payments where clauses
            $p_clauses = ["1=1"];
            $p_params = [];
            $p_types = "";
            
            if ($selected_term !== 'All') {
                $p_clauses[] = "semester = ?";
                $p_params[] = $selected_term;
                $p_types .= "s";
            }
            if ($selected_academic_year) {
                $p_clauses[] = "academic_year = ?";
                $p_params[] = $selected_academic_year;
                $p_types .= "s";
            }
            if ($date_from) {
                $p_clauses[] = "payment_date >= ?";
                $p_params[] = $date_from;
                $p_types .= "s";
            }
            if ($date_to) {
                $p_clauses[] = "payment_date <= ?";
                $p_params[] = $date_to;
                $p_types .= "s";
            }
            $p_where = implode(" AND ", $p_clauses);

            // Build expenses where clauses
            $e_clauses = ["1=1"];
            $e_params = [];
            $e_types = "";
            
            if ($selected_term !== 'All') {
                $e_clauses[] = "semester = ?";
                $e_params[] = $selected_term;
                $e_types .= "s";
            }
            if ($selected_academic_year) {
                $e_clauses[] = "academic_year = ?";
                $e_params[] = $selected_academic_year;
                $e_types .= "s";
            }
            if ($date_from) {
                $e_clauses[] = "expense_date >= ?";
                $e_params[] = $date_from;
                $e_types .= "s";
            }
            if ($date_to) {
                $e_clauses[] = "expense_date <= ?";
                $e_params[] = $date_to;
                $e_types .= "s";
            }
            $e_where = implode(" AND ", $e_clauses);
            
            // Payments query
            $p_sql = "SELECT 'Payment' as type, payment_date as t_date, amount, COALESCE(NULLIF(receipt_no, ''), description, 'Student Payment') as ref FROM payments WHERE $p_where";
            // Expenses query
            $e_sql = "SELECT 'Expense' as type, expense_date as t_date, amount, description as ref FROM expenses WHERE $e_where";
            
            // Union and sort by transaction date
            $u_sql = "($p_sql) UNION ALL ($e_sql) ORDER BY t_date DESC, amount DESC LIMIT $limit";
            
            $stmt = $conn->prepare($u_sql);
            $bind_types = $p_types . $e_types;
            $bind_params = array_merge($p_params, $e_params);
            if ($bind_types) {
                $stmt->bind_param($bind_types, ...$bind_params);
            }
            $stmt->execute();
            $tx_result = $stmt->get_result();
            ?>
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Recent Transactions List (Top 100)</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-8 py-4">Date</th>
                                <th class="px-8 py-4">Type</th>
                                <th class="px-8 py-4">Reference</th>
                                <th class="px-8 py-4 text-right">Amount (₵)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php while($tx = $tx_result->fetch_assoc()): 
                                $is_income = $tx['type'] === 'Payment';
                            ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-4 font-bold text-slate-600 text-xs"><?= date('M d, Y', strtotime($tx['t_date'])) ?></td>
                                <td class="px-8 py-4">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black tracking-widest uppercase <?= $is_income ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' ?>">
                                        <?= $tx['type'] ?>
                                    </span>
                                </td>
                                <td class="px-8 py-4 font-bold text-slate-800 text-sm"><?= htmlspecialchars($tx['ref'] ?: 'N/A') ?></td>
                                <td class="px-8 py-4 text-right font-black <?= $is_income ? 'text-emerald-600' : 'text-rose-600' ?> text-sm"><?= number_format($tx['amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if($tx_result->num_rows === 0): ?>
                            <tr>
                                <td colspan="4" class="px-8 py-12 text-center text-slate-400 italic font-medium">No transactions found for the selected criteria.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <!-- Fallback for other generic requests -->
            <div class="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><?= $navs[$report_type][0] ?? 'Data' ?> Ledger</h3>
                </div>
                <div class="p-20 text-center">
                    <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center text-indigo-400 text-3xl mx-auto mb-6">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 mb-2">Detailed View Initialized</h3>
                    <p class="text-slate-500 font-medium max-w-sm mx-auto">This specific view is being refined for enhanced analytics.</p>
                </div>
            </div>
        <?php endif; ?>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            Finance Analysis System &middot; Salba Montessori &middot; v9.4.0
        </footer>
    </main>
</body>
</html>
