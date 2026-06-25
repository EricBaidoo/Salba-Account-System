<?php
ob_start();
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
$error   = '';
$updated_by = $_SESSION['username'] ?? 'Admin';

// ── Expense Category AJAX handler — must run before anything else outputs ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ec_action'])) {
    ob_clean(); // discard any warnings from includes
    header('Content-Type: application/json');
    $ec_action = $_POST['ec_action'];

    if ($ec_action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { echo json_encode(['success' => false, 'message' => 'Name is required.']); exit; }
        $esc = $conn->real_escape_string($name);
        if ($conn->query("SELECT id FROM expense_categories WHERE name = '$esc' LIMIT 1")->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A category with that name already exists.']); exit;
        }
        $stmt = $conn->prepare("INSERT INTO expense_categories (name) VALUES (?)");
        $stmt->bind_param('s', $name); $stmt->execute();
        echo json_encode(['success' => true, 'id' => $conn->insert_id, 'name' => $name]); exit;
    }

    if ($ec_action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$id || $name === '') { echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit; }
        $stmt = $conn->prepare("UPDATE expense_categories SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id); $stmt->execute();
        echo json_encode(['success' => true, 'name' => $name]); exit;
    }

    if ($ec_action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $in_use = (int)$conn->query("SELECT COUNT(*) as c FROM expenses WHERE category_id = $id")->fetch_assoc()['c'];
        if ($in_use > 0) {
            echo json_encode(['success' => false, 'message' => "Cannot delete — $in_use expense record(s) use this category."]); exit;
        }
        $conn->query("DELETE FROM expense_categories WHERE id = $id");
        echo json_encode(['success' => $conn->affected_rows > 0, 'message' => $conn->affected_rows > 0 ? '' : 'Category not found.']); exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']); exit;
}

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

    // 4.5 Payroll Parameters
    if (isset($_POST['ssnit_tier1_employee'])) {
        setSystemSetting($conn, 'ssnit_tier1_employee', $_POST['ssnit_tier1_employee'], $updated_by);
        setSystemSetting($conn, 'ssnit_tier2_employee', $_POST['ssnit_tier2_employee'], $updated_by);
        setSystemSetting($conn, 'ssnit_tier1_employer', $_POST['ssnit_tier1_employer'], $updated_by);
        
        $global_taxes = [];
        if (isset($_POST['tax_name']) && is_array($_POST['tax_name'])) {
            for ($i = 0; $i < count($_POST['tax_name']); $i++) {
                if (trim($_POST['tax_name'][$i]) !== '') {
                    $global_taxes[] = [
                        'name' => trim($_POST['tax_name'][$i]),
                        'percent' => floatval($_POST['tax_percent'][$i])
                    ];
                }
            }
        }
        setSystemSetting($conn, 'global_taxes', json_encode($global_taxes), $updated_by);
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

$ssnit_tier1_employee = getSystemSetting($conn, 'ssnit_tier1_employee', '5.5');
$ssnit_tier2_employee = getSystemSetting($conn, 'ssnit_tier2_employee', '5.0');
$ssnit_tier1_employer = getSystemSetting($conn, 'ssnit_tier1_employer', '13.0');

$global_taxes_json = getSystemSetting($conn, 'global_taxes', '[]');
$global_taxes = json_decode($global_taxes_json, true) ?: [];

$active_bill_settings = getSemesterInvoiceSettings($conn, $active_semester, $active_year);
$expense_categories = $conn->query("SELECT * FROM expense_categories ORDER BY name ASC");
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
        <div class="bg-white border-b border-slate-200 px-6 py-6 sticky top-0 z-30 mb-6">
            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-2 uppercase tracking-wider">
                <a href="dashboard.php" class="hover:text-emerald-600 transition-colors flex items-center gap-1.5"><i class="fas fa-home"></i> Finance</a>
                <span>/</span>
                <span class="text-emerald-600">Settings</span>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fas fa-vault text-emerald-600"></i> Fiscal Configuration
                    </h1>
                    <p class="text-slate-500 mt-1 text-sm">Calibrate currency, billing templates, and late payment logic.</p>
                </div>
            </div>
        </div>

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
                                <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">Primary Currency Symbol</label>
                                <input type="text" name="currency_symbol" value="<?= htmlspecialchars($currency) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">Bill Number Prefix</label>
                                <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($invoice_prefix) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                <p class="text-[0.5625rem] text-slate-400 mt-2 italic">Example: <?= htmlspecialchars($invoice_prefix) ?>1001</p>
                            </div>
                            <div>
                                <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">Fee Payment Allocation</label>
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
                        <div class="p-6 space-y-6 flex flex-col justify-center h-[calc(100%-3.75rem)]">
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer group mb-6">
                                    <input type="checkbox" name="late_fee_enabled" value="1" <?= $late_fee_enabled === '1' ? 'checked' : '' ?>
                                           class="w-5 h-5 rounded-lg border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Enable Late Payment Penalty</span>
                                </label>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">Penalty %</label>
                                        <input type="number" name="late_fee_percentage" value="<?= $late_fee_percentage ?>" 
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                    </div>
                                    <div>
                                        <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">Grace Period (Days)</label>
                                        <input type="number" name="late_fee_grace_days" value="<?= $late_fee_grace_days ?>" 
                                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section: Payroll Parameters -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-percent text-indigo-500"></i> Payroll Parameters
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">SSNIT Tier 1 (Employee %)</label>
                                    <input type="number" step="0.01" name="ssnit_tier1_employee" value="<?= htmlspecialchars($ssnit_tier1_employee) ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                </div>
                                <div>
                                    <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">SSNIT Tier 2 (Employee %)</label>
                                    <input type="number" step="0.01" name="ssnit_tier2_employee" value="<?= htmlspecialchars($ssnit_tier2_employee) ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                </div>
                                <div>
                                    <label class="block text-[0.625rem] font-black text-slate-400 uppercase tracking-widest mb-2">SSNIT Tier 1 (Employer %)</label>
                                    <input type="number" step="0.01" name="ssnit_tier1_employer" value="<?= htmlspecialchars($ssnit_tier1_employer) ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none font-bold text-slate-700">
                                </div>
                            </div>
                            
                            <div class="border-t border-slate-100 pt-4">
                                <div class="flex justify-between items-center mb-4">
                                    <h4 class="text-[0.625rem] uppercase text-indigo-500 font-black tracking-widest">Global Taxes</h4>
                                    <button type="button" onclick="addTaxRow()" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-bold hover:bg-indigo-100 transition-colors">
                                        <i class="fas fa-plus mr-1"></i> Add Tax
                                    </button>
                                </div>
                                <div id="taxesContainer" class="space-y-3">
                                    <?php foreach($global_taxes as $tax): ?>
                                    <div class="grid grid-cols-12 gap-3 items-center tax-row">
                                        <div class="col-span-6">
                                            <input type="text" name="tax_name[]" value="<?= htmlspecialchars($tax['name']) ?>" placeholder="Tax Name (e.g. PAYE)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 text-sm">
                                        </div>
                                        <div class="col-span-5">
                                            <input type="number" step="0.01" name="tax_percent[]" value="<?= htmlspecialchars($tax['percent']) ?>" placeholder="Percentage (%)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 text-sm">
                                        </div>
                                        <div class="col-span-1 text-right">
                                            <button type="button" onclick="this.closest('.tax-row').remove()" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
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
                            <div class="px-3 py-1 rounded bg-slate-700/50 text-slate-300 text-[0.625rem] uppercase font-black tracking-widest">
                                Editing For: <?= htmlspecialchars($active_semester) ?> (<?= htmlspecialchars($active_year) ?>)
                            </div>
                        </div>
                        <div class="p-8 space-y-8">
                            
                            <!-- Payment Nodes Layer -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-4">
                                    <h4 class="text-[0.625rem] uppercase text-emerald-400 font-black tracking-widest border-b border-slate-700 pb-2">Bank Mode Layout</h4>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Section Title</label>
                                        <input type="text" name="bank_title" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['title']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Account Name</label>
                                        <input type="text" name="bank_account_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['account_name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Account Number</label>
                                        <input type="text" name="bank_account_number" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['account_number']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Bank Name</label>
                                        <input type="text" name="bank_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['bank']['bank_name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <h4 class="text-[0.625rem] uppercase text-emerald-400 font-black tracking-widest border-b border-slate-700 pb-2">MoMo Mode Layout</h4>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Section Title</label>
                                        <input type="text" name="momo_title" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['title']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Registered Name</label>
                                        <input type="text" name="momo_name" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['name']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div>
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Mobile Number</label>
                                        <input type="text" name="momo_number" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['momo']['number']) ?>" class="w-full mt-1 bg-slate-800 border border-slate-700 text-white rounded-lg px-3 py-2 text-xs focus:border-emerald-500 outline-none">
                                    </div>
                                    <div class="pt-2">
                                        <label class="text-[0.625rem] font-black text-slate-500 uppercase tracking-widest">Global Reference Note</label>
                                        <input type="text" name="payment_reference" value="<?= htmlspecialchars($active_bill_settings['payment_modes']['payment_reference']) ?>" class="w-full mt-1 bg-indigo-500/10 border border-indigo-500/30 text-indigo-200 rounded-lg px-3 py-2 text-xs focus:border-indigo-400 outline-none">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Plan -->
                            <div class="border-t border-slate-700 pt-6">
                                <h4 class="text-[0.625rem] uppercase text-emerald-400 font-black tracking-widest mb-4">Payment Plan Table</h4>
                                <div class="grid grid-cols-12 gap-2 text-[0.5625rem] uppercase font-black text-slate-500 tracking-widest mb-2 px-2">
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
                                <h4 class="text-[0.625rem] uppercase text-emerald-400 font-black tracking-widest mb-4">Policy Footer Notes (One per line)</h4>
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
                            <p class="text-emerald-200/50 text-[0.625rem] mt-1 font-medium italic">Commits changes globally to the current academic year block.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-emerald-500 hover:bg-emerald-400 text-white font-black uppercase tracking-widest px-8 py-4 rounded-xl shadow-lg transition-all active:scale-95 leading-none h-fit">
                        Synchronize Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- ── Expense Categories ── -->
        <div class="mt-10 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-tags text-rose-500"></i> Expense Categories
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Manage the categories used to classify expenses and budgets.</p>
                </div>
                <button onclick="openAddCatModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-xs font-semibold rounded-lg hover:bg-rose-700 shadow-sm transition-all">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            <div class="p-6">
                <div id="ec-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <?php if ($expense_categories && $expense_categories->num_rows > 0): ?>
                        <?php while ($cat = $expense_categories->fetch_assoc()): ?>
                        <div id="ec-item-<?= $cat['id'] ?>" class="flex items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 group">
                            <span class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                                <i class="fas fa-circle-dot text-rose-400 text-[10px]"></i>
                                <span id="ec-name-<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></span>
                            </span>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button onclick="openEditCatModal(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['name'])) ?>')"
                                    class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors text-xs">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteCat(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['name'])) ?>')"
                                    class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-colors text-xs">
                                    <i class="fas fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div id="ec-empty" class="col-span-3 text-center py-8 text-slate-400 text-sm">No categories yet. Add one above.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Add Category Modal -->
        <div id="addCatModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2"><i class="fas fa-plus text-rose-500"></i> Add Category</h3>
                    <button onclick="closeModal('addCatModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <div id="addCatError" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2"></div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Category Name</label>
                        <input type="text" id="addCatName" placeholder="e.g. Transportation"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                    </div>
                    <div class="flex gap-3 justify-end pt-2">
                        <button onclick="closeModal('addCatModal')" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Cancel</button>
                        <button onclick="submitAddCat()" class="px-4 py-2 bg-rose-600 text-white text-sm font-semibold rounded-lg hover:bg-rose-700 shadow-sm">Add Category</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div id="editCatModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-sm">
                <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900 flex items-center gap-2"><i class="fas fa-edit text-blue-500"></i> Edit Category</h3>
                    <button onclick="closeModal('editCatModal')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="editCatId">
                    <div id="editCatError" class="hidden text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-3 py-2"></div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Category Name</label>
                        <input type="text" id="editCatName"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    </div>
                    <div class="flex gap-3 justify-end pt-2">
                        <button onclick="closeModal('editCatModal')" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50">Cancel</button>
                        <button onclick="submitEditCat()" class="px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <footer class="mt-24 py-16 text-left border-t border-slate-200">
            <p class="text-[0.625rem] font-black text-slate-300 uppercase tracking-[0.5em]">Finance Control Node &middot; Salba Institutional Oversight &middot; v9.4.0</p>
        </footer>
    </main>
    <script>
        function addTaxRow() {
            const container = document.getElementById('taxesContainer');
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-3 items-center tax-row';
            row.innerHTML = `
                <div class="col-span-6">
                    <input type="text" name="tax_name[]" placeholder="Tax Name (e.g. PAYE)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 text-sm">
                </div>
                <div class="col-span-5">
                    <input type="number" step="0.01" name="tax_percent[]" placeholder="Percentage (%)" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 text-sm">
                </div>
                <div class="col-span-1 text-right">
                    <button type="button" onclick="this.closest('.tax-row').remove()" class="w-8 h-8 rounded-lg bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.appendChild(row);
        }

        // ── Expense Categories ──
        const EC_URL = window.location.pathname + window.location.search;

        function closeModal(id) {
            const m = document.getElementById(id);
            m.classList.add('hidden'); m.classList.remove('flex');
        }
        function openModal(id) {
            const m = document.getElementById(id);
            m.classList.remove('hidden'); m.classList.add('flex');
        }

        function openAddCatModal() {
            document.getElementById('addCatName').value = '';
            document.getElementById('addCatError').classList.add('hidden');
            openModal('addCatModal');
            setTimeout(() => document.getElementById('addCatName').focus(), 100);
        }

        function openEditCatModal(id, name) {
            document.getElementById('editCatId').value = id;
            document.getElementById('editCatName').value = name;
            document.getElementById('editCatError').classList.add('hidden');
            openModal('editCatModal');
            setTimeout(() => document.getElementById('editCatName').focus(), 100);
        }

        function ecPost(data) {
            return fetch(EC_URL, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(data).toString()
            }).then(r => r.json());
        }

        function buildCatItem(id, name) {
            return `<div id="ec-item-${id}" class="flex items-center justify-between gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 group">
                <span class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                    <i class="fas fa-circle-dot text-rose-400 text-[10px]"></i>
                    <span id="ec-name-${id}">${name}</span>
                </span>
                <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button onclick="openEditCatModal(${id}, '${name.replace(/'/g,"\\\'")}')"
                        class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors text-xs">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="deleteCat(${id}, '${name.replace(/'/g,"\\\'")}')"
                        class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-colors text-xs">
                        <i class="fas fa-trash-can"></i>
                    </button>
                </div>
            </div>`;
        }

        function submitAddCat() {
            const name = document.getElementById('addCatName').value.trim();
            if (!name) return;
            ecPost({ec_action: 'add', name}).then(d => {
                if (!d.success) {
                    const err = document.getElementById('addCatError');
                    err.textContent = d.message; err.classList.remove('hidden'); return;
                }
                document.getElementById('ec-empty')?.remove();
                document.getElementById('ec-list').insertAdjacentHTML('beforeend', buildCatItem(d.id, d.name));
                closeModal('addCatModal');
            });
        }

        function submitEditCat() {
            const id   = document.getElementById('editCatId').value;
            const name = document.getElementById('editCatName').value.trim();
            if (!name) return;
            ecPost({ec_action: 'edit', id, name}).then(d => {
                if (!d.success) {
                    const err = document.getElementById('editCatError');
                    err.textContent = d.message; err.classList.remove('hidden'); return;
                }
                document.getElementById('ec-name-' + id).textContent = d.name;
                // Refresh button data attributes
                const item = document.getElementById('ec-item-' + id);
                item.querySelectorAll('button')[0].setAttribute('onclick', `openEditCatModal(${id}, '${d.name.replace(/'/g,"\\'")}')`);
                item.querySelectorAll('button')[1].setAttribute('onclick', `deleteCat(${id}, '${d.name.replace(/'/g,"\\'")}')`);
                closeModal('editCatModal');
            });
        }

        function deleteCat(id, name) {
            appConfirm(`Delete category "${name}"? This cannot be undone.`, {
                onConfirm: function() {
                    ecPost({ec_action: 'delete', id}).then(d => {
                        if (!d.success) { alert(d.message); return; }
                        document.getElementById('ec-item-' + id)?.remove();
                        if (!document.querySelector('#ec-list .group')) {
                            document.getElementById('ec-list').innerHTML = '<div id="ec-empty" class="col-span-3 text-center py-8 text-slate-400 text-sm">No categories yet.</div>';
                        }
                    });
                }
            });
        }

        // Allow Enter key to submit category modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                if (!document.getElementById('addCatModal').classList.contains('hidden')) submitAddCat();
                if (!document.getElementById('editCatModal').classList.contains('hidden')) submitEditCat();
            }
        });
    </script>
</body>
</html>
