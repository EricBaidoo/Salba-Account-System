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

// Get or create semester budget
$budget_stmt = $conn->prepare("SELECT * FROM semester_budgets WHERE semester = ? AND academic_year = ?");
$budget_stmt->bind_param("ss", $current_term, $academic_year);
$budget_stmt->execute();
$semester_budget = $budget_stmt->get_result()->fetch_assoc();
$is_locked = $semester_budget && isset($semester_budget['status']) && $semester_budget['status'] === 'locked';

// Financial Projections (Income)
$income_items = [];
$fees_result = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");
while ($fee = $fees_result->fetch_assoc()) {
    $row = ['category' => $fee['name']];
    
    // Budgeted = Total Assigned Bills
    $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total 
                      FROM student_fees sf 
                      INNER JOIN students s ON sf.student_id = s.id
                      WHERE sf.fee_id = {$fee['id']} 
                      AND sf.semester = '{$conn->real_escape_string($current_term)}' 
                      AND sf.academic_year = '{$conn->real_escape_string($academic_year)}'
                      AND s.status = 'active'";
    $row['amount'] = (float)$conn->query($assigned_query)->fetch_assoc()['total'];
    
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
$total_income_budget = array_sum(array_column($income_items, 'amount'));
$total_income_actual = array_sum(array_column($income_items, 'actual'));
$total_expense_budget = array_sum(array_column($expense_items, 'amount'));
$total_expense_actual = array_sum(array_column($expense_items, 'actual'));

$net_budgeted = $total_income_budget - $total_expense_budget;
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
    <div class="no-print"><?php include '../../../includes/sidebar.php'; ?></div>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header Section -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6 no-print">
            <div>
                <div class="flex items-center gap-2 text-purple-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-purple-600"></span>
                    Financial Forecasting
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Semester <span class="text-purple-600">Budget</span></h1>
                <p class="text-slate-500 mt-2 font-medium"><?= htmlspecialchars($current_term) ?> | <?= htmlspecialchars(formatAcademicYearDisplay($conn, $academic_year)) ?></p>
            </div>
            <div class="flex items-center gap-3">
                 <?php if (!$is_locked): ?>
                    <a href="edit_semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="bg-purple-600 text-white font-black text-[10px] uppercase tracking-widest px-6 py-4 rounded-2xl shadow-lg shadow-purple-600/20 hover:bg-purple-700 transition-all leading-none">
                        <i class="fas fa-edit mr-2"></i> Configure Estimates
                    </a>
                    <?php if ($semester_budget): ?>
                        <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>&action=lock" class="bg-white text-slate-600 border border-slate-200 font-black text-[10px] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none" onclick="return confirm('DANGER: Lock this budget? All modifications will be deactivated.');">
                            <i class="fas fa-lock mr-2"></i> Lock Assets
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="bg-slate-900 text-white px-6 py-4 rounded-2xl flex items-center gap-3 shadow-xl">
                        <i class="fas fa-shield-halved text-emerald-400"></i>
                         <div class="flex flex-col">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 leading-none mb-1">Vault Locked</span>
                            <span class="text-[10px] font-bold"><?= htmlspecialchars($semester_budget['locked_by']) ?></span>
                         </div>
                         <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>&action=unlock" class="ml-4 w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center hover:bg-white/20 transition-all" title="Request Unlock">
                            <i class="fas fa-lock-open text-xs"></i>
                         </a>
                    </div>
                <?php endif; ?>
                <button onclick="window.print()" class="w-12 h-12 bg-white border border-slate-100 rounded-2xl flex items-center justify-center text-slate-400 hover:text-purple-600 transition-all shadow-sm">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </header>

        <!-- Dynamic Feedback -->
        <?php if (isset($_GET['locked'])): ?>
            <div class="mb-8 p-6 bg-emerald-50 border border-emerald-100 rounded-[2rem] flex items-center gap-4 text-emerald-700 no-print">
                <div class="w-12 h-12 bg-emerald-500 text-white rounded-full flex items-center justify-center text-xl shadow-lg shadow-emerald-500/20">
                    <i class="fas fa-check"></i>
                </div>
                <div>
                    <h5 class="font-black text-sm uppercase tracking-tight">Security Protocol Active</h5>
                    <p class="text-xs font-semibold opacity-80">Budget has been authorized and locked for the current session.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Snapshot Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
            <!-- Income Snapshot -->
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-money-bill-trend-up text-6xl text-emerald-600"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Projected Revenue</p>
                <h2 class="text-3xl font-black text-slate-900 mb-4">GHS <?= number_format($total_income_budget, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-emerald-50 text-emerald-600 w-fit px-3 py-1 rounded-full">
                    <i class="fas fa-circle-check"></i> From Active Bills
                </div>
            </div>

            <!-- Expense Snapshot -->
            <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-6 opacity-5 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-receipt text-6xl text-rose-600"></i>
                </div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Target Expenditure</p>
                <h2 class="text-3xl font-black text-slate-900 mb-4">GHS <?= number_format($total_expense_budget, 2) ?></h2>
                <div class="flex items-center gap-2 text-[10px] font-bold bg-rose-50 text-rose-600 w-fit px-3 py-1 rounded-full">
                    <i class="fas fa-compass"></i> Estimates Applied
                </div>
            </div>

            <!-- Position Snapshot -->
            <div class="bg-slate-900 p-8 rounded-[2.5rem] shadow-xl text-white relative overflow-hidden group lg:col-span-2">
                 <div class="absolute top-0 right-0 p-8 opacity-10 group-hover:scale-110 transition-transform duration-500">
                    <i class="fas fa-chart-line text-7xl text-purple-400"></i>
                </div>
                <div class="flex flex-col md:flex-row gap-12">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Budgeted Operating Net</p>
                        <h2 class="text-4xl font-black <?= $net_budgeted >= 0 ? 'text-emerald-400' : 'text-rose-400' ?> mb-2 tracking-tighter">GHS <?= number_format($net_budgeted, 2) ?></h2>
                        <p class="text-[10px] font-bold text-slate-500 uppercase">Estimated Surplus/Deficit</p>
                    </div>
                    <div class="flex flex-col justify-end">
                         <div class="p-4 bg-white/5 rounded-2xl border border-white/10 backdrop-blur-sm">
                            <?php 
                                $is_healthy = $total_expense_actual <= $total_expense_budget && $total_income_actual >= $total_income_budget;
                                $icon = $is_healthy ? 'fa-bolt text-emerald-400' : 'fa-triangle-exclamation text-amber-400';
                                $label = $is_healthy ? 'System Optimized' : 'Allocation Variance';
                            ?>
                            <div class="flex items-center gap-3">
                                <i class="fas <?= $icon ?> text-xl"></i>
                                <div>
                                    <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest">Health Audit</div>
                                    <div class="text-xs font-black uppercase tracking-tight"><?= $label ?></div>
                                </div>
                            </div>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytical Breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Income Ledger -->
            <div>
                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
                    Projected Revenue Streams <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>
                <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Revenue Pillar</th>
                                <th class="px-8 py-5 text-right text-[9px] font-black text-slate-400 uppercase tracking-widest">Maturity (GHS)</th>
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
                                <td class="px-8 py-6 text-[10px] font-black text-emerald-700 uppercase tracking-widest">Total Projected Inflow</td>
                                <td class="px-8 py-6 text-right text-lg font-black text-emerald-600">GHS <?= number_format($total_income_budget, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Expense Ledger -->
            <div>
                 <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-6 flex items-center gap-3">
                    Allocated Expenditure <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>
                <div class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100">
                                <th class="px-8 py-5 text-left text-[9px] font-black text-slate-400 uppercase tracking-widest">Expense Category</th>
                                <th class="px-8 py-5 text-right text-[9px] font-black text-slate-400 uppercase tracking-widest">Limit (GHS)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($expense_items as $item): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-8 py-5 text-sm font-bold text-slate-800"><?= htmlspecialchars($item['category']) ?></td>
                                <td class="px-8 py-5 text-right font-black text-slate-900"><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="bg-rose-50/30">
                                <td class="px-8 py-6 text-[10px] font-black text-rose-700 uppercase tracking-widest">Total Allocated Assets</td>
                                <td class="px-8 py-6 text-right text-lg font-black text-rose-600">GHS <?= number_format($total_expense_budget, 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer Audit -->
        <footer class="mt-20 py-10 border-t border-slate-200 flex justify-between items-center text-[10px] font-black text-slate-300 uppercase tracking-[0.5em] no-print">
            <span>Budget Optimization Protocol &middot; Financial Forecast &middot; v9.5.0</span>
            <div class="flex gap-6">
                <a href="../dashboard.php" class="hover:text-purple-600 transition-colors">Dashboard Control</a>
                <a href="../reports/report.php" class="hover:text-purple-600 transition-colors">Detailed Analytics</a>
            </div>
        </footer>
    </main>
</body>
</html>
