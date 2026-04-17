<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../includes/login.php');
    exit;
}
require_finance_access();

// Fetch fees and classes for the form
$fees_query = "
    SELECT f.id, f.name, f.amount, f.fee_type,
           GROUP_CONCAT(
               CASE 
                   WHEN fa.class_name IS NOT NULL THEN CONCAT(fa.class_name, ':GH₵', FORMAT(fa.amount, 2))
                   WHEN fa.category IS NOT NULL THEN CONCAT(
                       CASE fa.category 
                           WHEN 'early_years' THEN 'Early Years'
                           WHEN 'primary' THEN 'Primary'
                       END, ':GH₵', FORMAT(fa.amount, 2)
                   )
               END
               ORDER BY fa.amount
               SEPARATOR ' | '
           ) as amount_details
    FROM fees f
    LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
    GROUP BY f.id, f.name, f.amount, f.fee_type
    ORDER BY f.name";
$fees_result = $conn->query($fees_query);

$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");

// Academic year options
$default_academic_year = getAcademicYear($conn);
$year_options = [];
$yrs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs) {
    while ($yr = $yrs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) {
            $year_options[] = $yr['academic_year'];
        }
    }
    $yrs->close();
}
if (!in_array($default_academic_year, $year_options, true)) {
    array_unshift($year_options, $default_academic_year);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Semester Bills | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .fee-card.selected {
            border-color: #10b981;
            background-color: #F0FDF4;
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.1);
        }
        .fee-card.selected .check-icon {
            opacity: 1;
            transform: scale(1);
        }
        .header-gradient {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar_admin.php'; ?>
    
    <main class="ml-72 p-10">
        <!-- Breadcrumbs & Nav -->
        <nav class="flex items-center justify-between mb-12">
            <div class="flex items-center gap-4">
                <a href="../dashboard.php" class="w-10 h-10 rounded-full bg-white shadow-sm border border-slate-200 flex items-center justify-center text-slate-500 hover:text-emerald-600 transition-all">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Finance Hub</p>
                    <h4 class="text-sm font-bold text-slate-700">Billing Initialization</h4>
                </div>
            </div>
            <a href="view_semester_bills.php" class="bg-white px-6 py-2.5 rounded-xl shadow-sm border border-slate-200 text-sm font-bold text-slate-600 hover:bg-slate-50 transition-all flex items-center gap-2">
                <i class="fas fa-file-invoice text-emerald-500"></i> Open Billing Center
            </a>
        </nav>

        <!-- Main Header -->
        <header class="mb-12 relative">
            <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-4">
                <span class="w-8 h-[2px] bg-emerald-600"></span>
                Billing Preparation
            </div>
            <h1 class="text-4xl font-black text-slate-900 tracking-tight">Prepare <span class="text-emerald-600">Semester Bills</span></h1>
            <p class="text-slate-500 mt-2 font-medium max-w-2xl">Initialize the billing cycle by selecting specific fee categories to be appended to student ledgers for the active term.</p>
        </header>

        <form method="POST" action="process_semester_bills.php" id="billingForm">
            
            <!-- Context Selection Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
                <div class="lg:col-span-2 space-y-8">
                    <!-- Basic Configurations -->
                    <div class="glass-card p-10 rounded-[2.5rem] shadow-sm">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                                <i class="fas fa-sliders"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900">Target Session</h3>
                                <p class="text-xs font-medium text-slate-400 uppercase tracking-wider">Define the temporal context for this bill</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Semester</label>
                                <select name="semester" id="semester" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all appearance-none" required>
                                    <option value="">Select Semester</option>
                                    <option value="First Semester" <?= getCurrentSemester($conn) === 'First Semester' ? 'selected' : '' ?>>First Semester</option>
                                    <option value="Second Semester" <?= getCurrentSemester($conn) === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option>
                                    <option value="Third Semester" <?= getCurrentSemester($conn) === 'Third Semester' ? 'selected' : '' ?>>Third Semester</option>
                                </select>
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Academic Year</label>
                                <select name="academic_year" id="academic_year" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all appearance-none" required>
                                    <?php foreach ($year_options as $yr): ?>
                                        <option value="<?= htmlspecialchars($yr) ?>" <?= ($yr === $default_academic_year) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Deadline Date</label>
                                <input type="date" name="due_date" id="due_date" class="w-full bg-slate-50/50 border border-slate-200 rounded-2xl px-5 py-3.5 text-sm font-bold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all" required>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Selection Area -->
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em] flex items-center gap-3">
                                Available Fee Categories <span class="w-12 h-[1px] bg-slate-200"></span>
                            </h3>
                            <button type="button" onclick="selectAllFees()" class="text-[10px] font-black text-emerald-600 uppercase tracking-widest hover:bg-emerald-50 px-3 py-1 rounded-full transition-all">Select All</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            $fees_result->data_seek(0);
                            while ($fee = $fees_result->fetch_assoc()): 
                                $isFixed = ($fee['fee_type'] === 'fixed');
                                $colorClass = $isFixed ? 'emerald' : 'indigo';
                            ?>
                            <div class="fee-card glass-card p-6 rounded-[2.5rem] cursor-pointer hover:border-emerald-500/50 transition-all duration-300 relative group" data-fee-id="<?= $fee['id'] ?>">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-<?= $colorClass ?>-50 text-<?= $colorClass ?>-600 flex items-center justify-center text-lg">
                                            <i class="fas fa-<?= $isFixed ? 'bookmark' : 'tags' ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-black text-slate-900 leading-tight"><?= htmlspecialchars($fee['name']) ?></h5>
                                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400"><?= strtoupper($fee['fee_type']) ?></span>
                                        </div>
                                    </div>
                                    <div class="check-icon opacity-0 transform scale-50 transition-all duration-300 text-emerald-500 text-xl">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="flex items-baseline gap-1 mt-6">
                                    <span class="text-xs font-bold text-slate-400">GHS</span>
                                    <span class="text-2xl font-black text-slate-900 tracking-tight">
                                        <?= $isFixed ? number_format($fee['amount'], 2) : '---' ?>
                                    </span>
                                    <?php if (!$isFixed): ?>
                                        <span class="text-[10px] font-black text-indigo-500 uppercase tracking-widest ml-1">Tiered Rate</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($fee['amount_details']): ?>
                                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center gap-2 overflow-hidden">
                                        <i class="fas fa-info-circle text-[10px] text-slate-300"></i>
                                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest truncate">
                                            <?= htmlspecialchars($fee['amount_details']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar: Filters & Summary -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-slate-900 rounded-[2.5rem] p-8 text-white relative overflow-hidden shadow-2xl">
                        <div class="absolute top-0 right-0 p-8 opacity-10">
                            <i class="fas fa-users text-8xl"></i>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-lg font-black mb-6">Audience Filter</h3>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Apply Process To:</label>
                            
                            <select name="class_filter" id="class_filter" class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-4 text-sm font-bold text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-all appearance-none mb-6">
                                <option value="all" class="text-slate-900">Entire Active Population</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($class['name']) ?>" class="text-slate-900"><?= htmlspecialchars($class['name']) ?></option>
                                <?php endwhile; ?>
                            </select>

                            <div class="space-y-4 pt-6 border-t border-white/10">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-slate-400">Mode</span>
                                    <span class="text-xs font-black uppercase tracking-widest text-emerald-400">Bulk Execution</span>
                                </div>
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-xs font-bold text-slate-400">Selected Fees</span>
                                    <span id="summaryFeeCount" class="font-black">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="glass-card p-6 rounded-[2rem] border-dashed border-slate-200">
                        <div class="flex gap-4 items-start">
                            <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white shrink-0">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <div>
                                <h5 class="text-sm font-bold text-slate-900 mb-1">Integrity Policy</h5>
                                <p class="text-xs text-slate-500 font-medium leading-relaxed">System will skip duplicates. Arrears will be calculated and appended automatically based on current student balances.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="selected_fees" id="selected_fees_input" value="">
            
            <!-- Sticky Execution Bar -->
            <div id="executionBar" class="fixed bottom-10 left-[22rem] right-10 bg-white shadow-2xl shadow-indigo-500/20 border border-slate-100 p-6 rounded-3xl transform translate-y-32 transition-transform duration-500 z-50 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="w-14 h-14 rounded-2xl bg-slate-900 text-white flex items-center justify-center text-2xl shadow-xl shadow-slate-900/20">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 text-rose-600 font-black text-[10px] uppercase tracking-widest mb-1">
                            <span class="w-2 h-2 rounded-full bg-rose-600 animate-pulse"></span>
                            System Ready for Billing
                        </div>
                        <p class="text-sm font-bold text-slate-700">Initialize <span id="barFeeCount" class="text-emerald-600 font-extrabold">0</span> fees for <span id="barClassTarget" class="text-indigo-600 font-extrabold">Global Population</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button type="button" onclick="window.history.back()" class="px-6 py-4 rounded-xl text-xs font-black uppercase tracking-widest text-slate-400 hover:text-slate-600 transition-all">Cancel</button>
                    <button type="submit" id="submitBtn" disabled class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs uppercase tracking-[0.2em] px-10 py-4 rounded-2xl shadow-xl shadow-emerald-500/30 transition-all flex items-center gap-4 disabled:opacity-50 disabled:grayscale">
                        Deploy Billings <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        const selectedFees = new Set();
        const feeCards = document.querySelectorAll('.fee-card');
        const semesterSelect = document.getElementById('semester');
        const dueDateInput = document.getElementById('due_date');
        const classFilter = document.getElementById('class_filter');
        const yearSelect = document.getElementById('academic_year');
        const submitBtn = document.getElementById('submitBtn');
        const executionBar = document.getElementById('executionBar');

        feeCards.forEach(card => {
            card.addEventListener('click', () => {
                const id = card.dataset.feeId;
                if (selectedFees.has(id)) {
                    selectedFees.delete(id);
                    card.classList.remove('selected');
                } else {
                    selectedFees.add(id);
                    card.classList.add('selected');
                }
                updateForm();
            });
        });

        function selectAllFees() {
            feeCards.forEach(card => {
                selectedFees.add(card.dataset.feeId);
                card.classList.add('selected');
            });
            updateForm();
        }

        function updateForm() {
            const feeCount = selectedFees.size;
            document.getElementById('selected_fees_input').value = Array.from(selectedFees).join(',');
            document.getElementById('summaryFeeCount').textContent = feeCount;
            document.getElementById('barFeeCount').textContent = feeCount;
            
            const classTarget = classFilter.value === 'all' ? 'Global Population' : classFilter.value;
            document.getElementById('barClassTarget').textContent = classTarget;

            const isReady = feeCount > 0 && semesterSelect.value && dueDateInput.value && yearSelect.value;
            submitBtn.disabled = !isReady;

            if (isReady) {
                executionBar.classList.remove('translate-y-32');
            } else {
                executionBar.classList.add('translate-y-32');
            }
        }

        [semesterSelect, dueDateInput, classFilter, yearSelect].forEach(el => {
            el.addEventListener('change', updateForm);
        });

        // Set min date for due date
        dueDateInput.min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
