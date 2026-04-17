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
<body class="text-slate-900 leading-relaxed">
    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 p-10 min-h-screen">
        <!-- Header -->
        <header class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-[0.2em] mb-3">
                    <span class="w-8 h-[2px] bg-emerald-600"></span>
                    Audit Node
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tight">Adjust <span class="text-emerald-600">Remittance</span></h1>
                <div class="mt-3 flex items-center gap-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Student:</span>
                    <span class="text-xs font-black text-slate-700 uppercase"><?= htmlspecialchars($payment['student_name']) ?></span>
                </div>
            </div>
            <a href="../reports/student_balance_details.php?id=<?= $payment['student_id'] ?>" class="bg-white border border-slate-200 text-slate-400 font-black text-[10px] uppercase tracking-widest px-8 py-4 rounded-2xl hover:text-slate-600 hover:bg-slate-50 transition-all leading-none">
                <i class="fas fa-arrow-left mr-2"></i> Exit Audit
            </a>
        </header>

        <div class="max-w-3xl">
            <?php if ($success): ?>
                <div class="mb-10 bg-emerald-50 border border-emerald-100 p-6 rounded-[2rem] flex items-center gap-4 text-emerald-800 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="w-10 h-10 bg-emerald-500 text-white rounded-full flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <i class="fas fa-check"></i>
                    </div>
                    <p class="font-bold text-sm tracking-tight"><?= $success ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-10">
                <!-- Parameters Hub -->
                <section class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-sm">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em] mb-10">Fiscal Parameters</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="space-y-8">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Remittance Value (GHS)</label>
                                <input type="number" step="0.01" name="amount" value="<?= $payment['amount'] ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
                                <p class="text-[9px] text-slate-400 mt-2 font-medium italic">* Allocation weights will be proportionally updated.</p>
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Verification Date</label>
                                <input type="date" name="payment_date" value="<?= $payment['payment_date'] ?>" required class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
                            </div>
                        </div>
                        <div class="space-y-8">
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Institutional Receipt No</label>
                                <input type="text" name="receipt_no" value="<?= htmlspecialchars($payment['receipt_no'] ?: '') ?>" placeholder="e.g. REC-1001" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-black text-slate-900 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all">
                            </div>
                            <div>
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Administrative Logic / Description</label>
                                <textarea name="description" rows="1" class="w-full bg-slate-50 border border-slate-100 rounded-2xl px-6 py-4 text-sm font-medium text-slate-600 outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-indigo-500 transition-all"><?= htmlspecialchars($payment['description'] ?: '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Warning Zone -->
                <div class="bg-amber-50 p-6 rounded-3xl border border-amber-100 flex items-start gap-4">
                    <i class="fas fa-triangle-exclamation text-amber-500 mt-1"></i>
                    <div>
                        <h4 class="text-[10px] font-black text-amber-700 uppercase tracking-widest mb-1">Fiscal Re-balancing Awareness</h4>
                        <p class="text-[9px] font-medium text-amber-600 leading-relaxed italic">Modifying the aggregate remittance value will automatically re-allocate portions to assigned student fees based on existing proportionality. Ensure student account integrity after synchronization.</p>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="bg-slate-900 rounded-[2.5rem] p-10 text-white border border-slate-800 shadow-2xl flex flex-wrap items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-14 h-14 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-xl">
                            <i class="fas fa-shield-check"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-black uppercase tracking-[0.2em] text-emerald-400">Parameter Sync Authorized</h4>
                            <p class="text-slate-500 text-[10px] font-bold mt-1 uppercase leading-none italic">Recalibrating student ledger balances.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-black text-[10px] uppercase tracking-widest px-10 py-5 rounded-2xl transition-all shadow-xl shadow-emerald-600/20 active:scale-95 leading-none h-fit">
                        Sync Remittance Node
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-20 py-10 border-t border-slate-200 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
            Salba Montessori &middot; Financial Audit Node &middot; v9.5.0
        </footer>
    </main>
</body>
</html>
