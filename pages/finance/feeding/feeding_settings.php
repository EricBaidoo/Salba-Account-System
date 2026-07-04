<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/feeding_helpers.php'; // feeding_days_in_month(), feeding_week_interval()

if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$user_name = $_SESSION['username'] ?? 'System';
$success = '';
$error = '';

function feeding_table_exists($conn, $table_name) {
    $escaped = $conn->real_escape_string($table_name);
    $sql = "SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped'";
    $res = $conn->query($sql);
    if (!$res) {
        return false;
    }
    return ((int)($res->fetch_assoc()['c'] ?? 0)) > 0;
}

function feeding_create_rates_table($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS feeding_class_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        plan_type ENUM('weekly','monthly') NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        effective_from DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        notes VARCHAR(255) NULL,
        created_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_feed_rate_lookup (class_name, plan_type, effective_from),
        UNIQUE KEY uq_feed_rate (class_name, plan_type, effective_from)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    return $conn->query($sql);
}

/** Active operational plans are weekly and monthly only. */
function feeding_supported_plans() {
    return ['weekly', 'monthly'];
}

/**
 * Derive the per-plan amount from a base amount.
 * Base amount is an internal arithmetic input only; never stored as its own rate row.
 */
function feeding_rate_base_amount($amount, $plan_type, $effective_from = '', $conn_ref = null) {
    if ($plan_type === 'monthly') {
        $days = feeding_days_in_month($effective_from ?: date('Y-m-d'), $conn_ref);
        return $days > 0 ? ((float)$amount / $days) : (float)$amount;
    }
    // weekly: divide by 5
    return (float)$amount / 5;
}

function feeding_rate_amount_for_plan($base_amount, $plan_type, $effective_from = '', $conn_ref = null) {
    if ($plan_type === 'monthly') {
        $days = feeding_days_in_month($effective_from ?: date('Y-m-d'), $conn_ref);
        return round((float)$base_amount * $days, 2);
    }
    // weekly
    return round((float)$base_amount * 5, 2);
}

$rates_table_ready = feeding_table_exists($conn, 'feeding_class_rates');
if (!$rates_table_ready) {
    $rates_table_ready = feeding_create_rates_table($conn);
}

if ($rates_table_ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $class_name = trim($_POST['class_name'] ?? '');
        $base_amount = (float)($_POST['amount'] ?? 0);
        $effective_from = trim($_POST['effective_from'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($class_name === '' || $base_amount <= 0 || $effective_from === '') {
            $error = 'Please provide class, base amount (> 0), and effective date.';
        } else {
            $conn->begin_transaction();
            try {
                if ($action === 'update') {
                    $rate_id = (int)($_POST['rate_id'] ?? 0);
                    if ($rate_id <= 0) {
                        throw new Exception('Invalid rate selected for update.');
                    }

                    $original_stmt = $conn->prepare("SELECT class_name, effective_from FROM feeding_class_rates WHERE id = ? LIMIT 1");
                    if (!$original_stmt) {
                        throw new Exception('Could not prepare original-rate lookup.');
                    }
                    $original_stmt->bind_param('i', $rate_id);
                    $original_stmt->execute();
                    $original_row = $original_stmt->get_result()->fetch_assoc();
                    $original_stmt->close();

                    if (!$original_row) {
                        throw new Exception('Original rate not found.');
                    }

                    $delete_stmt = $conn->prepare("DELETE FROM feeding_class_rates WHERE class_name = ? AND effective_from = ?");
                    if (!$delete_stmt) {
                        throw new Exception('Could not prepare existing-rate cleanup.');
                    }
                    $delete_stmt->bind_param('ss', $original_row['class_name'], $original_row['effective_from']);
                    if (!$delete_stmt->execute()) {
                        throw new Exception('Could not clear previous rate set.');
                    }
                    $delete_stmt->close();
                } else {
                    $delete_stmt = $conn->prepare("DELETE FROM feeding_class_rates WHERE class_name = ? AND effective_from = ?");
                    if ($delete_stmt) {
                        $delete_stmt->bind_param('ss', $class_name, $effective_from);
                        $delete_stmt->execute();
                        $delete_stmt->close();
                    }
                }

                $insert_stmt = $conn->prepare("INSERT INTO feeding_class_rates (class_name, plan_type, amount, effective_from, is_active, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if (!$insert_stmt) {
                    throw new Exception('Could not prepare insert statement.');
                }

                foreach (feeding_supported_plans() as $plan_type) {
                    $plan_amount = feeding_rate_amount_for_plan($base_amount, $plan_type, $effective_from, $conn);
                    $insert_stmt->bind_param('ssdssss', $class_name, $plan_type, $plan_amount, $effective_from, $is_active, $notes, $user_name);
                    if (!$insert_stmt->execute()) {
                        throw new Exception('Could not save ' . $plan_type . ' rate: ' . $insert_stmt->error);
                    }
                }
                $insert_stmt->close();
                $conn->commit();
                $success = 'Feeding class rates saved. Weekly = base amount × 5. Monthly = base amount × school days in selected month (Mon-Fri, excluding holidays).';
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }

    if ($action === 'toggle_active') {
        $rate_id = (int)($_POST['rate_id'] ?? 0);
        $new_state = (int)($_POST['new_state'] ?? 0);
        if ($rate_id > 0) {
            $stmt = $conn->prepare("UPDATE feeding_class_rates SET is_active=? WHERE class_name = (SELECT class_name FROM (SELECT class_name FROM feeding_class_rates WHERE id = ? LIMIT 1) AS x) AND effective_from = (SELECT effective_from FROM (SELECT effective_from FROM feeding_class_rates WHERE id = ? LIMIT 1) AS y)");
            if ($stmt) {
                $stmt->bind_param('iii', $new_state, $rate_id, $rate_id);
                if ($stmt->execute()) {
                    $success = $new_state ? 'Rate activated.' : 'Rate deactivated.';
                } else {
                    $error = 'Could not change rate status.';
                }
                $stmt->close();
            }
        }
    }
}

$all_classes = [];
$class_sql = "SELECT DISTINCT class FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class";
$class_res = $conn->query($class_sql);
if ($class_res) {
    while ($row = $class_res->fetch_assoc()) {
        $all_classes[] = $row['class'];
    }
}

$edit_rate = null;
if ($rates_table_ready && isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM feeding_class_rates WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $edit_id);
            $stmt->execute();
            $edit_rate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

$edit_base_amount = $edit_rate ? feeding_rate_base_amount((float)($edit_rate['amount'] ?? 0), $edit_rate['plan_type'] ?? 'weekly', $edit_rate['effective_from'] ?? '', $conn) : '';
$current_month_days = feeding_days_in_month(date('Y-m-d'), $conn);
$preview_base_amount = (float)($edit_base_amount ?: 0);
$preview_weekly_amount = round($preview_base_amount * 5, 2);
$preview_monthly_amount = round($preview_base_amount * $current_month_days, 2);

$rates = [];
if ($rates_table_ready) {
    $rates_res = $conn->query("SELECT * FROM feeding_class_rates ORDER BY is_active DESC, class_name ASC, effective_from DESC");
    if ($rates_res) {
        $rates = $rates_res->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Settings | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-[#F8FAFC] text-slate-900 min-h-screen">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="../dashboard.php" class="hover:text-emerald-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <a href="dashboard.php" class="hover:text-emerald-600 transition-colors">Feeding Dashboard</a>
                <span>/</span>
                <span class="text-emerald-600">Feeding Settings</span>
            </div>
            <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                <i class="fas fa-sliders text-emerald-600"></i> Feeding Settings
            </h1>
            <p class="text-slate-500 mt-1 text-sm">Set class-based feeding rates. Plan behavior is handled during weekly and monthly marking.</p>
        </div>

        <?php if (!$rates_table_ready): ?>
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl">
                <p class="text-sm font-semibold">feeding_class_rates table is missing.</p>
                <p class="text-xs mt-1">The page will create the rates table automatically when possible; refresh after a failed first load.</p>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm font-semibold">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-1 bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider mb-4">
                    <?= $edit_rate ? 'Edit Rate' : 'Add Rate' ?>
                </h2>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="<?= $edit_rate ? 'update' : 'create' ?>">
                    <?php if ($edit_rate): ?>
                        <input type="hidden" name="rate_id" value="<?= (int)$edit_rate['id'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Class</label>
                        <input list="class_options" name="class_name" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="<?= htmlspecialchars($edit_rate['class_name'] ?? '') ?>" placeholder="e.g. KG 1">
                        <datalist id="class_options">
                            <?php foreach ($all_classes as $class_name): ?>
                                <option value="<?= htmlspecialchars($class_name) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs font-semibold">
                        <div class="rounded-lg border border-purple-100 bg-purple-50 p-3">
                            <p class="uppercase tracking-widest text-purple-700 mb-1">Weekly</p>
                            <p class="text-slate-900">Base amount × 5 school days</p>
                        </div>
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-3">
                            <p class="uppercase tracking-widest text-emerald-700 mb-1">Monthly</p>
                            <p class="text-slate-900">Base amount × Mon-Fri school days (excl. holidays)</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Base Amount (GHS)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="base_amount_input" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="<?= htmlspecialchars($edit_base_amount) ?>" placeholder="0.00">
                        <p class="text-[11px] text-slate-500 mt-1">This is the base rate. System auto-calculates weekly (×5) and monthly (×school days, excl. holidays).</p>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Effective From</label>
                        <input type="date" name="effective_from" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="<?= htmlspecialchars($edit_rate['effective_from'] ?? date('Y-m-d')) ?>">
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Notes</label>
                        <input type="text" name="notes" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" value="<?= htmlspecialchars($edit_rate['notes'] ?? '') ?>" placeholder="Optional note">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_active" name="is_active" <?= !isset($edit_rate['is_active']) || (int)$edit_rate['is_active'] === 1 ? 'checked' : '' ?>>
                        <label for="is_active" class="text-sm text-slate-700">Active rate</label>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[0.65rem] font-black uppercase tracking-widest text-slate-500 mb-3">Live Preview</p>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-white border border-purple-100 p-3">
                                <p class="text-[0.6rem] font-black uppercase tracking-widest text-purple-700 mb-1">Weekly</p>
                                <p class="font-semibold text-slate-900" id="preview_weekly">GHS <?= number_format($preview_weekly_amount, 2) ?></p>
                            </div>
                            <div class="rounded-lg bg-white border border-emerald-100 p-3">
                                <p class="text-[0.6rem] font-black uppercase tracking-widest text-emerald-700 mb-1">Monthly</p>
                                <p class="font-semibold text-slate-900" id="preview_monthly">GHS <?= number_format($preview_monthly_amount, 2) ?></p>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-3">Monthly based on <?= (int)$current_month_days ?> school days this month (Mon-Fri, holidays excluded).</p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors">
                            <?= $edit_rate ? 'Update Rate' : 'Create Rate' ?>
                        </button>
                        <?php if ($edit_rate): ?>
                            <a href="feeding_settings.php" class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-50 transition-colors">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="text-sm font-black text-slate-800 uppercase tracking-wider">Configured Rates</h2>
                    <span class="text-xs text-slate-500"><?= count($rates) ?> total</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Plan</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Effective</th>
                                <th class="px-4 py-3 text-left text-xs font-black text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-black text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!$rates): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">No feeding class rates yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rates as $rate): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-800"><?= htmlspecialchars($rate['class_name']) ?></td>
                                        <td class="px-4 py-3 text-sm text-slate-700 uppercase tracking-wider"><?= htmlspecialchars($rate['plan_type']) ?></td>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">GHS <?= number_format((float)$rate['amount'], 2) ?></td>
                                        <td class="px-4 py-3 text-sm text-slate-700"><?= htmlspecialchars($rate['effective_from']) ?></td>
                                        <td class="px-4 py-3">
                                            <?php if ((int)$rate['is_active'] === 1): ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-700">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex px-2 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-500">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="feeding_settings.php?edit=<?= (int)$rate['id'] ?>" class="px-2 py-1 text-xs font-semibold border border-slate-300 rounded-md hover:bg-slate-50">Edit</a>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="rate_id" value="<?= (int)$rate['id'] ?>">
                                                    <input type="hidden" name="new_state" value="<?= (int)$rate['is_active'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit" class="px-2 py-1 text-xs font-semibold border rounded-md <?= (int)$rate['is_active'] === 1 ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' ?>">
                                                        <?= (int)$rate['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            const baseAmountInput = document.getElementById('base_amount_input');
            const previewWeekly  = document.getElementById('preview_weekly');
            const previewMonthly = document.getElementById('preview_monthly');
            const currentMonthDays = <?= (int)$current_month_days ?>;

            function fmt(value) {
                const n = Number(value);
                return 'GHS ' + (Number.isFinite(n) && n > 0 ? n.toFixed(2) : '0.00');
            }

            function refreshPreview() {
                const baseAmount = Number(baseAmountInput.value || 0);
                if (previewWeekly)  previewWeekly.textContent  = fmt(baseAmount * 5);
                if (previewMonthly) previewMonthly.textContent = fmt(baseAmount * currentMonthDays);
            }

            if (baseAmountInput) {
                baseAmountInput.addEventListener('input', refreshPreview);
                refreshPreview();
            }
        })();
    </script>
</body>
</html>
