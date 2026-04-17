<?php 
include '../../../includes/auth_check.php'; 
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Get current semester and academic year
$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableSemesters($conn);

// Fetch categories
$cat_result = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Expenditure | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex justify-between items-end">
            <div>
                <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-rose-600"></span>
                    Procurement Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Add <span class="text-rose-600">Expenditure</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Record institutional spending and allocate to departmental budgets.</p>
            </div>
            <div class="flex gap-4">
                <a href="view_expenses.php" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel & Return
                </a>
            </div>
        </header>

        <form action="add_expense.php" method="POST" class="max-w-4xl">
            <!-- Classification -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-10">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    01. Classification & Purpose <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Expense Classification</label>
                        <select name="category_id" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
                            <option value="">Select category...</option>
                            <?php while($cat = $cat_result->fetch_assoc()): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Maturity Value (GHS)</label>
                        <div class="relative">
                            <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full px-12 py-4 bg-rose-50 border border-rose-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-lg font-black text-rose-900 transition-all">
                            <span class="absolute left-6 top-1/2 -translate-y-1/2 text-rose-400 font-black">₵</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Expenditure Narrative</label>
                        <textarea name="description" rows="3" placeholder="Explain the purpose of this procurement..." class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-[2rem] focus:ring-2 focus:ring-rose-500 outline-none text-sm font-bold text-slate-700 transition-all"></textarea>
                    </div>
                </div>
            </section>

            <!-- Temporal Context -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-12">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    02. Temporal Context <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Transaction Date</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-xs font-black text-slate-700 transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Semester</label>
                        <select name="semester" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-rose-500 outline-none text-xs font-black text-slate-700 appearance-none transition-all">
                            <?php foreach($available_terms as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $current_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Academic Year</label>
                        <input type="text" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>" readonly class="w-full px-6 py-4 bg-slate-100 border border-slate-100 rounded-2xl text-xs font-black text-slate-500 cursor-not-allowed">
                        <p class="text-[9px] font-bold text-slate-400 mt-2 ml-1">Fixed to systemic year: <?= formatAcademicYearDisplay($conn, $academic_year) ?></p>
                    </div>
                </div>
            </section>

            <!-- Finalize -->
            <div class="bg-slate-900 rounded-[2.5rem] p-10 flex items-center justify-between shadow-2xl shadow-slate-900/20">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-rose-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-rose-500/20">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-black text-sm uppercase tracking-[0.1em]">Commit to Ledger</h3>
                        <p class="text-slate-400 text-xs font-medium">Entry will be logged for semester audit.</p>
                    </div>
                </div>
                <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white font-black text-[11px] uppercase tracking-[0.2em] px-10 py-5 rounded-2xl shadow-xl transition-all h-fit active:scale-95 leading-none">
                    Register Expenditure
                </button>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Expenditure Node &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
