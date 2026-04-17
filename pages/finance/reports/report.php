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

// Logic and Data Fetching (Condensed for modernization while keeping core logic)
$total_income = 0; $total_expenses = 0;
$income_by_category = []; $expense_by_category = [];

if ($report_type === 'overview' || $report_type === 'income' || $report_type === 'expenses') {
    $income_total_stmt = $conn->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total 
        FROM payments p 
        LEFT JOIN students s ON p.student_id = s.id
        WHERE p.academic_year = ? 
        " . ($selected_term !== 'All' ? "AND p.semester = ? " : "") . "
        AND (s.status = 'active' OR p.payment_type = 'general')
    ");
    if ($selected_term !== 'All') $income_total_stmt->bind_param('ss', $selected_academic_year, $selected_term);
    else $income_total_stmt->bind_param('s', $selected_academic_year);
    $income_total_stmt->execute();
    $total_income = (float)$income_total_stmt->get_result()->fetch_assoc()['total'];

    // Income categories
    $filter_term = ($selected_term !== 'All');
    $income_union_sql = "
        (SELECT f.name AS category, SUM(pa.amount) AS total FROM payment_allocations pa JOIN student_fees sf ON pa.student_fee_id = sf.id JOIN payments p ON pa.payment_id = p.id JOIN fees f ON sf.fee_id = f.id LEFT JOIN students s ON p.student_id = s.id WHERE p.academic_year = ? ".($filter_term?"AND p.semester = ? ":"")." AND (s.status = 'active' OR s.id IS NULL) GROUP BY f.id)
        UNION ALL
        (SELECT CONCAT(f.name, ' (General)') AS category, SUM(p.amount) AS total FROM payments p JOIN fees f ON p.fee_id = f.id LEFT JOIN students s ON p.student_id = s.id WHERE p.payment_type = 'general' AND p.academic_year = ? ".($filter_term?"AND p.semester = ? ":"")." GROUP BY f.id)
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

    <main class="lg:ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Audit Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Financial <span class="text-indigo-600">Analytics</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Multi-dimensional reporting for institutional fiscal oversight.</p>
            </div>
            <div class="no-print flex gap-4">
                 <button onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-print mr-2"></i> Release Report
                 </button>
            </div>
        </header>

        <!-- Navigation Context -->
        <nav class="no-print flex flex-wrap gap-2 mb-12 bg-white/50 p-2 rounded-3xl border border-slate-100 w-fit">
            <?php 
            $navs = [
                'overview' => ['Overview', 'chart-pie'],
                'income' => ['Income', 'coins'],
                'expenses' => ['Expenses', 'receipt'],
                'budget' => ['Budget vs Actual', 'calculator'],
                'student_fees' => ['Collections', 'users'],
                'transactions' => ['Transactions', 'list-check']
            ];
            foreach($navs as $type => $info): ?>
                <a href="?report_type=<?= $type ?>&semester=<?= $selected_term ?>&academic_year=<?= $selected_academic_year ?>" 
                   class="nav-pill px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest flex items-center gap-3 <?= $report_type===$type?'active':'text-slate-400' ?>">
                    <i class="fas fa-<?= $info[1] ?> text-xs"></i> <?= $info[0] ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Filter Console -->
        <section class="no-print bg-white rounded-[2.5rem] p-8 border border-slate-100 shadow-sm mb-12">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">
                <input type="hidden" name="report_type" value="<?= $report_type ?>">
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Fiscal Year</label>
                    <select name="academic_year" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none">
                        <?php foreach($year_options as $y): ?>
                            <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Semester Context</label>
                    <select name="semester" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none">
                        <option value="All">All Semesters</option>
                        <?php foreach($available_terms as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $selected_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Scope (Optional)</label>
                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="<?= $date_from ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none">
                        <input type="date" name="date_to" value="<?= $date_to ?>" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none">
                    </div>
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest px-4 py-4 rounded-xl shadow-lg shadow-indigo-600/20 leading-none">Apply Scopes</button>
            </form>
        </section>

        <!-- Report Content -->
        <?php if($report_type === 'overview'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                <!-- Summary Metrics -->
                <div class="lg:col-span-12 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-emerald-500 p-8 rounded-[2.5rem] shadow-xl shadow-emerald-500/10 text-white">
                        <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest mb-2">Aggregate Income</p>
                        <h3 class="text-4xl font-black italic">₵<?= number_format($total_income, 2) ?></h3>
                    </div>
                    <div class="bg-rose-500 p-8 rounded-[2.5rem] shadow-xl shadow-rose-500/10 text-white">
                        <p class="text-[10px] font-black text-rose-100 uppercase tracking-widest mb-2">Aggregate Expenditure</p>
                        <h3 class="text-4xl font-black italic">₵<?= number_format($total_expenses, 2) ?></h3>
                    </div>
                    <div class="bg-indigo-900 p-8 rounded-[2.5rem] shadow-xl shadow-indigo-900/10 text-white">
                        <p class="text-[10px] font-black text-indigo-300 uppercase tracking-widest mb-2">Net Liquidity</p>
                        <h3 class="text-4xl font-black italic">₵<?= number_format($total_income - $total_expenses, 2) ?></h3>
                    </div>
                </div>

                <!-- Category Matrix -->
                <div class="lg:col-span-6 bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-10 py-8 border-b border-slate-50 bg-slate-50/50">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Income Classification Breakdown</h4>
                    </div>
                    <div class="p-8 space-y-6">
                        <?php foreach($income_by_category as $row): 
                            $pct = $total_income > 0 ? ($row['total']/$total_income)*100 : 0;
                        ?>
                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <p class="text-[11px] font-black text-slate-700 uppercase leading-none"><?= htmlspecialchars($row['category']) ?></p>
                                    <p class="text-[11px] font-black text-emerald-600">₵<?= number_format($row['total'], 2) ?></p>
                                </div>
                                <div class="h-2 bg-slate-50 rounded-full overflow-hidden flex items-center">
                                    <div class="h-full bg-emerald-500 rounded-full" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="lg:col-span-6 bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-10 py-8 border-b border-slate-50 bg-slate-50/50">
                        <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Expenditure Matrix</h4>
                    </div>
                    <div class="p-8 space-y-6">
                        <?php foreach($expense_by_category as $row): 
                            $pct = $total_expenses > 0 ? ($row['total']/$total_expenses)*100 : 0;
                        ?>
                            <div class="space-y-2">
                                <div class="flex justify-between items-end">
                                    <p class="text-[11px] font-black text-slate-700 uppercase leading-none"><?= htmlspecialchars($row['category']) ?></p>
                                    <p class="text-[11px] font-black text-rose-600">₵<?= number_format($row['total'], 2) ?></p>
                                </div>
                                <div class="h-2 bg-slate-50 rounded-full overflow-hidden flex items-center">
                                    <div class="h-full bg-rose-500 rounded-full" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Budget Overview (Condensed) -->
                <?php if(!empty($budget_comparison)): ?>
                <div class="lg:col-span-12 bg-slate-900 rounded-[2.5rem] p-10 text-white border border-slate-800">
                    <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-10 flex items-center gap-4">
                        <i class="fas fa-chart-line text-emerald-500"></i> Semester Performance Benchmarks
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-12">
                        <?php foreach($budget_comparison as $term => $data): 
                            $e_pct = $data['e_bud']>0 ? ($data['e_act']/$data['e_bud'])*100 : 0;
                        ?>
                            <div class="space-y-6">
                                <h5 class="text-xl font-black tracking-tight text-white"><?= htmlspecialchars($term) ?></h5>
                                <div class="flex justify-between py-4 border-b border-white/5">
                                    <span class="text-[10px] font-black text-slate-500 uppercase">Expense Utilization</span>
                                    <span class="text-[10px] font-black <?= $e_pct>100?'text-rose-400':'text-emerald-400' ?>"><?= round($e_pct) ?>% Consumed</span>
                                </div>
                                <div class="flex justify-between py-4 border-b border-white/5">
                                    <span class="text-[10px] font-black text-slate-500 uppercase">Realized Net</span>
                                    <span class="text-[11px] font-black text-indigo-400">₵<?= number_format($data['i_act'] - $data['e_act'], 2) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Fallback for other reports - modernized table -->
            <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]"><?= $navs[$report_type][0] ?> Ledger</h3>
                </div>
                <div class="p-20 text-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-3xl mx-auto mb-6">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-900 mb-2">Detailed View Initialized</h3>
                    <p class="text-slate-500 font-medium max-w-sm mx-auto italic">Refining data structures for specific report types: <?= $navs[$report_type][0] ?>.</p>
                </div>
            </div>
        <?php endif; ?>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Registry Ledger &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
