<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';
$updated_by = $_SESSION['username'] ?? 'Admin';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. SMS Settings
    if (isset($_POST['sms_provider'])) {
        setSystemSetting($conn, 'sms_provider', $_POST['sms_provider'], $updated_by);
        setSystemSetting($conn, 'sms_api_key', $_POST['sms_api_key'], $updated_by);
        setSystemSetting($conn, 'sms_sender_id', $_POST['sms_sender_id'], $updated_by);
    }
    
    // 2. Email Settings
    if (isset($_POST['smtp_host'])) {
        setSystemSetting($conn, 'smtp_host', $_POST['smtp_host'], $updated_by);
        setSystemSetting($conn, 'smtp_port', $_POST['smtp_port'], $updated_by);
        setSystemSetting($conn, 'smtp_user', $_POST['smtp_user'], $updated_by);
        if(!empty($_POST['smtp_pass'])) {
            setSystemSetting($conn, 'smtp_pass', $_POST['smtp_pass'], $updated_by);
        }
    }
    
    // 3. Notification Triggers
    $triggers = ['notify_on_payment', 'notify_on_attendance', 'notify_on_grading'];
    foreach($triggers as $trig) {
        $val = isset($_POST[$trig]) ? '1' : '0';
        setSystemSetting($conn, $trig, $val, $updated_by);
    }

    $success = "Communication nodes successfully re-calibrated.";
}

// Fetch current values
$sms_provider = getSystemSetting($conn, 'sms_provider', 'bulksms');
$sms_api_key = getSystemSetting($conn, 'sms_api_key', '');
$sms_sender_id = getSystemSetting($conn, 'sms_sender_id', 'SALBA');

$smtp_host = getSystemSetting($conn, 'smtp_host', '');
$smtp_port = getSystemSetting($conn, 'smtp_port', '587');
$smtp_user = getSystemSetting($conn, 'smtp_user', '');

$notify_payment = getSystemSetting($conn, 'notify_on_payment', '0');
$notify_attendance = getSystemSetting($conn, 'notify_on_attendance', '0');
$notify_grading = getSystemSetting($conn, 'notify_on_grading', '0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Settings | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#F8FAFC]">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen p-8">
        <header class="app-header !border-b-4 !border-b-indigo-500">
            <div class="flex items-center gap-2 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-1 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Communication Hub
                </a>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-6">
                <div>
                    <div class="app-title-pill !bg-indigo-500 !text-white !px-3 !py-1 !text-[10px] !font-black !uppercase !tracking-widest !mb-2 !inline-flex">
                        <i class="fas fa-tower-broadcast mr-2"></i> Signal Modulation
                    </div>
                    <h1 class="app-title uppercase tracking-tighter text-indigo-900">Communication Node Settings</h1>
                    <p class="app-subtitle">Provision SMS gateways, SMTP servers, and notification triggers</p>
                </div>
            </div>
        </header>

        <div class="p-8 max-w-5xl">
            <?php if ($success): ?>
                <div class="bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-3 rounded-xl flex items-center gap-3 mb-8 shadow-sm">
                    <i class="fas fa-satellite-dish text-indigo-500"></i>
                    <span class="font-bold"><?= $success ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Section: SMS Gateway -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-comment-sms text-indigo-500"></i> SMS Gateway (Bulk SMS)
                            </h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Service Provider</label>
                                <select name="sms_provider" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 appearance-none">
                                    <option value="bulksms" <?= $sms_provider === 'bulksms' ? 'selected' : '' ?>>BulkSMS.com</option>
                                    <option value="twilio" <?= $sms_provider === 'twilio' ? 'selected' : '' ?>>Twilio Node</option>
                                    <option value="mnotify" <?= $sms_provider === 'mnotify' ? 'selected' : '' ?>>mNotify (Ghana)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">API Key / Auth Token</label>
                                <input type="password" name="sms_api_key" value="<?= htmlspecialchars($sms_api_key) ?>" 
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Sender ID / Mask</label>
                                <input type="text" name="sms_sender_id" value="<?= htmlspecialchars($sms_sender_id) ?>" maxlength="11"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700 uppercase">
                                <p class="text-[9px] text-slate-400 mt-2 italic">Max 11 characters. Example: SALBA</p>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Email Node -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                            <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-envelope-open-text text-indigo-500"></i> SMTP Infrastructure
                            </h2>
                        </div>
                        <div class="p-6 space-y-5">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Host Address</label>
                                <input type="text" name="smtp_host" value="<?= htmlspecialchars($smtp_host) ?>" placeholder="smtp.gmail.com"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Port</label>
                                    <input type="text" name="smtp_port" value="<?= htmlspecialchars($smtp_port) ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Auth User</label>
                                    <input type="text" name="smtp_user" value="<?= htmlspecialchars($smtp_user) ?>" 
                                           class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Auth Password</label>
                                <input type="password" name="smtp_pass" placeholder="••••••••••••"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none font-bold text-slate-700">
                                <p class="text-[9px] text-slate-400 mt-2 italic">Leave blank to keep current password.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Notification Triggers -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-50 bg-slate-50/50">
                        <h2 class="text-xs font-black text-slate-900 uppercase tracking-widest flex items-center gap-2">
                            <i class="fas fa-bolt text-indigo-500"></i> Event Triggers
                        </h2>
                    </div>
                    <div class="p-10">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                            <label class="flex items-start gap-4 cursor-pointer group">
                                <input type="checkbox" name="notify_on_payment" value="1" <?= $notify_payment === '1' ? 'checked' : '' ?>
                                       class="mt-1 w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="block text-xs font-black text-slate-700 uppercase tracking-widest">Payment Receipts</span>
                                    <span class="block text-[10px] text-slate-400 mt-1 italic font-medium">Broadcast SMS/Email on fee payment.</span>
                                </div>
                            </label>
                            
                            <label class="flex items-start gap-4 cursor-pointer group">
                                <input type="checkbox" name="notify_on_attendance" value="1" <?= $notify_attendance === '1' ? 'checked' : '' ?>
                                       class="mt-1 w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="block text-xs font-black text-slate-700 uppercase tracking-widest">Absence Alerts</span>
                                    <span class="block text-[10px] text-slate-400 mt-1 italic font-medium">Notify parents when student is absent.</span>
                                </div>
                            </label>

                            <label class="flex items-start gap-4 cursor-pointer group">
                                <input type="checkbox" name="notify_on_grading" value="1" <?= $notify_grading === '1' ? 'checked' : '' ?>
                                       class="mt-1 w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <span class="block text-xs font-black text-slate-700 uppercase tracking-widest">Grade Publication</span>
                                    <span class="block text-[10px] text-slate-400 mt-1 italic font-medium">Alert when terminal results are ready.</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-indigo-950 rounded-2xl p-8 flex items-center justify-between shadow-2xl">
                    <div class="flex items-center gap-6">
                        <div class="w-14 h-14 bg-indigo-500 rounded-2xl flex items-center justify-center text-white text-2xl shadow-lg shadow-indigo-500/20">
                            <i class="fas fa-signal"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-xs font-black uppercase tracking-[0.3em]">Module-Wide Signal Registry</h3>
                            <p class="text-indigo-300/60 text-[10px] mt-1 font-medium italic leading-relaxed">Changes synchronize across all departmental communication sub-systems.</p>
                        </div>
                    </div>
                    <button type="submit" class="bg-indigo-500 hover:bg-indigo-400 text-white font-black uppercase tracking-widest px-12 py-5 rounded-xl shadow-xl shadow-indigo-900/40 transition-all active:scale-95 leading-none h-fit border border-indigo-400/30">
                        Calibrate Communications
                    </button>
                </div>
            </form>
        </div>

        <footer class="mt-24 py-16 text-left border-t border-slate-200">
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">Communication Hub &middot; Salba Institutional Oversight &middot; v9.4.0</p>
        </footer>
    </main>
</body>
</html>
