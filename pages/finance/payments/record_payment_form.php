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
        .mode-card { cursor: pointer; border: 2px solid #f1f5f9; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .mode-card.active { border-color: #10b981; background-color: #f0fdf4; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.1); }
        .mode-card.active .icon-box { background-color: #10b981; color: white; }
        .fee-item { cursor: pointer; transition: all 0.2s; border: 1px solid #f1f5f9; }
        .fee-item:hover { border-color: #10b981; transform: translateX(4px); }
        .fee-item.selected { border-color: #10b981; background-color: #f0fdf4; }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex justify-between items-end">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Remittance Intake
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Record <span class="text-indigo-600">Payment</span></h1>
                <p class="text-slate-500 mt-2 font-medium">Verify credentials and process financial entries into the institutional ledger.</p>
            </div>
            <div class="flex gap-4">
                <a href="view_payments.php" class="bg-white border border-slate-200 text-slate-600 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-50 transition-all leading-none">
                    <i class="fas fa-history mr-2"></i> Audit History
                </a>
            </div>
        </header>

        <form action="record_payment.php" method="POST" id="paymentForm">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                
                <!-- Left: Intake Context -->
                <div class="lg:col-span-12 xl:col-span-7 space-y-10">
                    
                    <!-- Type Selection -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                        <div onclick="setMode('student')" id="mode-student" class="mode-card bg-white p-6 rounded-[2rem] active flex items-center gap-6">
                            <div class="icon-box w-14 h-14 bg-slate-50 flex items-center justify-center rounded-2xl text-slate-400 text-xl transition-all">
                                <i class="fas fa-user-graduate"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">Student Bill</h4>
                                <p class="text-[10px] text-slate-400 font-bold italic">Fees & Arrears settlement</p>
                            </div>
                            <input type="radio" name="payment_mode" value="student" id="radio-student" class="hidden" checked>
                        </div>
                        <div onclick="setMode('general')" id="mode-general" class="mode-card bg-white p-6 rounded-[2rem] flex items-center gap-6">
                            <div class="icon-box w-14 h-14 bg-slate-50 flex items-center justify-center rounded-2xl text-slate-400 text-xl transition-all">
                                <i class="fas fa-vault"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-1">General Entry</h4>
                                <p class="text-[10px] text-slate-400 font-bold italic">Miscellaneous school income</p>
                            </div>
                            <input type="radio" name="payment_mode" value="general" id="radio-general" class="hidden">
                        </div>
                    </div>

                    <!-- Target Selection -->
                    <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm relative overflow-hidden h-fit">
                        <div id="student-selector">
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">01. Entity Verification</h3>
                            <div class="relative">
                                <select name="student_id" id="student_id" class="w-full px-8 py-5 bg-slate-50 border border-slate-100 rounded-2xl font-black text-slate-900 outline-none focus:ring-2 focus:ring-emerald-500 appearance-none text-sm">
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
                            <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">01. Income Classification</h3>
                            <select name="fee_id" class="w-full px-8 py-5 bg-slate-50 border border-slate-100 rounded-2xl font-black text-slate-900 outline-none focus:ring-2 focus:ring-indigo-500 appearance-none text-sm">
                                <option value="">None - Direct General Payment</option>
                                <?php foreach($fee_options as $f): ?>
                                    <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Real-time Balance Matrix -->
                        <div id="balance-matrix" class="mt-8 hidden animate-fade-in">
                             <div class="grid grid-cols-3 gap-4">
                                <div class="bg-indigo-50/50 p-6 rounded-3xl border border-indigo-100/50">
                                    <p class="text-[8px] font-black text-indigo-400 uppercase tracking-widest mb-1">Total Due</p>
                                    <p class="text-xl font-black text-indigo-900" id="matrix-total">₵0.00</p>
                                </div>
                                <div class="bg-emerald-50/50 p-6 rounded-3xl border border-emerald-100/50">
                                    <p class="text-[8px] font-black text-emerald-400 uppercase tracking-widest mb-1">Settled</p>
                                    <p class="text-xl font-black text-emerald-900" id="matrix-paid">₵0.00</p>
                                </div>
                                <div class="bg-rose-50/50 p-6 rounded-3xl border border-rose-100/50">
                                    <p class="text-[8px] font-black text-rose-400 uppercase tracking-widest mb-1">Exposure</p>
                                    <p class="text-xl font-black text-rose-900" id="matrix-exposure">₵0.00</p>
                                </div>
                             </div>

                             <div class="mt-8 flex items-center justify-between p-4 bg-slate-900 rounded-2xl">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-emerald-400 text-xs">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Projected Exposure Post-Payment</p>
                                </div>
                                <p class="text-sm font-black text-emerald-400" id="matrix-projected">₵0.00</p>
                             </div>
                        </div>
                    </div>

                    <!-- Outstanding Tranches -->
                    <div id="tranches-section" class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm hidden">
                         <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">02. Pending Tranche Allocation</h3>
                         <div id="tranches-list" class="space-y-3">
                            <!-- Populated by JS -->
                         </div>
                    </div>
                </div>

                <!-- Right: Fiscal Parameters -->
                <div class="lg:col-span-12 xl:col-span-5 space-y-10">
                    <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">03. Fiscal Attributes</h3>
                        <div class="space-y-6">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block italic">Remittance Value (GHS)</label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="amount" id="amount-input" required class="w-full px-12 py-5 bg-slate-900 border border-slate-800 rounded-2xl font-black text-2xl text-emerald-400 outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all leading-none">
                                    <span class="absolute left-6 top-1/2 -translate-y-1/2 font-black text-emerald-900">₵</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Semester</label>
                                    <select name="semester" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 appearance-none">
                                        <?php foreach($available_terms as $t): ?>
                                            <option value="<?= htmlspecialchars($t) ?>" <?= $t === $selected_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Maturity Year</label>
                                    <select name="academic_year" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 appearance-none">
                                        <?php foreach($year_options as $y): ?>
                                            <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Intake Date</label>
                                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Audit Token / Receipt No</label>
                                    <input type="text" name="receipt_no" placeholder="Optional..." class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Remittance Narrative</label>
                                <textarea name="description" rows="3" placeholder="Explain the purpose of this entry..." class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-5 py-4 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                            </div>
                        </div>

                        <button type="submit" class="w-full mt-10 bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-[0.25em] py-5 rounded-2xl shadow-xl shadow-emerald-900/10 transition-all active:scale-95 flex items-center justify-center gap-3">
                            <i class="fas fa-shield-check text-base"></i> Sync to Repository
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Remittance Node &middot; Salba Montessori &middot; v9.5.0
        </footer>
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
                    row.className = 'fee-item bg-white p-4 rounded-2xl flex items-center justify-between group';
                    row.onclick = () => {
                        document.getElementById('amount-input').value = parseFloat(f.amount).toFixed(2);
                        document.querySelectorAll('.fee-item').forEach(i => i.classList.remove('selected'));
                        row.classList.add('selected');
                        calcProjected();
                    };
                    row.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-[10px] font-black text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-500 transition-colors">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-800 uppercase leading-none mb-1">${f.fee_name}</p>
                                <p class="text-[8px] font-bold text-slate-400 uppercase tracking-widest">${f.due_date}</p>
                            </div>
                        </div>
                        <p class="text-xs font-black text-slate-900 leading-none">₵${parseFloat(f.amount).toFixed(2)}</p>
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
