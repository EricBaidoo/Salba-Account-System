<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_check.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';

// Get semester and year
$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$available_terms = getAvailableSemesters($conn);

// Build Academic Year options
$year_options = [];
$yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
if ($yrs_rs) {
    while ($yr = $yrs_rs->fetch_assoc()) {
        if (!empty($yr['academic_year'])) $year_options[] = $yr['academic_year'];
    }
}
if (!in_array($academic_year, $year_options, true)) array_unshift($year_options, $academic_year);

// Parameters for pre-filling
$pre_student_id = intval($_GET['student_id'] ?? 0);
$pre_amount = floatval($_GET['amount'] ?? 0);
$selected_term = isset($_GET['semester']) ? trim($_GET['semester']) : $current_term;
$selected_academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : $academic_year;

// Fetch active students
$students_rs = $conn->query("SELECT id, first_name, last_name, class FROM students WHERE status = 'active' ORDER BY class, first_name, last_name");

// Fetch fee options
$fees_rs = $conn->query("SELECT id, name FROM fees ORDER BY name");
$fee_options = [];
while ($row = $fees_rs->fetch_assoc()) $fee_options[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Remittance | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .mode-card { cursor: pointer; border: 0.125rem solid #f1f5f9; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .mode-card.active { border-color: #10b981; background-color: #f0fdf4; box-shadow: 0 0.625rem 1.875rem rgba(16, 185, 129, 0.1); }
        .mode-card.active .icon-box { background-color: #10b981; color: white; }
        .fee-item { cursor: pointer; transition: all 0.2s; border: 0.0625rem solid #f1f5f9; }
        .fee-item:hover { border-color: #10b981; transform: translateX(0.25rem); }
        .fee-item.selected { border-color: #10b981; background-color: #f0fdf4; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen pb-12">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="view_payments.php" class="hover:text-blue-600 transition-colors">Payment Ledger</a>
                <span>/</span>
                <span class="text-blue-600">Record Payment</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-file-invoice-dollar text-indigo-600"></i> Record Payment
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Verify credentials and process financial entries into the institutional ledger.</p>
                </div>
                <div class="flex gap-3">
                    <a href="view_payments.php" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                        <i class="fas fa-history"></i> Audit History
                    </a>
                </div>
            </div>
        </div>

        <div class="px-6">

        <form action="record_payment.php" method="POST" id="paymentForm">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                
                <!-- Left: Intake Context -->
                <div class="lg:col-span-12 xl:col-span-7 space-y-6">
                    
                    <!-- Type Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div onclick="setMode('student')" id="mode-student" class="mode-card bg-white p-5 rounded-xl active flex items-center gap-4 shadow-sm border border-slate-200">
                            <div class="icon-box w-10 h-10 bg-slate-50 flex items-center justify-center rounded-lg text-slate-400 text-lg transition-all border border-slate-100">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 mb-0.5">Student Bill</h4>
                                <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Fees & Arrears settlement</p>
                            </div>
                            <input type="radio" name="payment_mode" value="student" id="radio-student" class="hidden" checked>
                        </div>
                        <div onclick="setMode('general')" id="mode-general" class="mode-card bg-white p-5 rounded-xl flex items-center gap-4 shadow-sm border border-slate-200">
                            <div class="icon-box w-10 h-10 bg-slate-50 flex items-center justify-center rounded-lg text-slate-400 text-lg transition-all border border-slate-100">
                                <i class="fas fa-vault"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-slate-900 mb-0.5">General Entry</h4>
                                <p class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Miscellaneous school income</p>
                            </div>
                            <input type="radio" name="payment_mode" value="general" id="radio-general" class="hidden">
                        </div>
                    </div>

                    <!-- Target Selection -->
                    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm relative overflow-hidden h-fit">
                        <div id="student-selector">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2"><i class="fas fa-search text-slate-400"></i> Entity Verification</h3>
                            <div class="relative">
                                <select name="student_id" id="student_id" class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-lg font-medium text-slate-900 outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 appearance-none text-sm transition-all">
                                    <option value="">Search Student Repository...</option>
                                    <?php while($s = $students_rs->fetch_assoc()): ?>
                                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $pre_student_id ? 'selected' : '' ?> data-class="<?= htmlspecialchars($s['class']) ?>">
                                            <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?> (<?= htmlspecialchars($s['class']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>

                        <div id="general-selector" class="hidden">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2"><i class="fas fa-tag text-slate-400"></i> Income Classification</h3>
                            <select name="fee_id" class="w-full px-4 py-3 bg-slate-50 border border-slate-300 rounded-lg font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 appearance-none text-sm transition-all">
                                <option value="">None - Direct General Payment</option>
                                <?php foreach($fee_options as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Real-time Balance Matrix -->
                        <div id="balance-matrix" class="mt-6 hidden animate-fade-in border-t border-slate-100 pt-6">
                             <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="bg-indigo-50/50 p-4 rounded-lg border border-indigo-100/50 text-center md:text-left">
                                    <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider mb-1">Total Due</p>
                                    <p class="text-lg font-bold text-indigo-900" id="matrix-total">₵0.00</p>
                                </div>
                                <div class="bg-emerald-50/50 p-4 rounded-lg border border-emerald-100/50 text-center md:text-left">
                                    <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider mb-1">Settled</p>
                                    <p class="text-lg font-bold text-emerald-900" id="matrix-paid">₵0.00</p>
                                </div>
                                <div class="bg-rose-50/50 p-4 rounded-lg border border-rose-100/50 text-center md:text-left">
                                    <p class="text-[10px] font-bold text-rose-500 uppercase tracking-wider mb-1">Exposure</p>
                                    <p class="text-lg font-bold text-rose-900" id="matrix-exposure">₵0.00</p>
                                </div>
                             </div>

                             <div class="mt-4 flex flex-col md:flex-row items-center justify-between gap-3 p-4 bg-slate-50 rounded-lg border border-slate-200">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-chart-line text-emerald-500"></i>
                                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Projected Exposure Post-Payment</p>
                                </div>
                                <p class="text-base font-bold text-emerald-600" id="matrix-projected">₵0.00</p>
                             </div>
                        </div>
                    </div>

                    <!-- Outstanding Tranches -->
                    <div id="tranches-section" class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm hidden">
                         <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-4 flex items-center gap-2"><i class="fas fa-layer-group text-slate-400"></i> Pending Tranche Allocation</h3>
                         <div id="tranches-list" class="space-y-2">
                            <!-- Populated by JS -->
                         </div>
                    </div>
                </div>

                <!-- Right: Fiscal Parameters -->
                <div class="lg:col-span-12 xl:col-span-5 space-y-6">
                    <div class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="fas fa-sliders-h text-slate-400"></i> Fiscal Attributes</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Remittance Value (GHS)</label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="amount" id="amount-input" required class="w-full px-10 py-3 bg-white border border-slate-300 rounded-lg font-bold text-xl text-emerald-600 outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-all leading-none">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-500">₵</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Semester</label>
                                    <select name="semester" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                                        <?php foreach($available_terms as $t): ?>
                                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $selected_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Maturity Year</label>
                                    <select name="academic_year" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 appearance-none">
                                        <?php foreach($year_options as $y): ?>
                                            <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Intake Date</label>
                                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Audit Token / Receipt No</label>
                                    <input type="text" name="receipt_no" placeholder="Optional..." class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Remittance Narrative</label>
                                <textarea name="description" rows="3" placeholder="Explain the purpose of this entry..." class="w-full bg-white border border-slate-300 rounded-lg px-4 py-3 text-sm font-medium outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="w-full mt-6 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm py-3 rounded-lg shadow-sm transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-shield-check text-base"></i> Sync to Repository
                        </button>
                    </div>
                </div>
            </div>
        </form>

        </div>
    </main>

    <script>
        let currentMode = 'student';
        let studentBalance = 0;

        function setMode(mode) {
            currentMode = mode;
            document.querySelectorAll('.mode-card').forEach(c => c.classList.remove('active'));
            document.getElementById('mode-' + mode).classList.add('active');
            document.getElementById('radio-' + mode).checked = true;

            const studentWrap = document.getElementById('student-selector');
            const generalWrap = document.getElementById('general-selector');
            const balanceMatrix = document.getElementById('balance-matrix');
            const tranches = document.getElementById('tranches-section');

            if (mode === 'student') {
                studentWrap.classList.remove('hidden');
                generalWrap.classList.add('hidden');
                if (document.getElementById('student_id').value) {
                    balanceMatrix.classList.remove('hidden');
                    tranches.classList.remove('hidden');
                }
            } else {
                studentWrap.classList.add('hidden');
                generalWrap.classList.remove('hidden');
                balanceMatrix.classList.add('hidden');
                tranches.classList.add('hidden');
            }
        }

        document.getElementById('student_id').addEventListener('change', function() {
            const sid = this.value;
            if (sid) {
                fetch(`../../includes/get_student_balance_ajax.php?student_id=${sid}`)
                    .then(r => r.json())
                    .then(data => {
                        updateMatrix(data);
                    });
            } else {
                document.getElementById('balance-matrix').classList.add('hidden');
                document.getElementById('tranches-section').classList.add('hidden');
            }
        });

        function updateMatrix(data) {
            studentBalance = data.balance.outstanding_fees;
            document.getElementById('matrix-total').textContent = '₵' + data.balance.total_fees.toFixed(2);
            document.getElementById('matrix-paid').textContent = '₵' + data.balance.total_payments.toFixed(2);
            document.getElementById('matrix-exposure').textContent = '₵' + studentBalance.toFixed(2);
            document.getElementById('balance-matrix').classList.remove('hidden');

            const list = document.getElementById('tranches-list');
            list.innerHTML = '';
            if (data.fees && data.fees.length > 0) {
                data.fees.forEach(f => {
                    const row = document.createElement('div');
                    row.className = 'fee-item bg-slate-50 p-3 rounded-lg flex items-center justify-between group border border-slate-200 hover:bg-white transition-colors';
                    row.onclick = () => {
                        document.getElementById('amount-input').value = parseFloat(f.amount).toFixed(2);
                        document.querySelectorAll('.fee-item').forEach(i => i.classList.remove('selected'));
                        row.classList.add('selected');
                        calcProjected();
                    };
                    row.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-md bg-white border border-slate-200 flex items-center justify-center text-xs font-bold text-slate-400 group-hover:border-emerald-200 group-hover:text-emerald-500 transition-colors">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-800 uppercase leading-none mb-1">${f.fee_name}</p>
                                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">${f.due_date}</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold text-slate-900 leading-none">₵${parseFloat(f.amount).toFixed(2)}</p>
                    `;
                    list.appendChild(row);
                });
                document.getElementById('tranches-section').classList.remove('hidden');
            } else {
                document.getElementById('tranches-section').classList.add('hidden');
            }
            calcProjected();
        }

        document.getElementById('amount-input').addEventListener('input', calcProjected);

        function calcProjected() {
            const paid = parseFloat(document.getElementById('amount-input').value) || 0;
            const projected = Math.max(0, studentBalance - paid);
            document.getElementById('matrix-projected').textContent = '₵' + projected.toFixed(2);
        }

        <?php if ($pre_student_id): ?>
            setMode('student');
            document.getElementById('student_id').dispatchEvent(new Event('change'));
            <?php if ($pre_amount): ?>
                document.getElementById('amount-input').value = "<?= number_format($pre_amount, 2, '.', '') ?>";
            <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>
