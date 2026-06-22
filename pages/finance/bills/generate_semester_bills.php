<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/semester_helpers.php';

if (!is_logged_in()) {
    header('Location: ../../../login');
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
            backdrop-filter: blur(0.75rem);
            border: 0.0625rem solid rgba(255, 255, 255, 0.3);
        }
        .fee-card.selected {
            border-color: #10b981;
            background-color: #F0FDF4;
            transform: translateY(-0.25rem);
            box-shadow: 0 0.625rem 0.9375rem -0.1875rem rgba(16, 185, 129, 0.1);
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
    <?php include '../../../includes/sidebar.php'; ?>
    
    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_semester_bills.php" class="hover:text-blue-600 transition-colors">Billing Center</a>
                <span>/</span>
                <span class="text-blue-600">Billing Initialization</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-file-invoice text-emerald-600"></i> Prepare Semester Bills
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Initialize the billing cycle by selecting fee categories to append to student ledgers.</p>
                </div>
                <a href="view_semester_bills.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-times text-slate-400"></i> Cancel
                </a>
            </div>
        </div>

        <form method="POST" action="process_semester_bills.php" id="billingForm">
            
            <!-- Context Selection Grid -->
            <div class="px-6 grid grid-cols-1 lg:grid-cols-3 gap-6 mb-12">
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Configurations -->
                    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg border border-emerald-100">
                                <i class="fas fa-sliders"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Target Session</h3>
                                <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Define the temporal context for this bill</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Semester</label>
                                <select name="semester" id="semester" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium appearance-none" required>
                                    <option value="">Select Semester</option>
                                    <option value="First Semester" <?= getCurrentSemester($conn) === 'First Semester' ? 'selected' : '' ?>>First Semester</option>
                                    <option value="Second Semester" <?= getCurrentSemester($conn) === 'Second Semester' ? 'selected' : '' ?>>Second Semester</option>
                                    <option value="Third Semester" <?= getCurrentSemester($conn) === 'Third Semester' ? 'selected' : '' ?>>Third Semester</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Academic Year</label>
                                <select name="academic_year" id="academic_year" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium appearance-none" required>
                                    <?php foreach ($year_options as $yr): ?>
                                        <option value="<?= htmlspecialchars($yr) ?>" <?= ($yr === $default_academic_year) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(formatAcademicYearDisplay($conn, $yr)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Deadline Date</label>
                                <input type="date" name="due_date" id="due_date" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all font-medium" required>
                            </div>
                        </div>
                    </div>

                    <!-- Fee Selection Area -->
                    <div>
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-4">
                            <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wider flex items-center gap-3">
                                Available Fee Categories <span class="w-8 h-px bg-slate-200"></span>
                            </h3>
                            <button type="button" onclick="selectAllFees()" class="text-[10px] font-semibold text-emerald-600 uppercase tracking-wider hover:bg-emerald-50 px-2 py-1 rounded transition-all border border-emerald-200">Select All</button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php 
                            $fees_result->data_seek(0);
                            while ($fee = $fees_result->fetch_assoc()): 
                                $isFixed = ($fee['fee_type'] === 'fixed');
                                $colorClass = $isFixed ? 'emerald' : 'indigo';
                            ?>
                            <div class="fee-card bg-white p-5 rounded-xl border border-slate-200 cursor-pointer hover:border-emerald-400 transition-all duration-300 relative group shadow-sm" data-fee-id="<?= $fee['id'] ?>">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-<?= $colorClass ?>-50 text-<?= $colorClass ?>-600 flex items-center justify-center text-sm border border-<?= $colorClass ?>-100">
                                            <i class="fas fa-<?= $isFixed ? 'bookmark' : 'tags' ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="text-sm font-semibold text-slate-900 leading-tight"><?= htmlspecialchars($fee['name']) ?></h5>
                                            <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500"><?= strtoupper($fee['fee_type']) ?></span>
                                        </div>
                                    </div>
                                    <div class="check-icon opacity-0 transform scale-50 transition-all duration-300 text-emerald-500 text-lg">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                </div>
                                <div class="flex items-baseline gap-1 mt-4">
                                    <span class="text-xs font-medium text-slate-500">GHS</span>
                                    <span class="text-xl font-bold text-slate-900 tracking-tight">
                                        <?= $isFixed ? number_format($fee['amount'], 2) : '---' ?>
                                    </span>
                                    <?php if (!$isFixed): ?>
                                        <span class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wider ml-1">Tiered Rate</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($fee['amount_details']): ?>
                                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2 overflow-hidden">
                                        <i class="fas fa-info-circle text-[10px] text-slate-400"></i>
                                        <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider truncate">
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
                    <div class="bg-slate-900 rounded-xl p-6 text-white relative overflow-hidden shadow-lg border border-slate-800">
                        <div class="absolute top-0 right-0 p-6 opacity-10">
                            <i class="fas fa-users text-6xl"></i>
                        </div>
                        <div class="relative z-10">
                            <h3 class="text-base font-semibold mb-4">Audience Filter</h3>
                            <label class="block text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Apply Process To:</label>
                            
                            <select name="class_filter" id="class_filter" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-1 focus:ring-emerald-500 transition-all appearance-none mb-6">
                                <option value="all">Entire Active Population</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($class['name']) ?>"><?= htmlspecialchars($class['name']) ?></option>
                                <?php endwhile; ?>
                            </select>

                            <div class="space-y-3 pt-4 border-t border-slate-800">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400">Mode</span>
                                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-400">Bulk Execution</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-slate-400">Selected Fees</span>
                                    <span id="summaryFeeCount" class="text-sm font-bold">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-5 rounded-xl border border-blue-100">
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600 shrink-0 border border-blue-200">
                                <i class="fas fa-shield-halved"></i>
                            </div>
                            <div>
                                <h5 class="text-sm font-semibold text-blue-900 mb-1">Integrity Policy</h5>
                                <p class="text-xs text-blue-800 leading-relaxed">System skips duplicates. Arrears will be calculated and appended automatically based on current student balances.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="selected_fees" id="selected_fees_input" value="">
            
            <!-- Sticky Execution Bar -->
            <div id="executionBar" class="fixed bottom-6 left-6 lg:left-80 right-6 bg-white shadow-lg border border-slate-200 p-4 rounded-xl transform translate-y-32 transition-transform duration-500 z-50 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-lg bg-slate-900 text-white flex items-center justify-center text-lg shadow-sm">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 text-rose-600 font-semibold text-[10px] uppercase tracking-wider mb-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                            Ready for Billing
                        </div>
                        <p class="text-sm font-medium text-slate-700">Initialize <span id="barFeeCount" class="text-emerald-600 font-bold">0</span> fees for <span id="barClassTarget" class="text-indigo-600 font-bold">Global Population</span></p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="window.history.back()" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all border border-transparent hover:border-slate-200">Cancel</button>
                    <button type="submit" id="submitBtn" disabled class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm px-6 py-2 rounded-lg shadow-sm transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        Deploy Billings <i class="fas fa-arrow-right text-xs"></i>
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
