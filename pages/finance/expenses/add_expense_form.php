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
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_expenses.php" class="hover:text-blue-600 transition-colors">Institutional Expenses</a>
                <span>/</span>
                <span class="text-blue-600">Add Expenditure</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-money-check text-rose-600"></i> Add Expenditure
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Record institutional spending and allocate to departmental budgets.</p>
                </div>
                <a href="view_expenses.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Cancel & Return
                </a>
            </div>
        </div>

        <div class="px-6">

        <form action="add_expense.php" method="POST" class="max-w-4xl space-y-6">
            <!-- Classification -->
            <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <i class="fas fa-tags text-slate-400"></i> Classification & Purpose
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Expense Classification</label>
                        <select name="category_id" required class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500 outline-none text-sm font-medium text-slate-900 appearance-none transition-all">
                            <option value="">Select category...</option>
                            <?php while($cat = $cat_result->fetch_assoc()): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Maturity Value (GHS)</label>
                        <div class="relative">
                            <input type="number" step="0.01" name="amount" required placeholder="0.00" class="w-full pl-10 pr-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500 outline-none text-sm font-bold text-rose-600 transition-all">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-semibold">₵</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Expenditure Narrative</label>
                        <textarea name="description" rows="3" placeholder="Explain the purpose of this procurement..." class="w-full px-4 py-3 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500 outline-none text-sm font-medium text-slate-900 transition-all"></textarea>
                    </div>
                </div>
            </section>

            <!-- Temporal Context -->
            <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                    <i class="fas fa-calendar-alt text-slate-400"></i> Temporal Context
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Transaction Date</label>
                        <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500 outline-none text-sm font-medium text-slate-900 transition-all">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Semester</label>
                        <select name="semester" required class="w-full px-4 py-2 bg-white border border-slate-300 rounded-lg focus:ring-1 focus:ring-rose-500 focus:border-rose-500 outline-none text-sm font-medium text-slate-900 appearance-none transition-all">
                            <?php foreach($available_terms as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $t === $current_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Academic Year</label>
                        <input type="text" name="academic_year" value="<?= htmlspecialchars($academic_year) ?>" readonly class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm font-medium text-slate-500 cursor-not-allowed">
                        <p class="text-[10px] font-medium text-slate-400 mt-1">Fixed to systemic year: <?= formatAcademicYearDisplay($conn, $academic_year) ?></p>
                    </div>
                </div>
            </section>

            <!-- Finalize -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-rose-500 text-lg shadow-sm">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Commit to Ledger</h3>
                        <p class="text-slate-500 text-xs mt-0.5">Entry will be logged for semester audit.</p>
                    </div>
                </div>
                <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> Register Expenditure
                </button>
            </div>
        </form>
        </div>
    </main>
</html>
