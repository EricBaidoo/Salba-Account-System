<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login.php');
    exit;
}
require_finance_access();

$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);

$fees_result = $conn->query("SELECT id, name, fee_type, amount FROM fees ORDER BY name ASC");
$classes_result = $conn->query("SELECT DISTINCT class as name FROM students ORDER BY class");

// Handle potential redirects
if (isset($_GET['success']) && $_GET['success'] == 1) {
    header("Location: view_semester_bills.php?generated=1&count=" . ($_GET['count'] ?? 0) . "&skipped=" . ($_GET['skipped'] ?? 0));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Semester Billing | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <header class="mb-8">
            <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                <span class="w-8 h-[0.125rem] bg-indigo-600"></span>
                Billing Operations
            </div>
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Bulk Semester <span class="text-indigo-600">Billing</span></h1>
                    <p class="text-slate-500 mt-1 font-medium text-sm">Issue standardized charges across cohorts.</p>
                </div>
                <a href="view_semester_bills.php" class="px-5 py-3 bg-white border border-slate-200 text-slate-600 font-black text-[0.625rem] uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-list"></i> View All Bills
                </a>
            </div>
        </header>

        <form action="bulk_semester_billing.php" method="POST" class="bg-white rounded-[2rem] border border-slate-100 shadow-sm p-8 max-w-4xl mx-auto mb-10" onsubmit="prepareSubmit()">
            <input type="hidden" name="action" value="preview">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <!-- Target Selection -->
                <div class="space-y-6">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight border-b border-slate-100 pb-3">1. Scope of Billing</h3>

                    <div>
                        <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 block">Target Cohort</label>
                        <select name="class_filter" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="all">Entire Institution (All Active Students)</option>
                            <?php while($c = $classes_result->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>">Class: <?= htmlspecialchars($c['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <!-- Setup Details -->
                <div class="space-y-6">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight border-b border-slate-100 pb-3">2. Period & Deadlines</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 block">Semester</label>
                            <select name="semester" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                                <?php foreach(getAvailableSemesters($conn) as $s): ?>
                                    <option value="<?= htmlspecialchars($s) ?>" <?= $current_term === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 block">Due Date</label>
                            <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2 block">Billing Notes</label>
                        <input type="text" name="notes" placeholder="e.g. Initial Trimester Billing" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
            </div>

            <!-- Fee Selection -->
            <div class="mb-8">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight border-b border-slate-100 pb-3 mb-6">3. Include Fees in Bill</h3>
                <input type="hidden" name="selected_fees" id="selectedFeesInput">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php while($fee = $fees_result->fetch_assoc()): ?>
                    <label class="cursor-pointer">
                        <div class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:bg-indigo-50 transition-all">
                            <input type="checkbox" class="fee-checkbox mt-1 text-indigo-600 focus:ring-indigo-500 rounded border-slate-300" value="<?= $fee['id'] ?>">
                            <div>
                                <div class="font-bold text-sm text-slate-800"><?= htmlspecialchars($fee['name']) ?></div>
                                <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                                    <span class="uppercase tracking-wider text-[0.625rem] font-black text-slate-400 bg-slate-100 px-2 py-0.5 rounded"><?= $fee['fee_type'] ?></span>
                                </div>
                            </div>
                        </div>
                    </label>
                    <?php endwhile; ?>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 flex justify-end gap-4">
                <a href="view_semester_bills.php" class="px-6 py-3 bg-white border border-slate-200 text-slate-600 font-black text-[0.625rem] uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all">Cancel</a>
                <button type="submit" class="bg-indigo-600 text-white font-black text-[0.625rem] uppercase tracking-widest px-8 py-3 rounded-xl shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all leading-none">
                    Preview Batch Bill
                </button>
            </div>
        </form>
    </main>

    <script>
        function prepareSubmit() {
            const checkboxes = document.querySelectorAll('.fee-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('selectedFeesInput').value = ids.join(',');
            
            if (ids.length === 0) {
                alert("Please select at least one fee to include in the bill.");
                event.preventDefault();
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
