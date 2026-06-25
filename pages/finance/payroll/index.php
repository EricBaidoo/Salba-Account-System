<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

// Enforce admin/finance only
if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Handle Run Payroll form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'run_payroll') {
    $month = (int)$_POST['payroll_month'];
    $year = (int)$_POST['payroll_year'];
    $user_id = $_SESSION['user_id'];
    
    // Check if payroll already run for this month/year
    $check = $conn->query("SELECT id FROM payroll_runs WHERE payroll_month = $month AND payroll_year = $year");
    if ($check && $check->num_rows > 0) {
        $_SESSION['error_msg'] = "Payroll for this month has already been generated.";
    } else {
        $conn->begin_transaction();
        try {
            // Create payroll run
            $conn->query("INSERT INTO payroll_runs (payroll_month, payroll_year, created_by) VALUES ($month, $year, $user_id)");
            $run_id = $conn->insert_id;
            
            // Get all active staff with configured salaries
            $staff_res = $conn->query("
                SELECT sp.id as staff_id, sss.base_salary, sss.custom_allowances, sss.custom_deductions,
                       sss.tier_1_ssnit_employee, sss.tier_2_ssnit, sss.tier_1_ssnit_employer
                FROM staff_profiles sp
                JOIN staff_salary_structures sss ON sp.id = sss.staff_id
                WHERE sp.employment_status = 'active' AND sss.base_salary IS NOT NULL
            ");
            
            $global_taxes_json = getSystemSetting($conn, 'global_taxes', '[]');
            $global_taxes_conf = json_decode($global_taxes_json, true) ?: [];
            
            $total_gross = 0;
            $total_net = 0;
            $total_deductions = 0;
            $total_employer_ssnit = 0;
            
            if ($staff_res && $staff_res->num_rows > 0) {
                $stmt = $conn->prepare("INSERT INTO payroll_records (payroll_run_id, staff_id, base_salary, allowances, custom_allowances, deductions, custom_deductions, global_taxes, tier_1_employee, tier_2_employee, net_salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                while ($staff = $staff_res->fetch_assoc()) {
                    $alws = json_decode($staff['custom_allowances'] ?? '[]', true) ?: [];
                    $deds = json_decode($staff['custom_deductions'] ?? '[]', true) ?: [];
                    
                    $tot_alw = array_sum(array_column($alws, 'amount'));
                    $tot_ded = array_sum(array_column($deds, 'amount'));
                    
                    $staff_global_taxes = [];
                    $tot_global_tax = 0;
                    foreach($global_taxes_conf as $gtax) {
                        $tax_amt = $staff['base_salary'] * ($gtax['percent'] / 100);
                        $staff_global_taxes[] = [
                            'name' => $gtax['name'],
                            'amount' => $tax_amt
                        ];
                        $tot_global_tax += $tax_amt;
                    }
                    
                    $gross = $staff['base_salary'] + $tot_alw;
                    $total_deduct = $tot_ded + $staff['tier_1_ssnit_employee'] + $staff['tier_2_ssnit'] + $tot_global_tax;
                    $net = $gross - $total_deduct;
                    
                    $gtax_json = json_encode($staff_global_taxes);
                    $alw_json = $staff['custom_allowances'] ?? '[]';
                    $ded_json = $staff['custom_deductions'] ?? '[]';
                    
                    $stmt->bind_param("iiddsdssddd",
                        $run_id, $staff['staff_id'], $staff['base_salary'], $tot_alw, $alw_json,
                        $tot_ded, $ded_json, $gtax_json, $staff['tier_1_ssnit_employee'], $staff['tier_2_ssnit'],
                        $net
                    );
                    $stmt->execute();
                    
                    $total_gross += $gross;
                    $total_net += $net;
                    $total_deductions += $total_deduct;
                    $total_employer_ssnit += $staff['tier_1_ssnit_employer'];
                }
                
                // Update run totals
                $conn->query("UPDATE payroll_runs SET total_gross = $total_gross, total_net = $total_net, total_deductions = $total_deductions, total_employer_ssnit = $total_employer_ssnit WHERE id = $run_id");
                $conn->commit();
                $_SESSION['success_msg'] = "Payroll generated successfully for " . date("F", mktime(0, 0, 0, $month, 10)) . " $year.";
            } else {
                throw new Exception("No active staff with configured salaries found.");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_msg'] = "Failed to run payroll: " . $e->getMessage();
        }
    }
    header("Location: index.php");
    exit;
}

// Handle Delete Payroll Run
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_run') {
    $run_id = (int)$_POST['run_id'];
    
    $check = $conn->query("SELECT status FROM payroll_runs WHERE id = $run_id")->fetch_assoc();
    if ($check && $check['status'] === 'draft') {
        $conn->query("DELETE FROM payroll_records WHERE payroll_run_id = $run_id");
        $conn->query("DELETE FROM payroll_runs WHERE id = $run_id");
        $_SESSION['success_msg'] = "Drafted payroll deleted successfully.";
    } else {
        $_SESSION['error_msg'] = "Cannot delete a payroll that has already been approved or paid.";
    }
    header("Location: index.php");
    exit;
}

// Fetch Payroll Runs
$runs = $conn->query("
    SELECT pr.*, 
           (SELECT COUNT(id) FROM payroll_records WHERE payroll_run_id = pr.id) as staff_count,
           u.username as created_by_name
    FROM payroll_runs pr
    LEFT JOIN users u ON pr.created_by = u.id
    ORDER BY pr.payroll_year DESC, pr.payroll_month DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Dashboard | Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-blue-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-blue-600">Payroll Runs</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-file-invoice-dollar text-blue-600"></i> Payroll Management
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Generate and manage monthly salary payouts for all staff.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="staff_salaries.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all shadow-sm">
                        <i class="fas fa-users-cog"></i> Config Salaries
                    </a>
                    <button onclick="document.getElementById('runPayrollModal').classList.remove('hidden'); document.getElementById('runPayrollModal').classList.add('flex');" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all">
                        <i class="fas fa-play"></i> Generate Payroll
                    </button>
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
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 bg-rose-50 border border-rose-100 text-rose-700 px-4 py-3 rounded-lg text-sm flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($_SESSION['error_msg']) ?></span>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Payroll Period</th>
                                <th class="px-6 py-4 text-center">Staff Count</th>
                                <th class="px-6 py-4 text-right">Total Net (GHS)</th>
                                <th class="px-6 py-4 text-right">Total Deductions</th>
                                <th class="px-6 py-4 text-center">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($runs && $runs->num_rows > 0): ?>
                                <?php while ($row = $runs->fetch_assoc()): 
                                    $period_name = date("F", mktime(0, 0, 0, $row['payroll_month'], 10)) . ' ' . $row['payroll_year'];
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-100 last:border-0">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-900 text-sm"><?= $period_name ?></div>
                                        <div class="text-[10px] text-slate-500 font-medium uppercase tracking-widest mt-1">Generated: <?= date("d M Y", strtotime($row['created_at'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center font-semibold text-slate-700 text-sm">
                                        <?= $row['staff_count'] ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-emerald-600 text-base">
                                        <?= number_format($row['total_net'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-rose-600 text-sm">
                                        <?= number_format($row['total_deductions'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($row['status'] === 'paid'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                <i class="fas fa-check-double"></i> Paid Out
                                            </span>
                                        <?php elseif ($row['status'] === 'approved'): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                <i class="fas fa-check"></i> Approved
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-100">
                                                <i class="fas fa-hourglass-half"></i> Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="view_run.php?id=<?= $row['id'] ?>" class="inline-flex items-center justify-center w-8 h-8 bg-slate-100 text-slate-600 rounded hover:bg-slate-200 hover:text-slate-900 transition-colors" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($row['status'] === 'draft'): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this drafted payroll run? This cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_run">
                                                <input type="hidden" name="run_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-rose-50 border border-rose-200 text-rose-600 rounded hover:bg-rose-100 transition-colors" title="Delete Draft">
                                                    <i class="fas fa-trash-can"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-16 text-center">
                                        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-2xl mx-auto mb-4"><i class="fas fa-file-invoice-dollar"></i></div>
                                        <h3 class="text-slate-700 font-bold mb-1">No Payroll Runs Yet</h3>
                                        <p class="text-slate-500 text-sm">Configure your staff salaries and generate your first monthly payroll.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Run Payroll Modal -->
    <div id="runPayrollModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-play text-blue-600"></i> Generate Payroll
                </h3>
                <button onclick="document.getElementById('runPayrollModal').classList.add('hidden'); document.getElementById('runPayrollModal').classList.remove('flex');" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="run_payroll">
                
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Month</label>
                        <select name="payroll_month" required class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                            <?php 
                            $curr_month = date('n');
                            for ($m=1; $m<=12; $m++) {
                                $selected = ($m == $curr_month) ? 'selected' : '';
                                echo "<option value=\"$m\" $selected>" . date('F', mktime(0,0,0,$m,10)) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Year</label>
                        <select name="payroll_year" required class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all">
                            <?php 
                            $curr_year = date('Y');
                            for ($y=$curr_year-1; $y<=$curr_year+1; $y++) {
                                $selected = ($y == $curr_year) ? 'selected' : '';
                                echo "<option value=\"$y\" $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="document.getElementById('runPayrollModal').classList.add('hidden'); document.getElementById('runPayrollModal').classList.remove('flex');" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all w-full">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all w-full flex items-center justify-center gap-2">
                        Generate
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
