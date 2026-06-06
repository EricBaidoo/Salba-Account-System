<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login');
    exit;
}

$updated_by = $_SESSION['username'] ?? 'Admin';

// ---------------------------------------------------------
// PHASE 1: DATABASE INITIALIZATION (HYBRID)
// ---------------------------------------------------------
// Temporarily drop to rebuild structure cleanly during this pivot
if (isset($_GET['reset_tables'])) {
    $conn->query("DROP TABLE IF EXISTS sms_providers");
    $conn->query("DROP TABLE IF EXISTS sms_integrations");
    header("Location: settings.php");
    exit;
}

// Force column additions silently in case the table hasn't updated yet
try {
    $conn->query("ALTER TABLE sms_providers ADD COLUMN engine_type VARCHAR(50) DEFAULT 'custom' AFTER name");
    $conn->query("ALTER TABLE sms_providers ADD COLUMN api_key VARCHAR(255) NULL");
    $conn->query("ALTER TABLE sms_providers ADD COLUMN active_sender_id VARCHAR(50) NULL");
    $conn->query("ALTER TABLE sms_providers ADD COLUMN balance_endpoint_url VARCHAR(255) NULL");
} catch(Exception $e) {}

try {
    $conn->query("ALTER TABLE sms_templates ADD COLUMN sender_id VARCHAR(15) NULL AFTER template_name");
} catch(Exception $e) {}

$conn->query("CREATE TABLE IF NOT EXISTS sms_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    engine_type VARCHAR(50) DEFAULT 'custom',
    endpoint_url VARCHAR(255) NULL,
    balance_endpoint_url VARCHAR(255) NULL,
    http_method VARCHAR(10) DEFAULT 'POST',
    payload_type VARCHAR(20) DEFAULT 'json',
    auth_header TEXT,
    param_recipient VARCHAR(50) DEFAULT 'to',
    param_message VARCHAR(50) DEFAULT 'msg',
    param_sender VARCHAR(50) DEFAULT 'sender_id',
    api_key VARCHAR(255) NULL,
    active_sender_id VARCHAR(50) NULL,
    success_keyword VARCHAR(100) DEFAULT 'success',
    is_active TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(150) NOT NULL,
    message_body TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Add 'title' column to parents table if it doesn't exist
$title_check = $conn->query("SHOW COLUMNS FROM `parents` LIKE 'title'");
if ($title_check && $title_check->num_rows === 0) {
    $conn->query("ALTER TABLE `parents` ADD COLUMN `title` VARCHAR(50) NULL AFTER `id`");
}

// PRE-SEED TEMPLATES
$seed_templates = [
    ['Payment Receipt', 'Payment of GHS {amount} received for {student_name}. Remaining balance: GHS {balance}. Thank you!'],
    ['Overdue Fee Reminder', 'Dear Parent, {student_name} has an overdue balance of GHS {balance}. Please settle immediately.'],
    ['Birthday Wish', 'Happy Birthday {student_name}! Wishing you a wonderful day from Salba Montessori.'],
    ['Low SMS Balance Alert', 'URGENT: Salba SMS balance is low ({balance} units). Please top up.'],
    ['Welcome Message', 'Welcome to Salba Montessori, {student_name}!']
];

$stmt_check = $conn->prepare("SELECT id FROM sms_templates WHERE template_name = ?");
$stmt_insert = $conn->prepare("INSERT INTO sms_templates (template_name, message_body) VALUES (?, ?)");

foreach ($seed_templates as $st) {
    $stmt_check->bind_param("s", $st[0]);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        $stmt_insert->bind_param("ss", $st[0], $st[1]);
        $stmt_insert->execute();
    }
}

// ---------------------------------------------------------
// HANDLE FORM SUBMISSIONS (TRADITIONAL PHP - NO AJAX)
// ---------------------------------------------------------
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // 1. Save Automation Triggers
    if ($_POST['action'] === 'save_automations') {
        $triggers = ['payment', 'absence', 'grading', 'birthday', 'overdue', 'upcoming', 'low_balance', 'welcome'];
        foreach($triggers as $t) {
            $is_on = isset($_POST["trigger_$t"]) ? '1' : '0';
            $tpl_id = $_POST["template_$t"] ?? '0';
            setSystemSetting($conn, "trigger_$t", $is_on, $updated_by);
            setSystemSetting($conn, "template_$t", $tpl_id, $updated_by);
        }
        $success = "Automation triggers updated successfully.";
    }

    // 2. Save Provider (Create or Edit)
    if ($_POST['action'] === 'save_provider') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = $_POST['name'];
        $engine = $_POST['engine_type'];
        $api_key = $_POST['api_key'] ?? null;
        $sender = $_POST['active_sender_id'] ?? null;
        $url = $_POST['endpoint_url'] ?? null;
        $b_url = $_POST['balance_endpoint_url'] ?? null;
        $method = $_POST['http_method'] ?? 'POST';
        $payload = $_POST['payload_type'] ?? 'json';
        $auth = $_POST['auth_header'] ?? null;
        $p_rec = $_POST['param_recipient'] ?? 'to';
        $p_msg = $_POST['param_message'] ?? 'msg';
        $p_snd = $_POST['param_sender'] ?? 'sender_id';

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE sms_providers SET name=?, engine_type=?, endpoint_url=?, balance_endpoint_url=?, http_method=?, payload_type=?, auth_header=?, param_recipient=?, param_message=?, param_sender=?, api_key=?, active_sender_id=? WHERE id=?");
            $stmt->bind_param("ssssssssssssi", $name, $engine, $url, $b_url, $method, $payload, $auth, $p_rec, $p_msg, $p_snd, $api_key, $sender, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO sms_providers (name, engine_type, endpoint_url, balance_endpoint_url, http_method, payload_type, auth_header, param_recipient, param_message, param_sender, api_key, active_sender_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssss", $name, $engine, $url, $b_url, $method, $payload, $auth, $p_rec, $p_msg, $p_snd, $api_key, $sender);
        }
        if($stmt->execute()) {
            $success = "Gateway saved successfully.";
        } else {
            $error = "Database error saving gateway: " . $conn->error;
        }
    }

    // 3. Delete Provider
    if ($_POST['action'] === 'delete_provider') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM sms_providers WHERE id = $id");
        $success = "Gateway deleted forever.";
    }

    // 4. Activate Provider
    if ($_POST['action'] === 'activate_provider') {
        $id = (int)$_POST['id'];
        $conn->query("UPDATE sms_providers SET is_active = 0");
        $conn->query("UPDATE sms_providers SET is_active = 1 WHERE id = $id");
        $success = "Primary routing gateway updated.";
    }

    // 5. Save Template
    if ($_POST['action'] === 'save_template') {
        $id = !empty($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
        $name = $_POST['template_name'];
        $sender_id = !empty($_POST['sender_id']) ? strtoupper(trim($_POST['sender_id'])) : null;
        $body = $_POST['message_body'];

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE sms_templates SET template_name=?, sender_id=?, message_body=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $sender_id, $body, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO sms_templates (template_name, sender_id, message_body) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $sender_id, $body);
        }

        if($stmt->execute()) {
            $success = "Message template saved successfully.";
        } else {
            $error = "Database error saving template: " . $conn->error;
        }
    }

    // 6. Delete Template
    if ($_POST['action'] === 'delete_template') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM sms_templates WHERE id = $id");
        $success = "Message template deleted.";
    }

    // 7. Test SMS Dispatch
    if ($_POST['action'] === 'test_sms') {
        include_once '../../includes/sms_gateway.php';
        $res = send_sms($_POST['test_phone'], $_POST['test_message']);
        if($res['success']) {
            $success = "Test SMS Sent! Response: " . substr(strip_tags($res['response']), 0, 100);
        } else {
            $error = "Test SMS Failed: " . $res['error'];
        }
    }

}

// ---------------------------------------------------------
// FETCH DATA FOR UI
// ---------------------------------------------------------
$providers = [];
$res = $conn->query("SELECT * FROM sms_providers ORDER BY id DESC");
if ($res) while($row = $res->fetch_assoc()) $providers[] = $row;

$templates = [];
$t_res = $conn->query("SELECT * FROM sms_templates ORDER BY template_name ASC");
if ($t_res) while($row = $t_res->fetch_assoc()) $templates[] = $row;

// Fetch Automation Settings
$trigger_payment = getSystemSetting($conn, 'trigger_payment', '0');
$tpl_payment = getSystemSetting($conn, 'template_payment', '0');

$trigger_absence = getSystemSetting($conn, 'trigger_absence', '0');
$tpl_absence = getSystemSetting($conn, 'template_absence', '0');

$trigger_grading = getSystemSetting($conn, 'trigger_grading', '0');
$tpl_grading = getSystemSetting($conn, 'template_grading', '0');

$trigger_birthday = getSystemSetting($conn, 'trigger_birthday', '0');
$tpl_birthday = getSystemSetting($conn, 'template_birthday', '0');

$trigger_overdue = getSystemSetting($conn, 'trigger_overdue', '0');
$tpl_overdue = getSystemSetting($conn, 'template_overdue', '0');

$trigger_upcoming = getSystemSetting($conn, 'trigger_upcoming', '0');
$tpl_upcoming = getSystemSetting($conn, 'template_upcoming', '0');

$trigger_low_balance = getSystemSetting($conn, 'trigger_low_balance', '0');
$tpl_low_balance = getSystemSetting($conn, 'template_low_balance', '0');

$trigger_welcome = getSystemSetting($conn, 'trigger_welcome', '0');
$tpl_welcome = getSystemSetting($conn, 'template_welcome', '0');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Config Hub | Salba Montessori</title>
    <!-- Clean, Professional Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'], },
                    colors: { primary: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 900: '#1e3a8a', } }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased">
    <?php include '../../includes/sidebar.php'; ?>

    <div class="lg:ml-72 flex flex-col min-h-screen">
        
        <header class="bg-white border-b border-slate-200 px-6 md:px-10 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-2 mb-1">
                <a href="dashboard.php" class="text-slate-400 hover:text-indigo-600 transition-colors text-sm font-bold">
                    <i class="fas fa-arrow-left mr-1"></i> Comm Hub
                </a>
                <span class="text-slate-300">/</span>
                <span class="text-indigo-600 text-xs font-bold uppercase tracking-widest bg-indigo-50 px-2 py-0.5 rounded">Configuration</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight"><i class="fas fa-sliders text-indigo-500 mr-2"></i>Hybrid Integrations Hub</h1>
            <p class="text-sm text-slate-500 font-medium mt-1">Manage native advanced gateways or create your own custom blueprints.</p>
        </header>

        <main class="flex-1 px-6 md:px-10 py-8 w-full max-w-7xl mx-auto">
            
            <?php if ($success): ?>
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-lg flex items-center gap-3">
                <i class="fas fa-check-circle text-lg"></i>
                <span class="font-bold text-sm"><?= htmlspecialchars($success) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="mb-6 bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-lg flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-lg"></i>
                <span class="font-bold text-sm"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <div class="flex flex-wrap gap-2 mb-8 border-b border-slate-200 pb-2">
                <button onclick="switchTab('gateways')" id="tab-btn-gateways" class="tab-btn active bg-indigo-600 text-white px-5 py-2.5 rounded-lg text-sm font-bold shadow-sm transition-all"><i class="fas fa-plug mr-2"></i>Gateways & Routing</button>
                <button onclick="switchTab('templates')" id="tab-btn-templates" class="tab-btn bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 px-5 py-2.5 rounded-lg text-sm font-bold transition-all"><i class="fas fa-layer-group mr-2"></i>Message Templates</button>
                <button onclick="switchTab('automations')" id="tab-btn-automations" class="tab-btn bg-white text-slate-600 hover:bg-slate-100 border border-slate-200 px-5 py-2.5 rounded-lg text-sm font-bold transition-all"><i class="fas fa-bolt mr-2"></i>Automation Triggers</button>
            </div>

            <!-- TAB A: GATEWAYS -->
            <div id="tab-gateways" class="tab-content block space-y-8">
                
                <div class="flex items-center justify-between mb-6">
                    <p class="text-sm text-slate-500 font-medium">Configure SMS Providers to send your messages.</p>
                    <div class="flex gap-2">
                        <a href="?reset_tables=1" onclick="return confirm('Warning: This will drop and reset the provider table. Continue?');" class="bg-rose-50 hover:bg-rose-100 text-rose-600 text-sm font-bold py-2.5 px-4 rounded-lg shadow-sm transition-colors border border-rose-200"><i class="fas fa-exclamation-triangle mr-1"></i> Reset Tables</a>
                        <button onclick="openProviderModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm transition-colors">
                            <i class="fas fa-plus mr-2"></i> Add New Gateway
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if (empty($providers)): ?>
                        <div class="col-span-full bg-white rounded-xl border border-slate-200 border-dashed p-10 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 text-2xl mx-auto mb-4"><i class="fas fa-satellite-dish"></i></div>
                            <h3 class="text-base font-bold text-slate-700 mb-1">No Gateways Configured</h3>
                            <p class="text-sm text-slate-500 font-medium">Add a Native or Custom gateway to start sending SMS.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($providers as $p): ?>
                        <div class="bg-white rounded-2xl border <?= $p['is_active'] ? 'border-indigo-500 shadow-lg ring-1 ring-indigo-500' : 'border-slate-200 shadow-sm' ?> overflow-hidden relative flex flex-col">
                            <?php if($p['is_active']): ?>
                                <div class="absolute top-0 right-0 bg-indigo-500 text-white text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded-bl-lg shadow-sm"><i class="fas fa-broadcast-tower mr-1"></i> ACTIVE ROUTE</div>
                            <?php endif; ?>
                            
                            <div class="p-6 flex items-start gap-4">
                                <div class="w-14 h-14 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center text-2xl shadow-sm shrink-0">
                                    <?php if(($p['engine_type'] ?? '') === 'mnotify'): ?><i class="fas fa-satellite-dish text-indigo-600"></i>
                                    <?php elseif(($p['engine_type'] ?? '') === 'bulksmsgh'): ?><i class="fas fa-signal text-sky-600"></i>
                                    <?php else: ?><i class="fas fa-code text-slate-500"></i><?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-black text-slate-800 leading-tight"><?= htmlspecialchars($p['name']) ?></h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200">
                                            <?= ($p['engine_type'] ?? '') === 'custom' ? 'Custom Blueprint' : 'Native Engine' ?>
                                        </span>
                                    </div>
                                    <?php if(!empty($p['active_sender_id'])): ?>
                                        <p class="text-xs text-slate-500 font-medium mt-2"><i class="fas fa-id-card mr-1"></i> Sender: <span class="font-bold text-slate-700"><?= htmlspecialchars($p['active_sender_id']) ?></span></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="p-6 pt-0 mt-auto">
                                <!-- Specific Native Tools -->
                                <?php if(($p['engine_type'] ?? '') === 'mnotify'): ?>
                                    <div class="bg-indigo-50/50 rounded-lg p-3 border border-indigo-100 mb-4 text-xs">
                                        <button onclick="mNotifyCheckBalance(<?= $p['id'] ?>)" class="text-indigo-700 font-bold hover:underline mr-4"><i class="fas fa-wallet mr-1"></i> Balance</button>
                                        <button onclick="mNotifyRegisterSender(<?= $p['id'] ?>)" class="text-indigo-700 font-bold hover:underline mr-4"><i class="fas fa-plus mr-1"></i> Reg Sender</button>
                                        <button onclick="mNotifyCheckSenderStatus(<?= $p['id'] ?>)" class="text-indigo-700 font-bold hover:underline"><i class="fas fa-search mr-1"></i> Check Status</button>
                                    </div>
                                <?php elseif(($p['engine_type'] ?? '') === 'bulksmsgh'): ?>
                                    <div class="bg-sky-50/50 rounded-lg p-3 border border-sky-100 mb-4 text-xs">
                                        <button onclick="bulksmsCheckBalance(<?= $p['id'] ?>)" class="text-sky-700 font-bold hover:underline"><i class="fas fa-wallet mr-1"></i> Check Balance</button>
                                    </div>
                                <?php endif; ?>

                                <!-- Generic Actions -->
                                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                                    <?php if(!$p['is_active']): ?>
                                        <button onclick="activateProvider(<?= $p['id'] ?>)" class="text-xs font-bold text-indigo-600 hover:text-indigo-800"><i class="fas fa-power-off mr-1"></i> Make Active</button>
                                    <?php else: ?>
                                        <span class="text-xs font-bold text-emerald-500"><i class="fas fa-check mr-1"></i> Routing Live</span>
                                    <?php endif; ?>
                                    
                                    <div class="flex items-center gap-3">
                                        <?php if(($p['engine_type'] ?? '') === 'custom' && !empty($p['balance_endpoint_url'])): ?>
                                            <button onclick="checkCustomBalance(<?= $p['id'] ?>)" class="text-xs font-bold text-slate-500 hover:text-slate-800"><i class="fas fa-wallet mr-1"></i> Balance</button>
                                        <?php endif; ?>
                                        <button onclick="openEditProviderModal(<?= htmlspecialchars(json_encode($p)) ?>)" class="text-xs font-bold text-slate-400 hover:text-indigo-600"><i class="fas fa-edit"></i></button>
                                        <button onclick="deleteProvider(<?= $p['id'] ?>)" class="text-xs font-bold text-rose-400 hover:text-rose-600"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB B: TEMPLATES -->
            <div id="tab-templates" class="tab-content hidden space-y-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-800">Message Templates</h2>
                        <p class="text-xs font-medium text-slate-500 mt-1">Pre-write messages with dynamic variables for quick sending.</p>
                    </div>
                    <button onclick="openTemplateModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm transition-colors">
                        <i class="fas fa-plus mr-2"></i> New Template
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($templates)): ?>
                        <div class="col-span-full bg-white rounded-xl border border-slate-200 border-dashed p-10 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 text-2xl mx-auto mb-4"><i class="fas fa-layer-group"></i></div>
                            <h3 class="text-base font-bold text-slate-700 mb-1">No Templates Found</h3>
                            <p class="text-sm text-slate-500 font-medium">Create your first template (e.g. Fee Reminder).</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($templates as $t): ?>
                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative flex flex-col">
                            <div class="flex items-start justify-between mb-3">
                                <h3 class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($t['template_name']) ?></h3>
                                <?php if(!empty($t['sender_id'])): ?>
                                    <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded shadow-sm"><i class="fas fa-id-card mr-1"></i> <?= htmlspecialchars($t['sender_id']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-slate-600 font-medium bg-slate-50 p-3 rounded-lg border border-slate-100 flex-1 whitespace-pre-wrap"><?= htmlspecialchars($t['message_body']) ?></p>
                            <div class="pt-4 mt-4 border-t border-slate-100 flex justify-end gap-4">
                                <button onclick="openEditTemplateModal(<?= htmlspecialchars(json_encode($t)) ?>)" class="text-xs font-bold text-slate-500 hover:text-indigo-600"><i class="fas fa-edit mr-1"></i> Edit</button>
                                <button onclick="deleteTemplate(<?= $t['id'] ?>)" class="text-xs font-bold text-rose-400 hover:text-rose-600"><i class="fas fa-trash mr-1"></i> Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB C: AUTOMATIONS -->
            <div id="tab-automations" class="tab-content hidden space-y-8">
                <div><h2 class="text-lg font-black text-slate-800">Event Triggers</h2><p class="text-xs font-medium text-slate-500 mt-1">Map your templates to automatic system events.</p></div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_automations">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden divide-y divide-slate-100">
                        <!-- Payment -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-file-invoice-dollar"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Payment Receipts</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires automatically when the accountant saves a fee payment.</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_payment" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_payment == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_payment" value="1" class="sr-only peer" <?= $trigger_payment === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Absence -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-user-clock"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Absence Alerts</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires when a teacher marks a student as absent.</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_absence" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_absence == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_absence" value="1" class="sr-only peer" <?= $trigger_absence === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Grading -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-graduation-cap"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Grade Publications</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires in bulk when terminal results are published.</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_grading" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_grading == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_grading" value="1" class="sr-only peer" <?= $trigger_grading === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Birthdays -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-cake-candles"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Birthday Wishes</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires automatically in the morning for students born today. (CRON)</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_birthday" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_birthday == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_birthday" value="1" class="sr-only peer" <?= $trigger_birthday === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Overdue Fees -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-exclamation-triangle"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Overdue Fee Reminders</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires daily for parents with overdue invoice balances. (CRON)</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_overdue" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_overdue == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_overdue" value="1" class="sr-only peer" <?= $trigger_overdue === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Low Balance -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-battery-quarter"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">Low SMS Balance</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires daily to admin if SMS units drop below 50. (CRON)</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_low_balance" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_low_balance == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_low_balance" value="1" class="sr-only peer" <?= $trigger_low_balance === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                </label>
                            </div>
                        </div>
                        <!-- Welcome Message -->
                        <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 hover:bg-slate-50 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="w-12 h-12 rounded-full bg-pink-50 text-pink-500 flex items-center justify-center text-xl shrink-0"><i class="fas fa-handshake"></i></div>
                                <div><h3 class="text-sm font-bold text-slate-800">New Student Welcome</h3><p class="text-xs text-slate-500 font-medium mt-1">Fires when a new student is admitted to the school.</p></div>
                            </div>
                            <div class="flex items-center gap-6">
                                <select name="template_welcome" class="bg-white border border-slate-200 text-slate-700 text-xs rounded-lg p-2 outline-none font-bold min-w-[200px]"><option value="0">-- No Template Selected --</option><?php foreach($templates as $t): ?><option value="<?= $t['id'] ?>" <?= $tpl_welcome == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['template_name']) ?></option><?php endforeach; ?></select>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="trigger_welcome" value="1" class="sr-only peer" <?= $trigger_welcome === '1' ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-pink-500"></div>
                                </label>
                            </div>
                        </div>
                        <div class="p-5 bg-slate-50 border-t border-slate-200 flex justify-end">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors">
                                Save Automation Mapping
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
        </main>
    </div>

    <!-- MAIN GATEWAY MODAL (Unified) -->
    <div id="modal-provider" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest" id="modal-provider-title"><i class="fas fa-plug text-indigo-500 mr-2"></i> Configure Gateway</h3>
                <button onclick="document.getElementById('modal-provider').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form id="form-provider" method="POST" action="">
                    <input type="hidden" name="action" value="save_provider">
                    <input type="hidden" name="id" id="provider_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Provider Name *</label>
                            <input type="text" name="name" id="provider_name" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 outline-none font-bold" placeholder="E.g. mNotify, Twilio, BulkSMS" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Engine Architecture *</label>
                            <select name="engine_type" id="provider_engine" onchange="toggleEngineFields()" class="w-full bg-indigo-50 border border-indigo-200 text-indigo-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 outline-none font-bold" required>
                                <option value="custom">Generic Custom Blueprint (Manual Mapping)</option>
                                <option value="mnotify">Native: mNotify Engine</option>
                                <option value="bulksmsgh">Native: BulkSMSGH Engine</option>
                            </select>
                        </div>
                    </div>

                    <!-- Native Fields (API Key & Sender ID) -->
                    <div id="section-native" class="mb-8 p-5 rounded-xl border border-slate-200 bg-slate-50/50 hidden">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-bolt text-indigo-500"></i>
                            <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Native API Credentials</h4>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">API Key *</label>
                                <input type="password" name="api_key" id="provider_api_key" class="w-full bg-white border border-slate-200 text-slate-800 text-sm rounded-lg p-2.5 outline-none font-mono" placeholder="Paste your API Key">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Active Sender ID</label>
                                <input type="text" name="active_sender_id" id="provider_sender_id" class="w-full bg-white border border-slate-200 text-slate-800 text-sm rounded-lg p-2.5 outline-none font-bold uppercase" placeholder="E.g. SALBA" maxlength="11">
                            </div>
                        </div>
                    </div>

                    <!-- Custom Blueprint Fields -->
                    <div id="section-custom" class="space-y-8">
                        <div class="bg-slate-50/50 p-5 rounded-xl border border-slate-200">
                            <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fas fa-code text-slate-400"></i> API Endpoint Setup</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="col-span-full">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Endpoint URL *</label>
                                    <input type="url" name="endpoint_url" id="provider_endpoint" class="w-full bg-white border border-slate-200 text-slate-800 text-sm rounded-lg p-2.5 outline-none font-mono" placeholder="https://api.gateway.com/sms">
                                </div>
                                <div class="col-span-full">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Balance Check URL (Optional)</label>
                                    <input type="url" name="balance_endpoint_url" id="provider_balance_url" class="w-full bg-white border border-slate-200 text-slate-800 text-sm rounded-lg p-2.5 outline-none font-mono">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">HTTP Method</label>
                                    <select name="http_method" id="provider_method" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-bold">
                                        <option value="POST">POST (Recommended)</option>
                                        <option value="GET">GET</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Payload Type</label>
                                    <select name="payload_type" id="provider_payload" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-bold">
                                        <option value="json">JSON Application/json</option>
                                        <option value="form">Form x-www-form-urlencoded</option>
                                        <option value="query">URL Query Params</option>
                                    </select>
                                </div>
                                <div class="col-span-full">
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Custom Auth Header (Optional)</label>
                                    <input type="text" name="auth_header" id="provider_auth" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-mono" placeholder="Authorization: Bearer YOUR_TOKEN">
                                </div>
                            </div>
                        </div>

                        <div class="bg-indigo-50/30 p-5 rounded-xl border border-indigo-100">
                            <h4 class="text-xs font-black text-indigo-700 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fas fa-sitemap"></i> Data Field Mapping</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Recipient Field</label>
                                    <input type="text" name="param_recipient" id="provider_param_rec" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-mono text-sm" placeholder="to">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Message Field</label>
                                    <input type="text" name="param_message" id="provider_param_msg" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-mono text-sm" placeholder="msg">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Sender ID Field</label>
                                    <input type="text" name="param_sender" id="provider_param_snd" class="w-full bg-white border border-slate-200 rounded-lg p-2.5 font-mono text-sm" placeholder="sender_id">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 pt-6 mt-6 border-t border-slate-100">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors" id="btn-save-provider">
                            Save Gateway
                        </button>
                        <button type="button" onclick="document.getElementById('modal-provider').classList.add('hidden')" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold py-2.5 px-6 rounded-lg transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Template Modal -->
    <div id="modal-template" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest flex items-center gap-2" id="modal-template-title">
                    <div class="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center"><i class="fas fa-layer-group"></i></div>
                    New Template
                </h3>
                <button onclick="document.getElementById('modal-template').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <form id="form-template" method="POST" action="">
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_id" id="template_id" value="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Template Name *</label>
                            <input type="text" name="template_name" id="tpl_name_input" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 outline-none font-medium" placeholder="E.g. Payment Receipt" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Custom Sender ID <span class="text-slate-400 normal-case">(Optional)</span></label>
                            <input type="text" name="sender_id" id="tpl_sender_input" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-2.5 outline-none font-bold uppercase" placeholder="E.g. SALBA-FIN" maxlength="11">
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Message Body *</label>
                        <textarea name="message_body" id="tpl_body_input" rows="4" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3 outline-none font-medium" placeholder="Hi {parent_name}, we received a payment of GHS {amount} for {student_name}. New Balance: GHS {balance}" required></textarea>
                        <div class="mt-2 text-[10px] text-slate-400 font-bold bg-slate-50 p-2 rounded border border-slate-100">
                            Available Variables: {student_name}, {parent_name}, {amount}, {balance}, {term}
                        </div>
                    </div>
                    <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors" id="btn-save-tpl">
                            Save Template
                        </button>
                        <button type="button" onclick="document.getElementById('modal-template').classList.add('hidden')" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold py-2.5 px-6 rounded-lg transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Beautiful Response Modal -->
    <div id="modal-response" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col transform transition-all scale-100">
            <div class="p-6 text-center pt-8">
                <div class="w-20 h-20 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center text-3xl mx-auto mb-4 border-4 border-white shadow-sm" id="modal-response-icon">
                    <i class="fas fa-satellite-dish"></i>
                </div>
                <h3 class="font-black text-slate-800 text-xl mb-2 tracking-tight" id="modal-response-title">API Response</h3>
                <div class="text-base text-slate-700 font-medium bg-slate-50 p-6 rounded-xl border border-slate-200 whitespace-pre-wrap max-h-64 overflow-y-auto text-center shadow-inner mt-4" id="modal-response-body">
                    Loading response data...
                </div>
            </div>
            <div class="px-6 py-5 bg-slate-50 border-t border-slate-100 flex justify-center">
                <button onclick="document.getElementById('modal-response').classList.add('hidden')" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2.5 px-8 rounded-lg shadow-sm transition-colors w-full">Awesome, close this!</button>
            </div>
        </div>
    </div>

    <!-- Beautiful Purpose Modal -->
    <div id="modal-purpose" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest"><i class="fas fa-keyboard text-indigo-500 mr-2"></i> Register Sender ID</h3>
                <button onclick="document.getElementById('modal-purpose').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-6">
                <p class="text-xs text-slate-500 font-medium mb-4">Please provide a brief explanation of what you will use this Sender ID for. The telecommunication networks require this for approval.</p>
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Purpose of Sender ID *</label>
                <input type="text" id="purpose-input" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block p-3 outline-none font-medium mb-6" value="For School Alerts">
                
                <input type="hidden" id="purpose-provider-id" value="">
                
                <div class="flex gap-3">
                    <button onclick="submitPurpose()" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors flex-1">Submit Registration</button>
                    <button onclick="document.getElementById('modal-purpose').classList.add('hidden')" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold py-2.5 px-6 rounded-lg transition-colors flex-1">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('bg-indigo-600', 'text-white');
                el.classList.add('bg-white', 'text-slate-600');
            });
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            let btn = document.getElementById('tab-btn-' + tabId);
            btn.classList.remove('bg-white', 'text-slate-600');
            btn.classList.add('bg-indigo-600', 'text-white');
        }

        function toggleEngineFields() {
            let engine = document.getElementById('provider_engine').value;
            let secCustom = document.getElementById('section-custom');
            let secNative = document.getElementById('section-native');
            
            if (engine === 'custom') {
                secCustom.classList.remove('hidden');
                secNative.classList.add('hidden');
            } else {
                secCustom.classList.add('hidden');
                secNative.classList.remove('hidden');
            }
        }

        function openTemplateModal() {
            document.getElementById('form-template').reset();
            document.getElementById('template_id').value = '';
            document.getElementById('modal-template-title').innerHTML = '<div class="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center"><i class="fas fa-layer-group"></i></div> New Template';
            document.getElementById('modal-template').classList.remove('hidden');
        }

        function openEditTemplateModal(t) {
            document.getElementById('template_id').value = t.id;
            document.getElementById('tpl_name_input').value = t.template_name;
            document.getElementById('tpl_sender_input').value = t.sender_id || '';
            document.getElementById('tpl_body_input').value = t.message_body;
            document.getElementById('modal-template-title').innerHTML = '<div class="w-8 h-8 rounded bg-indigo-100 text-indigo-600 flex items-center justify-center"><i class="fas fa-edit"></i></div> Edit Template';
            document.getElementById('modal-template').classList.remove('hidden');
        }

        function openProviderModal() {
            document.getElementById('form-provider').reset();
            document.getElementById('provider_id').value = '';
            document.getElementById('modal-provider-title').innerHTML = '<i class="fas fa-plus text-indigo-500 mr-2"></i> Add New Gateway';
            toggleEngineFields();
            document.getElementById('modal-provider').classList.remove('hidden');
        }

        function openEditProviderModal(p) {
            document.getElementById('provider_id').value = p.id;
            document.getElementById('provider_name').value = p.name;
            document.getElementById('provider_engine').value = p.engine_type;
            
            // Native
            document.getElementById('provider_api_key').value = p.api_key || '';
            document.getElementById('provider_sender_id').value = p.active_sender_id || '';

            // Custom
            document.getElementById('provider_endpoint').value = p.endpoint_url || '';
            document.getElementById('provider_balance_url').value = p.balance_endpoint_url || '';
            document.getElementById('provider_method').value = p.http_method || 'POST';
            document.getElementById('provider_payload').value = p.payload_type || 'json';
            document.getElementById('provider_auth').value = p.auth_header || '';
            document.getElementById('provider_param_rec').value = p.param_recipient || 'to';
            document.getElementById('provider_param_msg').value = p.param_message || 'msg';
            document.getElementById('provider_param_snd').value = p.param_sender || 'sender_id';

            document.getElementById('modal-provider-title').innerHTML = '<i class="fas fa-edit text-indigo-500 mr-2"></i> Edit ' + p.name;
            toggleEngineFields();
            document.getElementById('modal-provider').classList.remove('hidden');
        }

        function saveTemplate() {
            let form = document.getElementById('form-template');
            if(!form.checkValidity()) { form.reportValidity(); return; }
            let btn = document.getElementById('btn-save-tpl');
            let og = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; btn.disabled = true;
            let fd = new FormData(form);
            fetch('ajax_save_template.php', { method: 'POST', body: fd })
                .then(res => res.text())
                .then(text => { 
                    try {
                        let data = JSON.parse(text);
                        if(data.success) location.reload(); 
                        else { alert('Error: ' + JSON.stringify(data)); btn.innerHTML = og; btn.disabled = false; }
                    } catch(e) {
                        alert("Raw Server Error:\n" + text);
                        btn.innerHTML = og; btn.disabled = false;
                    }
                })
                .catch(e => { alert('Network Error'); btn.innerHTML = og; btn.disabled = false; });
        }

        function saveProvider() {
            let form = document.getElementById('form-provider');
            if(!form.checkValidity()) { form.reportValidity(); return; }
            let btn = document.getElementById('btn-save-provider');
            let og = btn.innerHTML;
            btn.innerHTML = 'Saving...'; btn.disabled = true;
            let fd = new FormData(form);
            fetch('ajax_save_provider.php', { method: 'POST', body: fd })
                .then(res => res.text())
                .then(text => {
                    try {
                        let data = JSON.parse(text);
                        if(data.success) location.reload(); else { alert('Error saving gateway in database.'); btn.disabled = false; btn.innerHTML = og; }
                    } catch(e) {
                        alert("Raw Server Error:\n" + text);
                        btn.disabled = false; btn.innerHTML = og;
                    }
                })
                .catch(e => { alert('Network Request Failed'); btn.disabled = false; btn.innerHTML = og; });
        }

        function deleteTemplate(id) {
            if(!confirm('Delete this template?')) return;
            let f = document.createElement('form'); f.method = 'POST'; f.action = '';
            f.innerHTML = `<input type="hidden" name="action" value="delete_template"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }

        function deleteProvider(id) {
            if(!confirm('Delete this gateway forever?')) return;
            let f = document.createElement('form'); f.method = 'POST'; f.action = '';
            f.innerHTML = `<input type="hidden" name="action" value="delete_provider"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }

        function activateProvider(id) {
            let f = document.createElement('form'); f.method = 'POST'; f.action = '';
            f.innerHTML = `<input type="hidden" name="action" value="activate_provider"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }

        // --- Native Tools (Beautiful AJAX) ---
        function showResponseModal(title, message, isSuccess) {
            document.getElementById('modal-response-title').innerText = title;
            document.getElementById('modal-response-body').innerText = message;
            const iconWrap = document.getElementById('modal-response-icon');
            if (isSuccess) {
                iconWrap.className = "w-20 h-20 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-4xl mx-auto mb-4 border-4 border-white shadow-sm";
                iconWrap.innerHTML = '<i class="fas fa-check-circle"></i>';
                document.getElementById('modal-response-body').className = "text-base text-slate-700 font-medium bg-slate-50 p-6 rounded-xl border border-slate-200 whitespace-pre-wrap max-h-64 overflow-y-auto text-center shadow-inner mt-4";
            } else {
                iconWrap.className = "w-20 h-20 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-4xl mx-auto mb-4 border-4 border-white shadow-sm";
                iconWrap.innerHTML = '<i class="fas fa-exclamation-circle"></i>';
                document.getElementById('modal-response-body').className = "text-base text-rose-700 font-bold bg-rose-50 p-6 rounded-xl border border-rose-200 whitespace-pre-wrap max-h-64 overflow-y-auto text-center shadow-inner mt-4";
            }
            document.getElementById('modal-response').classList.remove('hidden');
        }

        async function triggerNativeTool(action, id, purpose = '') {
            showResponseModal("Communicating with Server...", "Please wait...", true);
            let fd = new FormData();
            fd.append('action', action);
            fd.append('provider_id', id);
            if(purpose) fd.append('purpose', purpose);

            try {
                let r = await fetch('ajax_mnotify_tools.php', { method: 'POST', body: fd });
                let data = await r.json();
                showResponseModal(data.title, data.message, data.success);
            } catch(e) {
                showResponseModal("Network Error", "Could not parse API response or connect to endpoint.", false);
            }
        }

        function mNotifyCheckBalance(id) { triggerNativeTool('check_balance', id); }
        function bulksmsCheckBalance(id) { triggerNativeTool('check_balance', id); }
        function mNotifyCheckSenderStatus(id) { triggerNativeTool('check_sender_status', id); }
        function checkCustomBalance(id) { triggerNativeTool('check_balance', id); }
        
        function mNotifyRegisterSender(id) {
            document.getElementById('purpose-provider-id').value = id;
            document.getElementById('purpose-input').value = 'For School Alerts';
            document.getElementById('modal-purpose').classList.remove('hidden');
        }

        function submitPurpose() {
            let id = document.getElementById('purpose-provider-id').value;
            let purpose = document.getElementById('purpose-input').value.trim();
            document.getElementById('modal-purpose').classList.add('hidden');
            if(!purpose) return;
            triggerNativeTool('register_sender', id, purpose);
        }

    </script>
</body>
</html>
