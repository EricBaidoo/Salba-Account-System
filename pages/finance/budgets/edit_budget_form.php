<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/budget_functions.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}
require_finance_access();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: budgets.php'); exit; }

$budget = getBudgetById($conn, $id);
if (!$budget) { header('Location: budgets.php'); exit; }

$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
$cat_list = [];
while($c = $categories->fetch_assoc()) $cat_list[] = $c;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revise Fiscal Projection | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Fiscal Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight italic">Adjust <span class="text-indigo-600">Projection</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Calibrating institutional spending thresholds for <?= htmlspecialchars($budget['semester']) ?>.</p>
            </div>
            <a href="budgets.php" class="bg-white border border-slate-200 text-slate-400 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Return to Projections
            </a>
        </header>

        <div class="max-w-3xl">
            <form action="process_budget.php" method="POST" onsubmit="return validateForm()" class="space-y-10">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= htmlspecialchars($budget['id']) ?>">

                <!-- Parameters Hub -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden group">
                     <div class="absolute top-0 right-0 p-10 opacity-5 group-hover:scale-110 transition-transform duration-700">
                        <i class="fas fa-calculator text-8xl text-indigo-600"></i>
                    </div>
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10">Institutional Allocation Details</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Fiscal Category</label>
                                <select name="category" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 appearance-none transition-all">
                                    <option value="">-- Classification --</option>
                                    <?php foreach ($cat_list as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['name']) ?>" <?= $cat['name'] === $budget['category'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Projected Amount (GHS)</label>
                                <input type="number" step="0.01" name="amount" id="amount" value="<?= htmlspecialchars($budget['amount']) ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-xl font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                            </div>
                        </div>
                        <div class="space-y-8">
                             <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Constraint Narrative / Notes</label>
                                <textarea name="description" rows="1" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-medium text-slate-600 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all leading-loose"><?= htmlspecialchars($budget['description'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Risk Alert Threshold (%)</label>
                                <div class="relative">
                                    <input type="number" step="1" name="alert_threshold" id="alert_threshold" value="<?= htmlspecialchars($budget['alert_threshold'] ?? 80) ?>" min="0" max="100" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                                    <span class="absolute right-6 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300 uppercase">Percent</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Scope -->
                    <div class="mt-10 grid grid-cols-1 md:grid-cols-2 gap-10 pt-10 border-t border-slate-50">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Activation Date</label>
                            <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($budget['start_date']) ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Semesterination Date</label>
                            <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($budget['end_date']) ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-indigo-500/10 transition-all">
                        </div>
                    </div>
                </section>

                <!-- Action Bar -->
                <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white border border-slate-800 shadow-2xl flex flex-wrap items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-indigo-600/20">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-[0.2em] text-indigo-400">Projection Sync Authorized</h4>
                            <p class="text-slate-500 text-[10px] font-bold mt-1 uppercase leading-none italic">Recalibrating institutional fiscal constraints.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-black text-[10px] uppercase tracking-widest px-10 py-5 rounded-2xl transition-all shadow-xl shadow-indigo-600/20 active:scale-95 leading-none h-fit">
                        Sync Fiscal Node
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Salba Montessori &middot; Institutional Planning Hub &middot; v9.5.0
        </footer>
    </main>

    <script>
        function validateForm() {
            const amount = parseFloat(document.getElementById('amount').value);
            const threshold = parseInt(document.getElementById('alert_threshold').value);
            const sd = new Date(document.getElementById('start_date').value);
            const ed = new Date(document.getElementById('end_date').value);

            if (amount <= 0) { alert('Institutional amount must exceed zero.'); return false; }
            if (threshold < 0 || threshold > 100) { alert('Alert threshold constraint must be 0-100.'); return false; }
            if (sd >= ed) { alert('Semesterination date must follow activation date.'); return false; }
            return true;
        }
    </script>
</body>
</html>
