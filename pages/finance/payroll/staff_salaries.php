<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Enforce admin/finance only
if (!in_array($_SESSION['role'] ?? '', ['admin', 'finance'])) {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Handle form submission to update salary structure
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_salary') {
    $staff_id = (int)$_POST['staff_id'];
    $base_salary = (float)$_POST['base_salary'];
    
    $bank_name = $conn->real_escape_string($_POST['bank_name'] ?? '');
    $account_number = $conn->real_escape_string($_POST['account_number'] ?? '');
    
    $custom_allowances = [];
    if (isset($_POST['allowance_name']) && is_array($_POST['allowance_name'])) {
        for ($i = 0; $i < count($_POST['allowance_name']); $i++) {
            if (trim($_POST['allowance_name'][$i]) !== '') {
                $custom_allowances[] = [
                    'name' => trim($_POST['allowance_name'][$i]),
                    'amount' => floatval($_POST['allowance_amount'][$i])
                ];
            }
        }
    }
    
    $custom_deductions = [];
    if (isset($_POST['deduction_name']) && is_array($_POST['deduction_name'])) {
        for ($i = 0; $i < count($_POST['deduction_name']); $i++) {
            if (trim($_POST['deduction_name'][$i]) !== '') {
                $custom_deductions[] = [
                    'name' => trim($_POST['deduction_name'][$i]),
                    'amount' => floatval($_POST['deduction_amount'][$i])
                ];
            }
        }
    }

    $alw_json = $conn->real_escape_string(json_encode($custom_allowances));
    $ded_json = $conn->real_escape_string(json_encode($custom_deductions));
    $user_id = $_SESSION['user_id'];
    
    // Check if exists
    $check = $conn->query("SELECT id FROM staff_salary_structures WHERE staff_id = $staff_id");
    if ($check && $check->num_rows > 0) {
        $success = $conn->query("UPDATE staff_salary_structures 
                      SET base_salary = $base_salary, custom_allowances = '$alw_json', custom_deductions = '$ded_json',
                          bank_name = '$bank_name', account_number = '$account_number', updated_by = $user_id
                      WHERE staff_id = $staff_id");
    } else {
        $success = $conn->query("INSERT INTO staff_salary_structures (staff_id, base_salary, custom_allowances, custom_deductions, bank_name, account_number, created_by)
                      VALUES ($staff_id, $base_salary, '$alw_json', '$ded_json', '$bank_name', '$account_number', $user_id)");
    }
    
    if ($success) {
        $_SESSION['success_msg'] = "Salary structure updated successfully.";
    } else {
        $_SESSION['error_msg'] = "Error updating salary: " . $conn->error;
    }
    
    header("Location: staff_salaries.php");
    exit;
}

// Fetch staff and their salary structures
$query = "
    SELECT sp.id, sp.full_name, sp.job_title, sp.department, sp.employment_status,
           sss.base_salary, sss.custom_allowances, sss.custom_deductions, sss.bank_name, sss.account_number
    FROM staff_profiles sp
    LEFT JOIN staff_salary_structures sss ON sp.id = sss.staff_id
    WHERE sp.employment_status = 'active'
    ORDER BY sp.full_name ASC
";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Salaries Configuration | Payroll</title>
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
                <a href="index.php" class="hover:text-blue-600 transition-colors">Payroll</a>
                <span>/</span>
                <span class="text-blue-600">Staff Salaries</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-3">
                        <i class="fas fa-money-check-dollar text-blue-600"></i> Staff Salary Structures
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Configure base salaries, dynamic allowances, and deductions.</p>
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
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4 text-right">Base Salary (GHS)</th>
                                <th class="px-6 py-4 text-right">Total Allowances</th>
                                <th class="px-6 py-4 text-right">Total Deductions</th>
                                <th class="px-6 py-4 text-center">Payment Info</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    $is_configured = !is_null($row['base_salary']);
                                    $alws = json_decode($row['custom_allowances'] ?? '[]', true) ?: [];
                                    $deds = json_decode($row['custom_deductions'] ?? '[]', true) ?: [];
                                    $tot_alw = array_sum(array_column($alws, 'amount'));
                                    $tot_ded = array_sum(array_column($deds, 'amount'));
                                ?>
                                <tr class="hover:bg-slate-50 transition-colors group border-b border-slate-100 last:border-0">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-900 text-sm"><?= htmlspecialchars($row['full_name']) ?></div>
                                        <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($row['job_title'] ?? 'Staff') ?> &middot; <?= htmlspecialchars($row['department'] ?? 'General') ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-sm <?= $is_configured ? 'text-slate-700' : 'text-slate-400' ?>">
                                        <?= $is_configured ? number_format($row['base_salary'], 2) : 'Not Set' ?>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-sm text-emerald-600">
                                        <?= $is_configured && $tot_alw > 0 ? '+' . number_format($tot_alw, 2) : '—' ?>
                                        <div class="text-[10px] text-emerald-500 mt-0.5 truncate max-w-[120px] ml-auto"><?= count($alws) ?> items</div>
                                    </td>
                                    <td class="px-6 py-4 text-right font-medium text-sm text-rose-600">
                                        <?= $is_configured && $tot_ded > 0 ? '-' . number_format($tot_ded, 2) : '—' ?>
                                        <div class="text-[10px] text-rose-500 mt-0.5 truncate max-w-[120px] ml-auto"><?= count($deds) ?> items</div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <?php if ($is_configured && !empty($row['bank_name'])): ?>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-semibold" title="<?= htmlspecialchars($row['account_number']) ?>">
                                                <i class="fas fa-building-columns"></i> <?= htmlspecialchars($row['bank_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs italic">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button onclick='openSalaryModal(<?= json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-semibold rounded hover:bg-blue-100 transition-colors">
                                            <i class="fas fa-edit"></i> <?= $is_configured ? 'Update' : 'Configure' ?>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500 font-medium">No active staff found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal -->
    <div id="salaryModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-2xl max-h-[90vh] overflow-y-auto transform transition-all">
            <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 sticky top-0 z-10">
                <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                    <i class="fas fa-money-bill-wave text-blue-600"></i> Configure Salary
                </h3>
                <button onclick="closeSalaryModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" class="p-6">
                <input type="hidden" name="action" value="update_salary">
                <input type="hidden" name="staff_id" id="modal_staff_id">
                
                <div class="mb-6">
                    <p class="text-lg font-semibold text-slate-900" id="modal_staff_name">Staff Name</p>
                    <p class="text-sm font-medium text-slate-500" id="modal_staff_role">Role</p>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Base Salary (GHS)</label>
                    <input type="number" step="0.01" name="base_salary" id="modal_base_salary" required class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-4 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all font-semibold text-base">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Allowances -->
                    <div class="border border-emerald-200 bg-emerald-50/50 p-4 rounded-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-xs uppercase text-emerald-700 font-semibold tracking-wider flex items-center gap-2">
                                <i class="fas fa-plus-circle"></i> Allowances
                            </h4>
                            <button type="button" onclick="addAllowanceRow()" class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[10px] font-semibold hover:bg-emerald-200 transition-colors border border-emerald-200">
                                Add
                            </button>
                        </div>
                        <div id="allowancesContainer" class="space-y-3"></div>
                    </div>
                    
                    <!-- Deductions -->
                    <div class="border border-rose-200 bg-rose-50/50 p-4 rounded-xl">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-xs uppercase text-rose-700 font-semibold tracking-wider flex items-center gap-2">
                                <i class="fas fa-minus-circle"></i> Deductions
                            </h4>
                            <button type="button" onclick="addDeductionRow()" class="px-2 py-1 bg-rose-100 text-rose-700 rounded text-[10px] font-semibold hover:bg-rose-200 transition-colors border border-rose-200">
                                Add
                            </button>
                        </div>
                        <div id="deductionsContainer" class="space-y-3"></div>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-6 space-y-4">
                    <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Bank Details</h4>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Bank Name</label>
                            <input type="text" name="bank_name" id="modal_bank_name" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase mb-1">Account Number</label>
                            <input type="text" name="account_number" id="modal_account_number" class="w-full bg-white border border-slate-300 text-slate-900 rounded-lg px-3 py-2 outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm">
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex gap-3 justify-end sticky bottom-0 bg-white pt-4 pb-2 border-t border-slate-100">
                    <button type="button" onclick="closeSalaryModal()" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shadow-sm transition-all">Save Structure</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addAllowanceRow(name = '', amount = '') {
            const container = document.getElementById('allowancesContainer');
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-center';
            row.innerHTML = `
                <input type="text" name="allowance_name[]" value="${name}" placeholder="Name (e.g. Transport)" class="w-full px-3 py-1.5 bg-white border border-emerald-300 rounded focus:ring-1 focus:ring-emerald-500 outline-none font-medium text-slate-700 text-xs">
                <input type="number" step="0.01" name="allowance_amount[]" value="${amount}" placeholder="Amount" class="w-24 px-3 py-1.5 bg-white border border-emerald-300 rounded focus:ring-1 focus:ring-emerald-500 outline-none font-semibold text-slate-700 text-xs">
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-600"><i class="fas fa-times-circle"></i></button>
            `;
            container.appendChild(row);
        }

        function addDeductionRow(name = '', amount = '') {
            const container = document.getElementById('deductionsContainer');
            const row = document.createElement('div');
            row.className = 'flex gap-2 items-center';
            row.innerHTML = `
                <input type="text" name="deduction_name[]" value="${name}" placeholder="Name (e.g. Loan)" class="w-full px-3 py-1.5 bg-white border border-rose-300 rounded focus:ring-1 focus:ring-rose-500 outline-none font-medium text-slate-700 text-xs">
                <input type="number" step="0.01" name="deduction_amount[]" value="${amount}" placeholder="Amount" class="w-24 px-3 py-1.5 bg-white border border-rose-300 rounded focus:ring-1 focus:ring-rose-500 outline-none font-semibold text-slate-700 text-xs">
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-600"><i class="fas fa-times-circle"></i></button>
            `;
            container.appendChild(row);
        }

        function openSalaryModal(staff) {
            document.getElementById('modal_staff_id').value = staff.id;
            document.getElementById('modal_staff_name').innerText = staff.full_name;
            document.getElementById('modal_staff_role').innerText = (staff.job_title || 'Staff') + ' - ' + (staff.department || 'General');
            
            document.getElementById('modal_base_salary').value = staff.base_salary || '';
            document.getElementById('modal_bank_name').value = staff.bank_name || '';
            document.getElementById('modal_account_number').value = staff.account_number || '';

            // Clear containers
            const alwCont = document.getElementById('allowancesContainer');
            alwCont.innerHTML = '';
            const dedCont = document.getElementById('deductionsContainer');
            dedCont.innerHTML = '';

            // Populate items
            if (staff.custom_allowances) {
                try {
                    const alws = JSON.parse(staff.custom_allowances);
                    alws.forEach(item => addAllowanceRow(item.name, item.amount));
                } catch(e) {}
            }
            if (staff.custom_deductions) {
                try {
                    const deds = JSON.parse(staff.custom_deductions);
                    deds.forEach(item => addDeductionRow(item.name, item.amount));
                } catch(e) {}
            }

            const modal = document.getElementById('salaryModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeSalaryModal() {
            const modal = document.getElementById('salaryModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    </script>
</body>
</html>
