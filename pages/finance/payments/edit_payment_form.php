<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
if (!is_logged_in()) {
    header('Location: ../../../login');
    exit;
}
require_finance_access();

$payment_id = intval($_GET['payment_id'] ?? 0);
$payment = null;
if ($payment_id) {
    $stmt = $conn->prepare("SELECT p.*, s.name as student_name FROM payments p JOIN students s ON p.student_id = s.id WHERE p.id = ?");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $payment = $result->fetch_assoc();
    $stmt->close();
}

if (!$payment) {
    die('<div class="p-10 text-center font-black uppercase tracking-widest text-rose-500">Remittance node not found.</div>');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $receipt_no = trim($_POST['receipt_no']);
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("UPDATE payments SET amount = ?, payment_date = ?, receipt_no = ?, description = ? WHERE id = ?");
    $stmt->bind_param("dsssi", $amount, $payment_date, $receipt_no, $description, $payment_id);
    
    if ($stmt->execute()) {
        // Proportional adjustment for multiple allocations
        $alloc_stmt = $conn->prepare("SELECT id, amount FROM payment_allocations WHERE payment_id = ?");
        $alloc_stmt->bind_param("i", $payment_id);
        $alloc_stmt->execute();
        $alloc_result = $alloc_stmt->get_result();
        $allocs = [];
        $old_total = 0;
        while ($row = $alloc_result->fetch_assoc()) {
            $allocs[] = $row;
            $old_total += $row['amount'];
        }
        $alloc_stmt->close();

        if ($old_total > 0 && count($allocs) > 0) {
            foreach ($allocs as $alloc) {
                $new_alloc = round($alloc['amount'] * ($amount / $old_total), 2);
                $update_alloc = $conn->prepare("UPDATE payment_allocations SET amount = ? WHERE id = ?");
                $update_alloc->bind_param("di", $new_alloc, $alloc['id']);
                $update_alloc->execute();
                $update_alloc->close();
            }
        }
        $success = "Remittance record synchronized successfully.";
        // Refresh local data
        $payment['amount'] = $amount;
        $payment['payment_date'] = $payment_date;
        $payment['receipt_no'] = $receipt_no;
        $payment['description'] = $description;
    } else {
        $error = "Synchronization failure: " . $stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjust Remittance | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
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
                <span class="text-blue-600">Adjust Remittance</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-edit text-emerald-600"></i> Adjust Remittance
                    </h1>
                    <div class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                        <span class="font-semibold text-slate-800">Student:</span>
                        <span><?= htmlspecialchars($payment['student_name']) ?></span>
                    </div>
                </div>
                <a href="../reports/student_balance_details.php?id=<?= $payment['student_id'] ?>" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Exit Audit
                </a>
            </div>
        </div>

        <div class="px-6 max-w-4xl">
            <?php if ($success): ?>
                <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg text-sm flex gap-3 items-center shadow-sm">
                    <i class="fas fa-check-circle"></i>
                    <span class="font-medium"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Parameters Hub -->
                <section class="bg-white rounded-xl p-6 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="fas fa-sliders-h text-slate-400"></i> Fiscal Parameters</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Remittance Value (GHS)</label>
                                <input type="number" step="0.01" name="amount" value="<?= $payment['amount'] ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-semibold text-emerald-600 outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-all">
                                <p class="text-[10px] text-slate-500 mt-1 font-medium italic">* Allocation weights will be proportionally updated.</p>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Verification Date</label>
                                <input type="date" name="payment_date" value="<?= $payment['payment_date'] ?>" required class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Institutional Receipt No</label>
                                <input type="text" name="receipt_no" value="<?= htmlspecialchars($payment['receipt_no'] ?: '') ?>" placeholder="e.g. REC-1001" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2.5 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2 block">Administrative Logic / Description</label>
                                <textarea name="description" rows="2" class="w-full bg-white border border-slate-300 rounded-lg px-4 py-2 text-sm font-medium text-slate-900 outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all"><?= htmlspecialchars($payment['description'] ?: '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Warning Zone -->
                <div class="bg-amber-50 p-4 rounded-lg border border-amber-200 flex items-start gap-3">
                    <i class="fas fa-triangle-exclamation text-amber-500 mt-0.5"></i>
                    <div>
                        <h4 class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-1">Fiscal Re-balancing Awareness</h4>
                        <p class="text-xs font-medium text-amber-700 leading-relaxed italic">Modifying the aggregate remittance value will automatically re-allocate portions to assigned student fees based on existing proportionality. Ensure student account integrity after synchronization.</p>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-6 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white border border-slate-200 rounded-lg flex items-center justify-center text-emerald-500 text-lg shadow-sm">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Parameter Sync Authorized</h4>
                            <p class="text-slate-500 text-xs font-medium mt-0.5 italic">Recalibrating student ledger balances.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg transition-all shadow-sm flex items-center gap-2">
                        <i class="fas fa-save"></i> Sync Remittance Node
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
