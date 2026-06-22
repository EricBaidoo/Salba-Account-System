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

// Handle marking as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_paid') {
        $record_id = (int)$_POST['record_id'];
        
        // Fetch record details to get amounts for accounting
        $rec = $conn->query("SELECT * FROM payroll_records WHERE id = $record_id")->fetch_assoc();
        
        if ($rec && $rec['status'] !== 'paid') {
            $conn->query("UPDATE payroll_records SET status = 'paid', payment_date = CURDATE() WHERE id = $record_id AND payroll_run_id = $run_id");
            $_SESSION['success_msg'] = "Salary record marked as paid.";
            
            // Auto Journal Entry
            $gross = $rec['base_salary'] + $rec['allowances'];
            $deductions = $rec['deductions'] + $rec['tier_1_employee'] + $rec['tier_2_employee'] + $rec['income_tax'];
            $net = $rec['net_salary'];
            
            record_journal_entry($conn, date('Y-m-d'), 'Payroll', $record_id, "Salary Payout (Staff #{$rec['staff_id']})", [
                ['account_code' => '5000', 'debit' => $gross, 'credit' => 0], // DR Salary Expense
                ['account_code' => '1000', 'debit' => 0, 'credit' => $net],   // CR Cash
                ['account_code' => '2000', 'debit' => 0, 'credit' => $deductions] // CR Accounts Payable (Taxes/Pensions)
            ]);
        }
        
        // Check if all are paid to update run status
        $pending_count = $conn->query("SELECT COUNT(id) as c FROM payroll_records WHERE payroll_run_id = $run_id AND status = 'pending'")->fetch_assoc()['c'];
        if ($pending_count == 0) {
            $conn->query("UPDATE payroll_runs SET status = 'paid' WHERE id = $run_id");
        } else {
            $conn->query("UPDATE payroll_runs SET status = 'approved' WHERE id = $run_id");
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
            </div>
        </div>

        <div class="p-6">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-3 rounded-lg text-sm flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($_SESSION['success_msg']) ?></span>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
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
</body>
</html>
