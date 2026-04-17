<?php 
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/student_balance_functions.php';
require_once '../../../includes/semester_helpers.php';

$student_id = intval($_GET['id'] ?? 0);
$current_term = getCurrentSemester($conn);
$selected_term = $_GET['semester'] ?? $current_term;
$default_academic_year = getAcademicYear($conn);
$selected_academic_year = $_GET['academic_year'] ?? $default_academic_year;
$display_academic_year = formatAcademicYearDisplay($conn, $selected_academic_year);

if ($student_id === 0) {
    header('Location: student_balances.php');
    exit;
}

ensureArrearsAssignment($conn, $student_id, $selected_term, $selected_academic_year);
$student_balance = getStudentBalance($conn, $student_id, $selected_term, $selected_academic_year);
if (!$student_balance) {
    header('Location: student_balances.php');
    exit;
}

$term_fees = getStudentSemesterFees($conn, $student_id, $selected_term, $selected_academic_year);
$payment_history = getStudentPaymentHistory($conn, $student_id, $selected_term, $selected_academic_year);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($student_balance['student_name']) ?> | Individual Ledger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .ledger-row { transition: all 0.2s; }
        .ledger-row:hover { background-color: #f1f5f9; transform: scale(1.002); }
    </style>
</head>
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-indigo-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-indigo-600"></span>
                    Individual Audit
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight italic"><?= htmlspecialchars($student_balance['student_name']) ?></h1>
                <div class="flex items-center gap-3 mt-3">
                    <span class="bg-indigo-50 text-indigo-600 font-black text-[10px] uppercase tracking-widest px-3 py-1 rounded-lg border border-indigo-100"><?= htmlspecialchars($student_balance['class']) ?></span>
                    <span class="bg-slate-100 text-slate-500 font-black text-[10px] uppercase tracking-widest px-3 py-1 rounded-lg">UID: SMS-<?= str_pad($student_id, 3, '0', STR_PAD_LEFT) ?></span>
                </div>
            </div>
            <div class="flex gap-4">
                <a href="../bills/semester_bill.php?student_id=<?= $student_id ?>&semester=<?= urlencode($selected_term) ?>&academic_year=<?= urlencode($selected_academic_year) ?>" class="bg-slate-900 text-white font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-slate-800 transition-all leading-none">
                    <i class="fas fa-print mr-2"></i> View Bill
                </a>
                <a href="../payments/record_payment_form.php?student_id=<?= $student_id ?>" class="bg-emerald-600 text-white font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:bg-emerald-500 transition-all leading-none">
                    <i class="fas fa-plus mr-2"></i> Record Intake
                </a>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Left: Controls & Stats -->
            <div class="lg:col-span-12 xl:col-span-4 space-y-10">
                <!-- Context Switcher -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8">Audit Period Scope</h3>
                    <div class="space-y-6">
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Fiscal Year</label>
                            <select id="yearFilter" onchange="updateContext()" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 appearance-none">
                                <?php 
                                $yrs_rs = $conn->query("SELECT DISTINCT academic_year FROM student_fees ORDER BY academic_year DESC");
                                while($yr = $yrs_rs->fetch_assoc()) if($yr['academic_year']) $yrs[] = $yr['academic_year'];
                                if(!in_array($default_academic_year, $yrs)) array_unshift($yrs, $default_academic_year);
                                foreach($yrs as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>" <?= $y === $selected_academic_year ? 'selected' : '' ?>><?= formatAcademicYearDisplay($conn, $y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Semester Context</label>
                            <select id="termFilter" onchange="updateContext()" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-indigo-500 appearance-none">
                                <?php foreach (getAvailableSemesters($conn) as $t): ?>
                                    <option value="<?= htmlspecialchars($t) ?>" <?= $t === $selected_term ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </section>

                <!-- Balance Pulse -->
                <section class="bg-slate-900 rounded-[2.5rem] p-10 shadow-2xl shadow-slate-900/20 text-white relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <i class="fas fa-vault text-[120px]"></i>
                    </div>
                    <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] mb-8">Fulfillment Matrix</h3>
                    
                    <div class="space-y-8 relative z-10">
                        <div>
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1">Current Liability</p>
                            <h2 class="text-4xl font-black italic text-rose-500">₵<?= number_format($student_balance['net_balance'], 2) ?></h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="bg-white/5 p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-black text-slate-500 uppercase mb-1">Total Due</p>
                                <p class="text-sm font-black text-slate-200">₵<?= number_format($student_balance['total_fees'], 2) ?></p>
                            </div>
                            <div class="bg-white/5 p-4 rounded-2xl border border-white/5">
                                <p class="text-[8px] font-black text-slate-500 uppercase mb-1">Aggregate Paid</p>
                                <p class="text-sm font-black text-emerald-400">₵<?= number_format($student_balance['total_payments'], 2) ?></p>
                            </div>
                        </div>
                        <?php if($student_balance['arrears'] > 0): ?>
                        <div class="flex items-center gap-3 p-4 bg-rose-500/10 rounded-2xl border border-rose-500/20">
                            <i class="fas fa-triangle-exclamation text-rose-500"></i>
                            <div>
                                <p class="text-[10px] font-black text-rose-100 uppercase leading-none mb-1">Arrears Included</p>
                                <p class="text-[9px] font-bold text-rose-300 italic">₵<?= number_format($student_balance['arrears'], 2) ?> from previous period</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Right: Interactive Ledger -->
            <div class="lg:col-span-12 xl:col-span-8 space-y-10">
                <section class="bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden">
                    <div class="px-10 py-8 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Institutional Master Ledger</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="text-[9px] font-black text-slate-400 uppercase tracking-widest bg-slate-50/20">
                                    <th class="px-10 py-6">Transaction Entry</th>
                                    <th class="px-6 py-6">Value (₵)</th>
                                    <th class="px-6 py-6">Status</th>
                                    <th class="px-6 py-6">Receipt / Ref</th>
                                    <th class="px-10 py-6 text-right no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <!-- Arrears/Fees Mapping -->
                                <?php foreach($term_fees as $fee): 
                                    $is_ob = (stripos($fee['fee_name'], 'outstanding') !== false || stripos($fee['fee_name'], 'arrears') !== false);
                                ?>
                                <tr class="ledger-row group">
                                    <td class="px-10 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-8 h-8 rounded-lg <?= $is_ob?'bg-rose-50 text-rose-600 font-black':'bg-indigo-50 text-indigo-600' ?> flex items-center justify-center text-[10px]">
                                                <i class="fas <?= $is_ob?'fa-exclamation-triangle-check':'fa-receipt' ?>"></i>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-black text-slate-800 uppercase leading-none mb-1"><?= htmlspecialchars($fee['fee_name']) ?></p>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter italic">Entry Date: <?= $fee['due_date'] ? date('M j, Y', strtotime($fee['due_date'])) : 'Rolling' ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 text-sm font-black <?= $fee['status']==='paid'?'text-emerald-600':'text-rose-600' ?>">₵<?= number_format($fee['amount'], 2) ?></td>
                                    <td class="px-6 py-6">
                                        <span class="text-[9px] font-black uppercase px-2 py-1 rounded-md <?= $fee['status']==='paid'?'bg-emerald-50 text-emerald-600':'bg-slate-100 text-slate-400' ?>"><?= $fee['status'] ?></span>
                                    </td>
                                    <td class="px-6 py-6 text-[10px] font-bold text-slate-400">—</td>
                                    <td class="px-10 py-6 text-right no-print">
                                        <?php if(!$is_ob): ?>
                                        <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="editFee(<?= $fee['id'] ?>, '<?= htmlspecialchars($fee['fee_name']) ?>', <?= $fee['amount'] ?>)" class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center transition-all"><i class="fas fa-edit text-[10px]"></i></button>
                                            <button onclick="unassignFee(<?= $fee['id'] ?>)" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all"><i class="fas fa-times text-[10px]"></i></button>
                                        </div>
                                        <?php else: ?>
                                            <span class="text-[8px] font-black text-slate-300 uppercase italic">System Lock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <!-- Payment Mapping -->
                                <?php foreach($payment_history as $p): ?>
                                <tr class="ledger-row group bg-emerald-50/20">
                                    <td class="px-10 py-6">
                                        <div class="flex items-center gap-4">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px]">
                                                <i class="fas fa-shield-check"></i>
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-black text-emerald-800 uppercase leading-none mb-1">Remittance Applied</p>
                                                <p class="text-[9px] font-bold text-emerald-400 uppercase tracking-tighter">Verified: <?= date('M j, Y', strtotime($p['payment_date'])) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 text-sm font-black text-emerald-600 italic">₵<?= number_format($p['amount'], 2) ?></td>
                                    <td class="px-6 py-6">
                                        <span class="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-emerald-100 text-emerald-600">Settled</span>
                                    </td>
                                    <td class="px-6 py-6 text-[10px] font-black text-slate-600 italic"><?= $p['receipt_no'] ?: 'AUTO-GEN' ?></td>
                                    <td class="px-10 py-6 text-right no-print">
                                         <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="deletePayment(<?= $p['id'] ?>)" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all"><i class="fas fa-trash text-[10px]"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(empty($term_fees) && empty($payment_history)): ?>
                        <div class="p-20 text-center text-slate-300 italic text-sm">Matrix Void &middot; No transactions registered for this period.</div>
                    <?php endif; ?>
                </section>
            </div>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Institutional Registry Ledger &middot; Salba Montessori &middot; v9.5.0
        </footer>
    </main>

    <!-- Simple Utility Modals -->
    <div id="editFeeOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 flex items-center justify-center opacity-0 pointer-events-none transition-all duration-300 px-6">
        <div class="bg-white rounded-[2.5rem] p-10 max-w-md w-full shadow-2xl">
            <h3 class="text-xl font-black text-slate-900 mb-2">Adjust Allocation</h3>
            <p class="text-xs text-slate-500 font-medium mb-8">Redefine fee parameters for <span id="overlayFeeName" class="text-indigo-600 font-black">...</span></p>
            <form id="editFeeForm">
                <input type="hidden" name="student_fee_id" id="editFeeId">
                <div class="space-y-6">
                    <div>
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Modified Amount (GHS)</label>
                        <input type="number" step="0.01" name="amount" id="editFeeAmount" class="w-full bg-slate-50 border border-slate-100 rounded-xl px-4 py-3 text-sm font-black text-slate-900 outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>
                </div>
                <div class="flex gap-4 mt-10">
                    <button type="button" onclick="closeModals()" class="flex-1 px-3 py-2 rounded-xl text-[10px] font-black text-slate-400 uppercase tracking-widest hover:bg-slate-50 transition-all">Cancel</button>
                    <button type="submit" class="flex-1 bg-indigo-600 text-white px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-lg shadow-indigo-600/20">Sync Adjustments</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateContext() {
            const sid = <?= $student_id ?>;
            const term = document.getElementById('termFilter').value;
            const year = document.getElementById('yearFilter').value;
            window.location.href = `?id=${sid}&semester=${encodeURIComponent(term)}&academic_year=${encodeURIComponent(year)}`;
        }

        function editFee(id, name, amount) {
            document.getElementById('editFeeId').value = id;
            document.getElementById('overlayFeeName').textContent = name;
            document.getElementById('editFeeAmount').value = amount;
            const o = document.getElementById('editFeeOverlay');
            o.classList.remove('pointer-events-none');
            o.classList.add('opacity-100');
        }

        function closeModals() {
            const overlays = ['editFeeOverlay'];
            overlays.forEach(id => {
                const o = document.getElementById(id);
                o.classList.add('pointer-events-none');
                o.classList.remove('opacity-100');
            });
        }

        document.getElementById('editFeeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            fetch('edit_student_fee.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if(d.success) window.location.reload(); else alert(d.message); });
        });

        function unassignFee(id) {
            if(!confirm('Expunge this fee assignment?')) return;
            fetch('unassign_fee.php', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify({student_fee_id: id, student_id: <?= $student_id ?>})
            }).then(r => r.json()).then(d => { if(d.success) window.location.reload(); else alert(d.message); });
        }

        function deletePayment(id) {
            if(!confirm('Expunge this payment record? Balance will be updated.')) return;
            fetch('delete_payment.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({payment_id: id})
            }).then(r => r.json()).then(d => { if(d.success) window.location.reload(); else alert(d.message); });
        }
    </script>
</body>
</html>
