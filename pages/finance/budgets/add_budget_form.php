<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);

// Fetch expense categories for budget categories
$categories = $conn->query("SELECT id, name FROM expense_categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provision Budget | Salba Montessori</title>
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
        <header class="mb-12 flex justify-between items-end">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Resource Allocation
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Budget <span class="text-indigo-600">Provisioning</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Define fiscal ceilings and departmental caps for the current semester.</p>
            </div>
            <div class="flex gap-4">
                <a href="semester_budget.php" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel & Return
                </a>
            </div>
        </header>

        <form action="process_budget.php" method="POST" onsubmit="return validateForm()" class="max-w-4xl">
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($current_term); ?>">
            <input type="hidden" name="academic_year" value="<?php echo htmlspecialchars($academic_year); ?>">

            <!-- Core Details -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-10">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    01. Allocation Objective <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Budget Classification</label>
                        <select name="category" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 appearance-none transition-all">
                            <option value="">Select Category...</option>
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Fiscal Ceiling (GHS)</label>
                        <div class="relative">
                            <input type="number" step="0.01" id="amount" name="amount" required placeholder="0.00" class="w-full px-12 py-4 bg-indigo-50 border border-indigo-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-lg font-black text-indigo-900 transition-all overflow-hidden">
                            <span class="absolute left-6 top-1/2 -translate-y-1/2 text-indigo-400 font-black">₵</span>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Provisioning Strategy / Notes</label>
                        <textarea name="description" rows="3" placeholder="Define the scope of this budget allocation..." class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-[2rem] focus:ring-2 focus:ring-indigo-500 outline-none text-sm font-bold text-slate-700 transition-all"></textarea>
                    </div>
                </div>
            </section>

            <!-- Temporal & Risk Context -->
            <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm mb-12">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                    02. Temporal & Risk Parameters <span class="flex-1 h-[1px] bg-slate-100"></span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Effective Date</label>
                        <input type="date" name="start_date" id="start_date" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-xs font-black text-slate-700 transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Semesterination Date</label>
                        <input type="date" name="end_date" id="end_date" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none text-xs font-black text-slate-700 transition-all">
                    </div>
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Exposure Threshold (%)</label>
                        <div class="relative">
                            <input type="number" step="1" name="alert_threshold" value="80" min="0" max="100" class="w-full px-6 py-4 bg-amber-50 border border-amber-100 rounded-2xl focus:ring-2 focus:ring-amber-500 outline-none text-xs font-black text-amber-900 transition-all">
                            <span class="absolute right-6 top-1/2 -translate-y-1/2 text-amber-400 font-black">%</span>
                        </div>
                    </div>
                </div>
                <p class="text-[9px] font-bold text-slate-400 mt-6 ml-1 flex items-center gap-2">
                    <i class="fas fa-info-circle text-indigo-500"></i>
                    Scope: <?= htmlspecialchars($current_term) ?> | Academic Year: <?= formatAcademicYearDisplay($conn, $academic_year) ?>
                </p>
            </section>

            <!-- Finalize -->
            <div class="bg-slate-900 rounded-[2.5rem] p-10 flex items-center justify-between shadow-2xl shadow-slate-900/20">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 bg-indigo-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-indigo-500/20">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-black text-sm uppercase tracking-[0.1em]">Verify Provision</h3>
                        <p class="text-slate-400 text-xs font-medium">Budgets define the spending limits for the semester.</p>
                    </div>
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-black text-[11px] uppercase tracking-[0.2em] px-10 py-5 rounded-2xl shadow-xl transition-all h-fit active:scale-95 leading-none">
                    Initialize Allocation
                </button>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Budgeting Node &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>

    <script>
        function validateForm() {
            const amount = parseFloat(document.getElementById('amount').value);
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = new Date(document.getElementById('end_date').value);
            
            if (amount <= 0) {
                alert('Exposure ceiling must be > 0');
                return false;
            }
            if (startDate >= endDate) {
                alert('Semesterination must be post-effective date');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
