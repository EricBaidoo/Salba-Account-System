<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';
include '../../includes/semester_bill_functions.php'; // Included for getting/saving bill settings

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';
$updated_by = $_SESSION['username'] ?? 'Admin';

// Default active semester context for Bill Settings
$active_semester = getCurrentSemester($conn);
$active_year = getAcademicYear($conn);

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

    // 5. Semester Bill Settings JSON Saving
    if (isset($_POST['bank_name'])) {
        // Collect Bank Details
        $bank = [
            'title' => $_POST['bank_title'] ?? 'BANK DEPOSIT/TRANSFER',
            'account_name' => $_POST['bank_account_name'] ?? '',
            'account_number' => $_POST['bank_account_number'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? ''
        ];
        // Collect MoMo Details
        $momo = [
            'title' => $_POST['momo_title'] ?? 'MOBILE MONEY (MOMO)',
            'number' => $_POST['momo_number'] ?? '',
            'name' => $_POST['momo_name'] ?? ''
        ];
        
        $payment_reference = $_POST['payment_reference'] ?? '';

        // Collect Notes
        $notes = [];
        if (!empty($_POST['bill_notes'])) {
            $raw_notes = explode("\n", $_POST['bill_notes']);
            foreach ($raw_notes as $n) {
                if (trim($n) !== '') $notes[] = trim($n);
            }
        }
        
        // Collect Payment Plan (we'll just support a static 3 installments for now, or build array dynamically if passed as arrays)
        $payment_plan = [];
        if (isset($_POST['plan_name']) && is_array($_POST['plan_name'])) {
            for ($i = 0; $i < count($_POST['plan_name']); $i++) {
                if (trim($_POST['plan_name'][$i]) !== '') {
                    $payment_plan[] = [
                        'name' => trim($_POST['plan_name'][$i]),
                        'percent' => floatval($_POST['plan_percent'][$i]),
                        'due_date' => trim($_POST['plan_due'][$i])
                    ];
                }
            }
        }
        if (empty($payment_plan)) {
            $payment_plan = getDefaultSemesterInvoiceSettings()['payment_plan'];
        }

        $bill_settings = [
            'payment_plan' => $payment_plan,
            'payment_modes' => [
                'bank' => $bank,
                'momo' => $momo,
                'payment_reference' => $payment_reference
            ],
            'notes' => $notes
        ];

        // Ensure key format exactly exactly matches what the backend uses 
        $target_key = getSemesterInvoiceSettingsKey($active_semester, $active_year);
        $encoded_json = json_encode($bill_settings);
        
        // Save to system settings DB
        setSystemSetting($conn, $target_key, $encoded_json, $updated_by);
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

$active_bill_settings = getSemesterInvoiceSettings($conn, $active_semester, $active_year);
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
    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
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
                    <p class="app-subtitle">Calibrate currency, billing templates, and late payment logic</p>
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
                    
                    <!-- Section: Currency & Naming -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-coins text-emerald-500"></i> Currency & Core Output
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Primary Currency Symbol</label>
                                <input type="text" name="currency_symbol" value="<?= htmlspecialchars($currency) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Bill Number Prefix</label>
                                <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($invoice_prefix) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                <p class="text-[9px] text-slate-400 mt-2 italic">Example: <?= htmlspecialchars($invoice_prefix) ?>1001</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Fee Payment Allocation</label>
                                <select name="payment_allocation_scope" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700 appearance-none">
                                    <option value="global" <?= $alloc_scope === 'global' ? 'selected' : '' ?>>Global (Pays oldest debts first)</option>
                                    <option value="term_year" <?= $alloc_scope === 'term_year' ? 'selected' : '' ?>>Semester Context (Target semester only)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Late Payment Rules -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-gavel text-amber-500"></i> Delinquency System
                            </h2>
                        </div>
                        <div class="p-6 space-y-6 flex flex-col justify-center h-[calc(100%-60px)]">
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer group mb-6">
                                    <input type="checkbox" name="late_fee_enabled" value="1" <?= $late_fee_enabled === '1' ? 'checked' : '' ?>
                                           class="w-5 h-5 rounded-lg border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Enable Late Payment Penalty</span>
                                </label>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    
                    <!-- Section: Semester Bill Settings (Full Width) -->
                    <div class="md:col-span-2 bg-gradient-to-b from-slate-800 to-slate-900 rounded-2xl shadow-xl shadow-slate-900/10 border border-slate-700 overflow-hidden h-fit">
                        <div class="px-8 py-5 border-b border-slate-700/50 bg-slate-800 flex justify-between items-center">
                            <h2 class="text-xs font-black text-white uppercase tracking-widest flex items-center gap-3">
                                <i class="fas fa-file-invoice text-emerald-400"></i> 
                                Semester Bill Footer Settings
                            </h2>
                            <div class="px-3 py-1 rounded bg-slate-700/50 text-slate-300 text-[10px] uppercase font-black tracking-widest">
                                Editing For: <?= htmlspecialchars($active_semester) ?> (<?= htmlspecialchars($active_year) ?>)
                            </div>
                        </div>
                        <div class="p-8 space-y-8">
                            
                            <!-- Payment Nodes Layer -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <h4 class="text-[10px] uppercase text-emerald-400 font-black tracking-widest border-b border-slate-700 pb-2">Bank Mode Layout</h4>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Section Title</label>
                                        <input type="text" name="bank_title" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['title']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Account Name</label>
                                        <input type="text" name="bank_account_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['account_name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Account Number</label>
                                        <input type="text" name="bank_account_number" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['account_number']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Bank Name</label>
                                        <input type="text" name="bank_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['bank_name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <h4 class="text-[10px] uppercase text-emerald-400 font-black tracking-widest border-b border-slate-700 pb-2">MoMo Mode Layout</h4>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Section Title</label>
                                        <input type="text" name="momo_title" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['title']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Registered Name</label>
                                        <input type="text" name="momo_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Mobile Number</label>
                                        <input type="text" name="momo_number" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['number']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div class="pt-2">
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Global Reference Note</label>
                                        <input type="text" name="payment_reference" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['payment_reference']) ?>" class="w-full mt-1 bg-indigo-500/10 border border-indigo-500/30 text-indigo-200 rounded-lg px-3 py-2 text-xs focus:border-indigo-400 outline-none">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Plan -->
                            <div class="border-t border-slate-700 pt-6">
                                <h4 class="text-[10px] uppercase text-emerald-400 font-black tracking-widest mb-4">Payment Plan Table</h4>
                                <div class="grid grid-cols-12 gap-2 text-[9px] uppercase font-black text-slate-500 tracking-widest mb-2 px-2">
                                    <div class="col-span-5">Installment Label</div>
                                    <div class="col-span-3">Percentage (%)</div>
                                    <div class="col-span-4">Due Date String</div>
                                </div>
                                <?php 
                                $plan_idx = 0;
                                foreach($active_bill_settings['payment_plan'] as $plan): 
                                ?>
                                <div class="grid grid-cols-12 gap-2 mb-2">
                                    <div class="col-span-5">
                                        <input type="text" name="plan_name[]" value="<?= htmlspecialchars($plan['name']) ?>" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" name="plan_percent[]" value="<?= htmlspecialchars($plan['percent']) ?>" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none">
                                    </div>
                                    <div class="col-span-4">
                                        <input type="text" name="plan_due[]" placeholder="e.g. 1st Oct" value="<?= htmlspecialchars($plan['due_date']) ?>" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none">
                                    </div>
                                </div>
                                <?php 
                                $plan_idx++;
                                endforeach; 
                                ?>
                                <!-- Empty default entry if none -->
                                <?php if($plan_idx == 0): ?>
                                <div class="grid grid-cols-12 gap-2 mb-2">
                                    <div class="col-span-5"><input type="text" name="plan_name[]" value="Full Payment" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none"></div>
                                    <div class="col-span-3"><input type="number" name="plan_percent[]" value="100" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none"></div>
                                    <div class="col-span-4"><input type="text" name="plan_due[]" placeholder="-" class="w-full bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs outline-none"></div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Policy Notes -->
                            <div class="border-t border-slate-700 pt-6">
                                <h4 class="text-[10px] uppercase text-emerald-400 font-black tracking-widest mb-4">Policy Footer Notes (One per line)</h4>
                                <textarea name="bill_notes" rows="4" class="w-full bg-slate-800 border border-slate-700 text-white rounded-xl px-4 py-3 text-xs leading-loose outline-none"><?= htmlspecialchars(implode("\n", $active_bill_settings['notes'])) ?></textarea>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <div class="bg-emerald-900 rounded-2xl p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-xl shadow-emerald-900/10 border border-emerald-800">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-emerald-500 rounded-xl flex items-center justify-center text-white text-xl">
                            <i class="fas fa-shield-halved"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-xs font-black uppercase tracking-[0.2em]">Secure Parameter Sync</h3>
                            <p class="text-emerald-200/50 text-[10px] mt-1 font-medium italic">Commits changes globally to the current academic year block.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-400 text-white font-black uppercase tracking-widest px-8 py-4 rounded-xl shadow-lg transition-all active:scale-95 leading-none h-fit">
                        Synchronize Settings
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
