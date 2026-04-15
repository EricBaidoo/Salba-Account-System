<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';
$updated_by = $_SESSION['username'] ?? 'Admin';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Currency Settings
    if (isset($_POST['currency_symbol'])) {
        setSystemSetting($conn, 'currency_symbol', $_POST['currency_symbol'], $updated_by);
    }
    
    // 2. Invoice Config
    if (isset($_POST['invoice_prefix'])) {
        setSystemSetting($conn, 'invoice_prefix', $_POST['invoice_prefix'], $updated_by);
    }
    
    // 3. Late Payment Rules
    if (isset($_POST['late_fee_enabled'])) {
        setSystemSetting($conn, 'late_fee_enabled', '1', $updated_by);
        setSystemSetting($conn, 'late_fee_percentage', $_POST['late_fee_percentage'], $updated_by);
        setSystemSetting($conn, 'late_fee_grace_days', $_POST['late_fee_grace_days'], $updated_by);
    } else {
        setSystemSetting($conn, 'late_fee_enabled', '0', $updated_by);
    }

    // 4. Payment Allocation (Moved from System Settings)
    if (isset($_POST['payment_allocation_scope'])) {
        setSystemSetting($conn, 'payment_allocation_scope', $_POST['payment_allocation_scope'], $updated_by);
    }

    $success = "Finance parameters successfully synchronized.";
}

// Fetch current values
$currency = getSystemSetting($conn, 'currency_symbol', 'GH₵');
$invoice_prefix = getSystemSetting($conn, 'invoice_prefix', 'INV-');
$late_fee_enabled = getSystemSetting($conn, 'late_fee_enabled', '0');
$late_fee_percentage = getSystemSetting($conn, 'late_fee_percentage', '5');
$late_fee_grace_days = getSystemSetting($conn, 'late_fee_grace_days', '14');
$alloc_scope = getSystemSetting($conn, 'payment_allocation_scope', 'global');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Settings | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#F8FAFC]">
    <?php include '../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8">
        <header class="app-header !border-b-4 !border-b-emerald-600">
            <div class="flex items-center gap-2 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-emerald-600 transition-colors flex items-center gap-1 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Finance Hub
                </a>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="app-title-pill !bg-emerald-600 !text-white !px-3 !py-1 !text-[10px] !font-black !uppercase !tracking-widest !mb-2 !inline-flex">
                        <i class="fas fa-vault mr-2"></i> Fiscal Configuration
                    </div>
                    <h1 class="app-title uppercase tracking-tighter text-emerald-900">Finance & Fee Settings</h1>
                    <p class="app-subtitle">Calibrate currency, invoicing protocols, and late payment logic</p>
                </div>
            </div>
        </header>

        <div class="p-8 max-w-5xl">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl flex items-center gap-3 mb-8 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <span class="font-bold"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Section: Currency & Invoicing -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-coins text-emerald-500"></i> Currency & Invoicing
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Primary Currency Symbol</label>
                                <input type="text" name="currency_symbol" value="<?= htmlspecialchars($currency) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Invoice Number Prefix</label>
                                <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($invoice_prefix) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                <p class="text-[9px] text-slate-400 mt-2 italic">Example: <?= $invoice_prefix ?>1001</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Payment Rules -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-gavel text-amber-500"></i> Delinquency & Allocation
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Fee Payment Allocation</label>
                                <select name="payment_allocation_scope" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700 appearance-none">
                                    <option value="global" <?= $alloc_scope === 'global' ? 'selected' : '' ?>>Global (Pays oldest debts first)</option>
                                    <option value="term_year" <?= $alloc_scope === 'term_year' ? 'selected' : '' ?>>Term Context (Target term only)</option>
                                </select>
                            </div>

                            <div class="pt-4 border-t border-slate-100">
                                <label class="flex items-center gap-3 cursor-pointer group mb-4">
                                    <input type="checkbox" name="late_fee_enabled" value="1" <?= $late_fee_enabled === '1' ? 'checked' : '' ?>
                                           class="w-5 h-5 rounded-lg border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Enable Late Payment Penalty</span>
                                </label>
                                
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Penalty %</label>
                                        <input type="number" name="late_fee_percentage" value="<?= $late_fee_percentage ?>" 
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Grace Period (Days)</label>
                                        <input type="number" name="late_fee_grace_days" value="<?= $late_fee_grace_days ?>" 
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900 rounded-2xl p-8 flex items-center justify-between shadow-xl">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white text-xl">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-xs font-black uppercase tracking-[0.2em]">Secure Parameter Sync</h3>
                            <p class="text-slate-400 text-[10px] mt-1 font-medium italic">Changes will apply to all future invoices and payment distributions.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white font-black uppercase tracking-widest px-10 py-4 rounded-xl shadow-lg shadow-emerald-900/40 transition-all active:scale-95 leading-none h-fit">
                        Synchronize Global Finance
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-24 py-16 text-left border-t border-slate-200">
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">Finance Control Node &middot; Salba Institutional Oversight &middot; v9.4.0</p>
        </footer>
    </main>
</body>
</html>
