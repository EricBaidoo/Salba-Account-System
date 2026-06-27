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
    <title><?= $existing?'Edit':'Setup' ?> Budget | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .sticky-summary { backdrop-filter: blur(0.75rem); background: rgba(15, 23, 42, 0.9); border-top: 0.0625rem solid rgba(255, 255, 255, 0.1); }
        .income-card { transition: all 0.2s; }
        .income-card:focus-within { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        .expense-card { transition: all 0.2s; cursor: pointer; }
        .expense-card:hover { border-color: #e11d48; box-shadow: 0 4px 16px rgba(225,29,72,0.08); transform: translateY(-1px); }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 pb-40 min-h-screen">
        <!-- Header -->
        <header class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
                    <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                    <span>/</span>
                    <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="hover:text-indigo-600 transition-colors">Budget</a>
                    <span>/</span>
                    <span class="text-indigo-600">Edit Budget</span>
                </div>
                <h1 class="text-3xl font-black text-slate-900 tracking-tight">Edit Budget</h1>
                <p class="text-slate-500 mt-1 font-medium text-sm"><?= htmlspecialchars($current_term) ?> &bull; <?= htmlspecialchars($academic_year) ?></p>
            </div>
            <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="bg-white border border-slate-200 text-slate-500 font-black text-[0.625rem] uppercase tracking-widest px-6 py-3 rounded-xl hover:bg-slate-50 transition-all leading-none flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Budget
            </a>
        </header>

        <form id="budgetForm" action="process_semester_budget.php" method="POST" class="space-y-12">
            <input type="hidden" name="semester" value="<?= htmlspecialchars($current_term) ?>">
            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <!-- INCOME SECTION -->
                <section class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-coins text-emerald-500"></i> Income Budget
                        </h3>
                        <span class="text-[0.5625rem] font-black text-emerald-600 uppercase tracking-widest bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">From Fee Assignments</span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium -mt-2">Amounts below are the total fees assigned to active students this semester. Adjust if needed.</p>

                    <div class="space-y-4">
                        <?php 
                        $fees->data_seek(0);
                        while ($fee = $fees->fetch_assoc()): 
                            $assigned_query = "SELECT COALESCE(SUM(sf.amount), 0) as total FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE sf.fee_id = {$fee['id']} AND sf.semester = '$current_term' AND sf.academic_year = '$academic_year' AND s.status = 'active'";
                            $assigned_total = (float)$conn->query($assigned_query)->fetch_assoc()['total'];
                            $fee_amount = $assigned_total;
                            foreach ($income_items as $item) if ($item['category'] === $fee['name']) { $fee_amount = $item['amount']; break; }
                        ?>
                        <div class="income-card bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                            <div class="flex justify-between items-center mb-3">
                                <div>
                                    <h4 class="text-sm font-black text-slate-800"><?= htmlspecialchars($fee['name']) ?></h4>
                                    <p class="text-[0.5625rem] font-bold text-slate-400 uppercase mt-0.5">Actual Assigned: GH₵<?= number_format($assigned_total, 2) ?></p>
                                </div>
                                <?php if($fee_amount != $assigned_total): ?>
                                    <span class="text-[0.5rem] font-black text-amber-600 bg-amber-50 border border-amber-100 px-2 py-1 rounded-md uppercase">Variance</span>
                                <?php endif; ?>
                            </div>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">GH₵</span>
                                <input type="number" step="0.01" name="income_amount[]" value="<?= $fee_amount ?>" class="income-amount w-full bg-slate-50 border border-slate-100 rounded-xl pl-12 pr-4 py-3 text-sm font-black text-slate-900 outline-none focus:bg-white focus:border-indigo-400 transition-all text-right">
                                <input type="hidden" name="income_category[]" value="<?= htmlspecialchars($fee['name']) ?>">
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </section>

                <!-- EXPENSE SECTION -->
                <section class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-black text-slate-700 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-receipt text-rose-500"></i> Expense Budget
                        </h3>
                        <span class="text-[0.5625rem] font-black text-rose-600 uppercase tracking-widest bg-rose-50 px-3 py-1 rounded-full border border-rose-100">Bottom-Up</span>
                    </div>
                    <p class="text-xs text-slate-500 font-medium -mt-2">Click any category to add or manage its line items. The total is calculated automatically from your items.</p>

                    <div class="space-y-3">
                        <?php 
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()):
                            // Get current total from sub-items (if budget exists)
                            $cat_total = 0;
                            $item_count = 0;
                            if ($existing) {
                                $cat_esc = $conn->real_escape_string($cat['name']);
                                $budget_item_row = $conn->query("SELECT id, amount FROM semester_budget_items WHERE semester_budget_id = {$existing['id']} AND category = '$cat_esc' AND type = 'expense'")->fetch_assoc();
                                if ($budget_item_row) {
                                    $cat_total = (float)$budget_item_row['amount'];
                                    $item_count = (int)$conn->query("SELECT COUNT(*) as c FROM semester_budget_item_sources WHERE budget_item_id = {$budget_item_row['id']}")->fetch_assoc()['c'];
                                }
                            }
                            $detail_url = 'budget_category_detail.php?semester=' . urlencode($current_term) . '&academic_year=' . urlencode($academic_year) . '&category=' . urlencode($cat['name']);
                        ?>
                        <a href="<?= $detail_url ?>" class="expense-card flex items-center justify-between bg-white rounded-2xl p-5 border border-slate-100 shadow-sm group no-underline">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 bg-rose-50 text-rose-500 rounded-xl flex items-center justify-center group-hover:bg-rose-500 group-hover:text-white transition-colors">
                                    <i class="fas fa-receipt text-sm"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black text-slate-800"><?= htmlspecialchars($cat['name']) ?></h4>
                                    <p class="text-[0.5625rem] font-bold text-slate-400 uppercase mt-0.5">
                                        <?= $item_count > 0 ? $item_count . ' item' . ($item_count != 1 ? 's' : '') : 'No items yet — click to add' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-black text-slate-900">GH₵ <?= number_format($cat_total, 2) ?></p>
                                    <?php if ($cat_total > 0): ?>
                                        <span class="text-[0.5rem] font-black text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md uppercase">Budgeted</span>
                                    <?php else: ?>
                                        <span class="text-[0.5rem] font-black text-slate-300 bg-slate-50 px-2 py-0.5 rounded-md uppercase">Not set</span>
                                    <?php endif; ?>
                                </div>
                                <i class="fas fa-chevron-right text-slate-300 group-hover:text-rose-500 transition-colors text-xs"></i>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                </section>
            </div>

            <!-- Sticky Summary Bar -->
            <div class="fixed bottom-6 left-[18.5rem] right-6 sticky-summary rounded-2xl px-8 py-5 shadow-2xl shadow-indigo-900/40 z-40 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-8">
                    <div>
                        <p class="text-[0.5625rem] font-black text-emerald-400 uppercase tracking-widest mb-0.5">Total Income Budget</p>
                        <h4 id="totalIncome" class="text-lg font-black text-white">GH₵ 0.00</h4>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-[0.5625rem] font-black text-rose-400 uppercase tracking-widest mb-0.5">Total Expenses Budget</p>
                        <h4 id="totalExpenses" class="text-lg font-black text-white">GH₵ 0.00</h4>
                    </div>
                    <div class="w-px h-8 bg-white/10"></div>
                    <div>
                        <p class="text-[0.5625rem] font-black text-indigo-300 uppercase tracking-widest mb-0.5">Net Budget</p>
                        <h4 id="balance" class="text-lg font-black text-white">GH₵ 0.00</h4>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="bg-indigo-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-8 py-4 rounded-xl shadow-xl shadow-indigo-600/20 hover:bg-indigo-500 transition-all active:scale-95">
                        <i class="fas fa-save mr-2"></i> Save Income Budget
                    </button>
                    <a href="semester_budget.php?semester=<?= urlencode($current_term) ?>&academic_year=<?= urlencode($academic_year) ?>" class="bg-white/5 border border-white/10 text-white font-black text-[0.625rem] uppercase tracking-widest px-6 py-4 rounded-xl hover:bg-white/10 transition-all">
                        Cancel
                    </a>
                </div>
            </div>
        </form>

        <footer class="mt-24 py-10 border-t border-slate-200 text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">
            Budget Setup &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>

    <script>
        function updateTotals() {
            const incomeInputs = document.querySelectorAll('.income-amount');
            let incomeTotal = 0;
            incomeInputs.forEach(input => incomeTotal += (parseFloat(input.value) || 0));

            // Expense total is display-only — sum the GH₵ amounts shown in the expense cards
            let expenseTotal = 0;
            document.querySelectorAll('.expense-total-val').forEach(el => {
                expenseTotal += parseFloat(el.dataset.amount || 0);
            });

            const net = incomeTotal - expenseTotal;
            const fmt = n => 'GH\u20B5 ' + Math.abs(n).toLocaleString(undefined, {minimumFractionDigits:2});

            document.getElementById('totalIncome').textContent = fmt(incomeTotal);
            document.getElementById('totalExpenses').textContent = fmt(expenseTotal);

            const balEl = document.getElementById('balance');
            balEl.textContent = (net >= 0 ? '' : '-') + fmt(net);
            balEl.className = 'text-lg font-black ' + (net >= 0 ? 'text-emerald-400' : 'text-rose-400');
        }

        document.querySelectorAll('.income-amount').forEach(i => i.addEventListener('input', updateTotals));
        updateTotals();
    </script>
</body>
</html>
