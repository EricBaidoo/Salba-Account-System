<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include_once '../../../includes/accounting_engine.php';

// Enforce admin/finance only
if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

$run_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Helper: derive semester + academic_year from payroll month/year
function payrollPeriodMeta($month, $year) {
    if ($month >= 9) {
        return ['First Semester', $year . '/' . ($year + 1)];
    } elseif ($month <= 4) {
        return ['Second Semester', ($year - 1) . '/' . $year];
    } else {
        return ['Trimester', ($year - 1) . '/' . $year];
    }
}

// Helper: insert Staff Salaries expense row for a payroll record
function insertPayrollExpense($conn, $run_id, $rec, $run_month, $run_year) {
    $gross = $rec['base_salary'] + $rec['allowances'];
    [$sem, $acad_yr] = payrollPeriodMeta($run_month, $run_year);
    $period_label = date('F', mktime(0, 0, 0, $run_month, 10)) . ' ' . $run_year;
    $desc = $conn->real_escape_string("Payroll Run #{$run_id}: Staff #{$rec['staff_id']} — {$period_label}");
    $conn->query("INSERT INTO expenses (category_id, amount, expense_date, description, semester, academic_year)
                  VALUES (4, $gross, CURDATE(), '$desc', '$sem', '$acad_yr')");
}

// Helper: delete journal entries + expense rows for a payroll run
function deletePayrollJournalAndExpenses($conn, $run_id) {
    $res = $conn->query("SELECT id FROM payroll_records WHERE payroll_run_id = $run_id");
    $record_ids = [];
    while ($r = $res->fetch_assoc()) $record_ids[] = (int)$r['id'];

    if (!empty($record_ids)) {
        $ids = implode(',', $record_ids);
        // Remove journal lines first (FK child), then headers
        $conn->query("DELETE jl FROM journal_lines jl
                      JOIN journal_entries je ON jl.journal_entry_id = je.id
                      WHERE je.reference_type = 'Payroll' AND je.reference_id IN ($ids)");
        $conn->query("DELETE FROM journal_entries WHERE reference_type = 'Payroll' AND reference_id IN ($ids)");
    }

    // Remove expense rows tagged to this run
    $conn->query("DELETE FROM expenses WHERE description LIKE 'Payroll Run #$run_id:%' OR description LIKE 'Payroll Run #{$run_id}: %'");
}

// Handle marking as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Fetch run info once for expense logging
    $run_info = $conn->query("SELECT payroll_month, payroll_year FROM payroll_runs WHERE id = $run_id")->fetch_assoc();

    if ($_POST['action'] === 'mark_paid') {
        $record_id = (int)$_POST['record_id'];
        $rec = $conn->query("SELECT * FROM payroll_records WHERE id = $record_id")->fetch_assoc();
        if ($rec && $rec['status'] !== 'paid') {
            $conn->query("UPDATE payroll_records SET status = 'paid', payment_date = CURDATE() WHERE id = $record_id AND payroll_run_id = $run_id");
            $_SESSION['success_msg'] = "Salary record marked as paid.";
            $gross = $rec['base_salary'] + $rec['allowances'];
            $deductions = $rec['deductions'] + $rec['tier_1_employee'] + $rec['tier_2_employee'] + $rec['income_tax'];
            record_journal_entry($conn, date('Y-m-d'), 'Payroll', $record_id, "Salary Payout (Staff #{$rec['staff_id']})", [
                ['account_code' => '5000', 'debit' => $gross, 'credit' => 0],
                ['account_code' => '1000', 'debit' => 0, 'credit' => $rec['net_salary']],
                ['account_code' => '2000', 'debit' => 0, 'credit' => $deductions]
            ]);
            if ($run_info) insertPayrollExpense($conn, $run_id, $rec, $run_info['payroll_month'], $run_info['payroll_year']);
        }
        $pending_count = $conn->query("SELECT COUNT(id) as c FROM payroll_records WHERE payroll_run_id = $run_id AND status = 'pending'")->fetch_assoc()['c'];
        $conn->query("UPDATE payroll_runs SET status = " . ($pending_count == 0 ? "'paid'" : "'approved'") . " WHERE id = $run_id");
    }

    if ($_POST['action'] === 'bulk_pay') {
        $pending = $conn->query("SELECT * FROM payroll_records WHERE payroll_run_id = $run_id AND status = 'pending'");
        $count = 0;
        while ($rec = $pending->fetch_assoc()) {
            $conn->query("UPDATE payroll_records SET status = 'paid', payment_date = CURDATE() WHERE id = {$rec['id']}");
            $gross = $rec['base_salary'] + $rec['allowances'];
            $deductions = $rec['deductions'] + $rec['tier_1_employee'] + $rec['tier_2_employee'] + $rec['income_tax'];
            record_journal_entry($conn, date('Y-m-d'), 'Payroll', $rec['id'], "Salary Payout (Staff #{$rec['staff_id']})", [
                ['account_code' => '5000', 'debit' => $gross, 'credit' => 0],
                ['account_code' => '1000', 'debit' => 0, 'credit' => $rec['net_salary']],
                ['account_code' => '2000', 'debit' => 0, 'credit' => $deductions]
            ]);
            if ($run_info) insertPayrollExpense($conn, $run_id, $rec, $run_info['payroll_month'], $run_info['payroll_year']);
            $count++;
        }
        $conn->query("UPDATE payroll_runs SET status = 'paid' WHERE id = $run_id");
        $_SESSION['success_msg'] = "$count staff salary records marked as paid.";
    }

    if ($_POST['action'] === 'edit_record') {
        $record_id   = (int)$_POST['record_id'];
        $base_salary = (float)$_POST['base_salary'];
        $allowances  = (float)$_POST['allowances'];
        $deductions  = (float)$_POST['deductions'];
        $rec = $conn->query("SELECT * FROM payroll_records WHERE id = $record_id AND payroll_run_id = $run_id")->fetch_assoc();
        if ($rec && $rec['status'] !== 'paid') {
            $gross     = $base_salary + $allowances;
            $total_ded = $deductions + $rec['tier_1_employee'] + $rec['tier_2_employee'] + $rec['income_tax'];
            $net       = $gross - $total_ded;
            $conn->query("UPDATE payroll_records SET base_salary = $base_salary, allowances = $allowances, deductions = $deductions, net_salary = $net WHERE id = $record_id");
            $t = $conn->query("SELECT SUM(base_salary + allowances) as tg, SUM(net_salary) as tn, SUM(deductions + tier_1_employee + tier_2_employee + income_tax) as td FROM payroll_records WHERE payroll_run_id = $run_id")->fetch_assoc();
            $conn->query("UPDATE payroll_runs SET total_gross = {$t['tg']}, total_net = {$t['tn']}, total_deductions = {$t['td']} WHERE id = $run_id");
            $_SESSION['success_msg'] = "Salary record updated successfully.";
        } else {
            $_SESSION['error_msg'] = "Cannot edit a record that has already been paid.";
        }
    }

    header("Location: view_run.php?id=$run_id");
    exit;
}

// Fetch run details
$run = $conn->query("SELECT * FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
if (!$run) {
    die("Payroll run not found.");
}
$period_name = date("F", mktime(0, 0, 0, $run['payroll_month'], 10)) . ' ' . $run['payroll_year'];

// Fetch records
$records = $conn->query("
    SELECT pr.*, sp.full_name, sp.job_title, sp.department, sss.bank_name, sss.account_number
    FROM payroll_records pr
    JOIN staff_profiles sp ON pr.staff_id = sp.id
    LEFT JOIN staff_salary_structures sss ON sp.id = sss.staff_id
    WHERE pr.payroll_run_id = $run_id
    ORDER BY sp.full_name ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Run Details | Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-[#F8FAFC] text-slate-900">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="index.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-arrow-left"></i> All Runs</a>
                <span>/</span>
                <span class="text-blue-600"><?= $period_name ?></span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-file-invoice-dollar text-blue-600"></i> <?= $period_name ?> Payroll
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Review salary calculations and manage payouts for this period.</p>
                </div>
                <?php if ($run['status'] !== 'paid'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="bulk_pay">
                    <button type="submit" onclick="return confirm('Mark ALL pending staff as paid? This will create journal entries for each.')" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 shadow-sm transition-all">
                        <i class="fas fa-money-bill-wave"></i> Pay All Pending
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="p-6">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($_SESSION['success_msg']) ?></span>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-lg text-sm flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($_SESSION['error_msg']) ?></span>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Gross</p>
                    <h3 class="text-xl font-bold text-slate-900">GHS <?= number_format($run['total_gross'], 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Deductions</p>
                    <h3 class="text-xl font-bold text-rose-600">GHS <?= number_format($run['total_deductions'], 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Net Payout</p>
                    <h3 class="text-xl font-bold text-emerald-600">GHS <?= number_format($run['total_net'], 2) ?></h3>
                </div>
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</p>
                    <?php if ($run['status'] === 'paid'): ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-emerald-50 text-emerald-700 border border-emerald-100 mt-1">
                            <i class="fas fa-check-double"></i> Fully Paid
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold bg-amber-50 text-amber-700 border border-amber-100 mt-1">
                            <i class="fas fa-hourglass-half"></i> Pending Payouts
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4 text-right">Gross (GHS)</th>
                                <th class="px-6 py-4 text-right">Deductions</th>
                                <th class="px-6 py-4 text-right">Net Salary</th>
                                <th class="px-6 py-4 text-center">Payment Info</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($records && $records->num_rows > 0): ?>
                                <?php while ($row = $records->fetch_assoc()): 
                                    $gross = $row['base_salary'] + $row['allowances'];
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0 group">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($row['full_name']) ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($row['job_title'] ?? 'Staff') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-sm text-slate-700">
                                        <?= number_format($gross, 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-sm text-rose-600">
                                        -<?= number_format($row['deductions'] + $row['tier_1_employee'] + $row['tier_2_employee'] + $row['income_tax'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-base text-emerald-600">
                                        <?= number_format($row['net_salary'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if (!empty($row['bank_name'])): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-semibold" title="<?= htmlspecialchars($row['account_number']) ?>">
                                                <i class="fas fa-building-columns"></i> <?= htmlspecialchars($row['bank_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-300 text-xs italic">Cash / Cheque</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($row['status'] === 'paid'): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="view_payslip.php?id=<?= $row['id'] ?>" target="_blank" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center justify-center transition-colors" title="Print Payslip">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php if ($row['status'] !== 'paid'): ?>
                                            <button onclick='openEditModal(<?= json_encode(['id'=>$row['id'],'full_name'=>$row['full_name'],'base_salary'=>$row['base_salary'],'allowances'=>$row['allowances'],'deductions'=>$row['deductions']]) ?>)'
                                                class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors" title="Edit Record">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="record_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-600 hover:text-white flex items-center justify-center transition-colors" title="Mark as Paid">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Record Modal -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-edit text-blue-600"></i> Edit Salary Record
                </h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_record">
                <input type="hidden" name="record_id" id="edit_record_id">
                <p class="text-sm font-semibold text-slate-700" id="edit_staff_name"></p>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Base Salary (GHS)</label>
                    <input type="number" step="0.01" name="base_salary" id="edit_base_salary" required
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm font-semibold outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" oninput="recalcNet()">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Allowances</label>
                        <input type="number" step="0.01" name="allowances" id="edit_allowances" value="0"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" oninput="recalcNet()">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Other Deductions</label>
                        <input type="number" step="0.01" name="deductions" id="edit_deductions" value="0"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500" oninput="recalcNet()">
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 rounded-lg px-4 py-3 flex justify-between items-center">
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Estimated Net Pay</span>
                    <span id="edit_net_preview" class="text-base font-bold text-emerald-600">GHS 0.00</span>
                </div>
                <p class="text-[11px] text-slate-400">SSNIT and income tax deductions are applied on top and are not editable here.</p>
                <div class="flex gap-3 justify-end pt-2">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(rec) {
            document.getElementById('edit_record_id').value = rec.id;
            document.getElementById('edit_staff_name').textContent = rec.full_name;
            document.getElementById('edit_base_salary').value = rec.base_salary;
            document.getElementById('edit_allowances').value = rec.allowances || 0;
            document.getElementById('edit_deductions').value = rec.deductions || 0;
            recalcNet();
            const m = document.getElementById('editModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        }
        function closeEditModal() {
            const m = document.getElementById('editModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        }
        function recalcNet() {
            const base = parseFloat(document.getElementById('edit_base_salary').value) || 0;
            const alw  = parseFloat(document.getElementById('edit_allowances').value) || 0;
            const ded  = parseFloat(document.getElementById('edit_deductions').value) || 0;
            const net  = (base + alw) - ded;
            document.getElementById('edit_net_preview').textContent = 'GHS ' + net.toFixed(2);
        }
    </script>
</body>
</html>
