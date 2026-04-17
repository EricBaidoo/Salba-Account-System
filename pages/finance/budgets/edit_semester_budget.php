<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';
include '../../../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
require_finance_access();

$current_term = $_GET['semester'] ?? getCurrentSemester($conn);
$academic_year = $_GET['academic_year'] ?? getAcademicYear($conn);

$existing = $conn->query("SELECT * FROM semester_budgets WHERE semester = '$current_term' AND academic_year = '$academic_year'")->fetch_assoc();

if ($existing && isset($existing['status']) && $existing['status'] === 'locked') {
    header("Location: semester_budget.php?semester=" . urlencode($current_term) . "&academic_year=" . urlencode($academic_year) . "&error=locked");
    exit;
}

$fees = $conn->query("SELECT id, name FROM fees ORDER BY name ASC");
$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");

$available_terms = getAvailableSemesters($conn);
$current_term_index = array_search($current_term, $available_terms);
$previous_term = null;
$previous_academic_year = $academic_year;

if ($current_term_index > 0) {
    $previous_term = $available_terms[$current_term_index - 1];
} elseif ($current_term_index === 0) {
    $previous_term = $available_terms[count($available_terms) - 1];
    $year_parts = explode('/', $academic_year);
    if(count($year_parts)==2) $previous_academic_year = ($year_parts[0] - 1) . '/' . ($year_parts[1] - 1);
}

$income_items = []; $expense_items = [];
if ($existing) {
    $res = $conn->query("SELECT * FROM semester_budget_items WHERE semester_budget_id = {$existing['id']} AND type = 'income' ORDER BY category");
    while($r = $res->fetch_assoc()) $income_items[] = $r;
    $res = $conn->query("SELECT * FROM semester_budget_items WHERE semester_budget_id = {$existing['id']} AND type = 'expense' ORDER BY category");
    while($r = $res->fetch_assoc()) $expense_items[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $existing?'Edit':'Setup' ?> Fiscal Hub | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .sticky-summary { backdrop-filter: blur(12px); background: rgba(15, 23, 42, 0.9); border-top: 1px solid rgba(255, 255, 255, 0.1); }
        .budget-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .budget-card:focus-within { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1); }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-10 pb-40 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Strategy Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight italic">Fiscal <span class="text-indigo-600"> Intelligence Hub</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Calibrating institutional projections for <?= htmlspecialchars($current_term) ?> | <?= htmlspecialchars($academic_year) ?>.</p>
            </div>
            <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="bg-white border border-slate-200 text-slate-400 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Exit Projections
            </a>
        </header>

        <form id="budgetForm" action="process_semester_budget.php" method="POST" class="space-y-16">
            <input type="hidden" name="semester" value="<?= htmlspecialchars($current_term) ?>">
            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <!-- Section: Revenue Nodes -->
                <section class="space-y-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] flex items-center gap-3">
                            <i class="fas fa-coins text-emerald-500"></i> Revenue Infrastructure
                        </h3>
                        <span class="text-[9px] font-black text-emerald-600 uppercase tracking-widest bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">Inbound Flow</span>
                    </div>

                    <div class="space-y-6">
                        <?php 
                        $fees->data_seek(0);
                        while ($fee = $fees->fetch_assoc()): 
                            $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE sf.fee_id = {$fee['id']} AND sf.semester = '$current_term' AND sf.academic_year = '$academic_year' AND s.status = 'active'";
                            $assigned_total = (float)$conn->query($assigned_query)->fetch_assoc()['total'];
                            
                            $fee_amount = $assigned_total;
                            foreach ($income_items as $item) if ($item['category'] === $fee['name']) { $fee_amount = $item['amount']; break; }
                        ?>
                        <div class="budget-card bg-white rounded-[2rem] p-8 border border-slate-100 shadow-sm">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($fee['name']) ?></h4>
                                    <p class="text-[9px] font-bold text-slate-400 uppercase mt-1">Institutional Realized: ₵<?= number_format($assigned_total, 2) ?></p>
                                </div>
                                <?php if($fee_amount != $assigned_total): ?>
                                    <i class="fas fa-circle-exclamation text-amber-500 text-xs" title="Variance detected between budget and actual assignments"></i>
                                <?php endif; ?>
                            </div>
                            <div class="relative">
                                <input type="number" step="0.01" name="income_amount[]" value="<?= $fee_amount ?>" class="income-amount w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:bg-white transition-all" placeholder="0.00">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase">Projected</span>
                                <input type="hidden" name="income_category[]" value="<?= htmlspecialchars($fee['name']) ?>">
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </section>

                <!-- Section: Expenditure Targets -->
                <section class="space-y-10">
                    <div class="flex items-center justify-between">
                        <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] flex items-center gap-3">
                            <i class="fas fa-receipt text-rose-500"></i> Expenditure Matrix
                        </h3>
                        <span class="text-[9px] font-black text-rose-600 uppercase tracking-widest bg-rose-50 px-3 py-1 rounded-full border border-rose-100">Outbound Thresholds</span>
                    </div>

                    <div id="budgetItemsContainer" class="space-y-6">
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): 
                            $cat_amount = 0; $found = false;
                            foreach ($expense_items as $item) if ($item['category'] === $cat['name']) { $cat_amount = $item['amount']; $found = true; break; }
                            if (!$found && $previous_term) $cat_amount = getSemesterCategorySpending($conn, $cat['name'], $previous_term, $previous_academic_year);
                        ?>
                        <div class="budget-card bg-white rounded-[2rem] p-8 border border-slate-100 shadow-sm">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-tight"><?= htmlspecialchars($cat['name']) ?></h4>
                                    <?php if($previous_term): ?>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase mt-1">Previous Audit: ₵<?= number_format(getSemesterCategorySpending($conn, $cat['name'], $previous_term, $previous_academic_year), 2) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="relative">
                                <input type="number" step="0.01" name="amount[]" value="<?= $cat_amount ?>" class="item-amount w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:bg-white transition-all" placeholder="0.00">
                                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase">Cap</span>
                                <input type="hidden" name="category[]" value="<?= htmlspecialchars($cat['name']) ?>">
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </section>
            </div>

            <!-- Sticky Intelligence Bar -->
            <div class="fixed bottom-10 left-80 right-10 sticky-summary rounded-[2.5rem] p-8 shadow-2xl shadow-indigo-900/40 z-40 flex items-center justify-between pointer-events-auto">
                <div class="flex items-center gap-10">
                    <div>
                        <p class="text-[9px] font-black text-emerald-400 uppercase tracking-widest mb-1">Expected Revenue</p>
                        <h4 id="totalIncome" class="text-xl font-black text-white italic">₵0.00</h4>
                    </div>
                    <div class="w-[1px] h-10 bg-white/10"></div>
                    <div>
                        <p class="text-[9px] font-black text-rose-400 uppercase tracking-widest mb-1">Projected Spending</p>
                        <h4 id="totalExpenses" class="text-xl font-black text-white italic">₵0.00</h4>
                    </div>
                    <div class="w-[1px] h-10 bg-white/10"></div>
                    <div>
                        <p class="text-[9px] font-black text-indigo-300 uppercase tracking-widest mb-1">Fiscal Balance</p>
                        <h4 id="balance" class="text-xl font-black text-white italic uppercase">₵0.00</h4>
                    </div>
                </div>
                <div class="flex gap-4">
                    <button type="submit" class="bg-indigo-600 text-white font-black text-[10px] uppercase tracking-widest px-10 py-5 rounded-2xl shadow-xl shadow-indigo-600/20 hover:bg-indigo-500 transition-all active:scale-95 leading-none">
                        Synchronize Projections
                    </button>
                    <a href="semester_budget.php" class="bg-white/5 border border-white/10 text-white font-black text-[10px] uppercase tracking-widest px-8 py-5 rounded-2xl hover:bg-white/10 transition-all leading-none">
                        Cancel
                    </a>
                </div>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Salba Institutional Strategy Node &middot; v9.5.0
        </footer>
    </main>

    <script>
        function updateTotals() {
            const incomeInputs = document.querySelectorAll('.income-amount');
            const expenseInputs = document.querySelectorAll('.item-amount');
            
            let incomeTotal = 0;
            incomeInputs.forEach(input => incomeTotal += (parseFloat(input.value) || 0));
            
            let expenseTotal = 0;
            expenseInputs.forEach(input => expenseTotal += (parseFloat(input.value) || 0));
            
            const net = incomeTotal - expenseTotal;

            document.getElementById('totalIncome').textContent = '₵' + incomeTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
            document.getElementById('totalExpenses').textContent = '₵' + expenseTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
            
            const balanceEl = document.getElementById('balance');
            balanceEl.textContent = (net >= 0 ? '+' : '-') + '₵' + Math.abs(net).toLocaleString(undefined, {minimumFractionDigits: 2});
            balanceEl.className = 'text-xl font-black italic uppercase ' + (net >= 0 ? 'text-emerald-400' : 'text-rose-400');
        }

        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', updateTotals);
        });

        // Initialize
        updateTotals();
    </script>
</body>
</html>
