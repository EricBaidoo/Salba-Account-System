<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in()) { header('Location: ../../../login'); exit; }

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_user         = $_SESSION['username'] ?? '';
$user_id              = $_SESSION['user_id'] ?? 0;
$user_role            = $_SESSION['role'] ?? 'staff';

// Ensure external_messages table exists
$conn->query("CREATE TABLE IF NOT EXISTS external_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(100) NOT NULL,
    audience VARCHAR(100) NOT NULL,
    message_type VARCHAR(20) NOT NULL,
    subject VARCHAR(255) DEFAULT '',
    body TEXT NOT NULL,
    recipients_count INT DEFAULT 0,
    status VARCHAR(20) DEFAULT 'Sent',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audience     = $_POST['audience'] ?? '';
    $message_type = $_POST['message_type'] ?? 'sms';
    $subject      = trim($_POST['subject'] ?? '');
    $body         = trim($_POST['body'] ?? '');

    if ($audience && $body) {
        // Simulate counting recipients based on audience
        $recipients_count = ($audience === 'all_parents') ? 150 : 25; // Dummy counts for simulation

        $ins = $conn->prepare("INSERT INTO external_messages (sender, audience, message_type, subject, body, recipients_count) VALUES (?,?,?,?,?,?)");
        $ins->bind_param('sssssi', $current_user, $audience, $message_type, $subject, $body, $recipients_count);
        
        if ($ins->execute()) {
            $type_label = strtoupper($message_type);
            $flash = ['type' => 'success', 'message' => "$type_label successfully broadcasted to $recipients_count recipients."];
        } else {
            $flash = ['type' => 'danger', 'message' => 'Error sending broadcast.'];
        }
        $ins->close();
    } else {
        $flash = ['type' => 'danger', 'message' => 'Audience and message body are required.'];
    }
}

// Fetch history
$history = [];
$h = $conn->query("SELECT * FROM external_messages ORDER BY sent_at DESC LIMIT 50");
if ($h) while ($row = $h->fetch_assoc()) $history[] = $row;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Messaging — <?= htmlspecialchars($school_name) ?></title>
    <!-- Clean, Professional Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
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

    <?php include '../../../includes/sidebar.php'; ?>

    <div class="lg:ml-72 flex flex-col min-h-screen">
        
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 px-6 md:px-10 py-6 sticky top-0 z-30">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <a href="../dashboard.php" class="text-slate-400 hover:text-blue-600 transition-colors text-sm font-bold">
                            <i class="fas fa-arrow-left mr-1"></i> Comm Hub
                        </a>
                        <span class="text-slate-300">/</span>
                        <span class="text-emerald-600 text-xs font-bold uppercase tracking-widest bg-emerald-50 px-2 py-0.5 rounded">External</span>
                    </div>
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight"><i class="fas fa-satellite-dish text-emerald-500 mr-2"></i>External Broadcasts</h1>
                    <p class="text-sm text-slate-500 font-medium mt-1">Send bulk SMS and Email communications to parents and external contacts.</p>
                </div>
                <button onclick="document.getElementById('compose-modal').classList.toggle('hidden')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm transition-colors shadow-emerald-500/30">
                    <i class="fas fa-paper-plane mr-2"></i> New Broadcast
                </button>
            </div>
        </header>

        <main class="flex-1 px-6 md:px-10 py-8 w-full">

            <?php if ($flash): ?>
            <div class="mb-6 bg-<?= $flash['type'] === 'success' ? 'emerald' : 'rose' ?>-50 border border-<?= $flash['type'] === 'success' ? 'emerald' : 'rose' ?>-200 text-<?= $flash['type'] === 'success' ? 'emerald' : 'rose' ?>-700 px-4 py-3 rounded-lg flex items-center gap-3">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> text-lg"></i>
                <span class="font-bold text-sm"><?= htmlspecialchars($flash['message']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Compose Form Container -->
            <div id="compose-modal" class="<?php echo ($flash && $flash['type'] !== 'success') ? '' : 'hidden'; ?> mb-8">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-emerald-50 rounded-bl-full -mr-10 -mt-10 opacity-50"></div>
                    <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest mb-6 relative z-10 flex items-center gap-2">
                        <div class="w-8 h-8 rounded bg-emerald-100 text-emerald-600 flex items-center justify-center"><i class="fas fa-pen"></i></div>
                        Compose Broadcast
                    </h3>
                    
                    <form method="POST" action="" class="relative z-10">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Audience *</label>
                                <select id="audience-select" name="audience" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 outline-none font-medium" required>
                                    <option value="">Select Target Group...</option>
                                    <option value="all_parents">All Parents in School</option>
                                    <option value="nursery_parents">Nursery Parents</option>
                                    <option value="primary_parents">Primary Parents</option>
                                    <option value="jhs_parents">JHS Parents</option>
                                    <option value="debtors">Parents with Outstanding Fees</option>
                                </select>
                                <!-- Preview Container -->
                                <div id="preview-container" class="hidden mt-3 max-h-48 overflow-y-auto bg-slate-50 rounded-lg border border-slate-200 p-2 text-xs">
                                    <div id="preview-loading" class="text-slate-400 font-bold p-2 text-center"><i class="fas fa-spinner fa-spin mr-1"></i> Loading preview...</div>
                                    <div id="preview-list" class="space-y-1 hidden"></div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Method *</label>
                                <select name="message_type" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 outline-none font-medium" required>
                                    <option value="sms">SMS Text Message</option>
                                    <option value="email">Email</option>
                                    <option value="both">Both SMS & Email</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Subject <span class="text-[10px] text-slate-400 normal-case font-medium">(Email Only)</span></label>
                            <input type="text" name="subject" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-2.5 outline-none font-medium" placeholder="E.g. End of Term Notice">
                        </div>

                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Message Body *</label>
                            <textarea name="body" rows="4" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-3 outline-none font-medium" placeholder="Write your message here. For SMS, keep it concise..." required></textarea>
                            <p class="text-[10px] text-slate-400 mt-2 font-bold uppercase tracking-widest"><i class="fas fa-info-circle"></i> Use variables like {parent_name} or {student_name} to personalize.</p>
                        </div>

                        <div class="flex items-center gap-3 border-t border-slate-100 pt-5">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors shadow-emerald-500/30">
                                Send Broadcast Now
                            </button>
                            <button type="button" onclick="document.getElementById('compose-modal').classList.add('hidden')" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold py-2.5 px-6 rounded-lg transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Broadcast History -->
            <h2 class="text-sm font-bold text-slate-900 mb-4"><i class="fas fa-history text-slate-400 mr-1.5"></i> Broadcast History</h2>
            
            <?php if (empty($history)): ?>
            <div class="bg-white rounded-xl border border-slate-200 border-dashed p-10 text-center">
                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-300 text-2xl mx-auto mb-4"><i class="fas fa-paper-plane"></i></div>
                <h3 class="text-base font-bold text-slate-700 mb-1">No Broadcasts Sent</h3>
                <p class="text-sm text-slate-500 font-medium">You haven't sent any SMS or Email campaigns yet.</p>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-xs uppercase tracking-widest text-slate-500 font-bold">
                                <th class="p-4">Date & Time</th>
                                <th class="p-4">Method</th>
                                <th class="p-4">Audience</th>
                                <th class="p-4">Message Preview</th>
                                <th class="p-4 text-center">Recipients</th>
                                <th class="p-4 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach ($history as $h): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 font-bold text-slate-700 whitespace-nowrap">
                                    <?php echo date('M j, Y', strtotime($h['sent_at'])); ?><br>
                                    <span class="text-[10px] text-slate-400"><?php echo date('g:i A', strtotime($h['sent_at'])); ?></span>
                                </td>
                                <td class="p-4">
                                    <?php if($h['message_type'] === 'sms'): ?>
                                        <span class="inline-flex items-center gap-1.5 bg-sky-50 text-sky-600 px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest"><i class="fas fa-comment-sms"></i> SMS</span>
                                    <?php elseif($h['message_type'] === 'email'): ?>
                                        <span class="inline-flex items-center gap-1.5 bg-purple-50 text-purple-600 px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest"><i class="fas fa-envelope"></i> Email</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 bg-indigo-50 text-indigo-600 px-2 py-1 rounded text-[10px] font-black uppercase tracking-widest"><i class="fas fa-layer-group"></i> Both</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 font-bold text-slate-800">
                                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $h['audience']))); ?>
                                </td>
                                <td class="p-4">
                                    <?php if ($h['subject']): ?>
                                        <div class="font-bold text-slate-800 mb-0.5"><?php echo htmlspecialchars($h['subject']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-slate-500 font-medium line-clamp-1 max-w-xs"><?php echo htmlspecialchars($h['body']); ?></div>
                                </td>
                                <td class="p-4 text-center font-black text-slate-700">
                                    <?php echo $h['recipients_count']; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <span class="inline-flex items-center gap-1 text-emerald-600 text-xs font-bold"><i class="fas fa-check-circle"></i> Sent</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <script>
        document.getElementById('audience-select').addEventListener('change', function() {
            const audience = this.value;
            const container = document.getElementById('preview-container');
            const loading = document.getElementById('preview-loading');
            const list = document.getElementById('preview-list');
            
            if (!audience) {
                container.classList.add('hidden');
                return;
            }
            
            container.classList.remove('hidden');
            loading.classList.remove('hidden');
            list.classList.add('hidden');
            list.innerHTML = '';
            
            fetch(`ajax_get_audience.php?audience=${audience}`)
                .then(res => res.text())
                .then(text => {
                    loading.classList.add('hidden');
                    list.classList.remove('hidden');
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch(e) {
                        list.innerHTML = `<div class="text-rose-500 p-2 font-bold break-words text-xs">Raw Error: ${text}</div>`;
                        return;
                    }
                    
                    if (data.error) {
                        list.innerHTML = `<div class="text-rose-500 p-2 font-bold"><i class="fas fa-exclamation-triangle mr-1"></i> Error: ${data.error}</div>`;
                        return;
                    }
                    
                    if (!data.recipients || data.recipients.length === 0) {
                        list.innerHTML = '<div class="text-slate-500 p-2 font-medium">No contacts found for this audience.</div>';
                        return;
                    }
                    
                    let html = `<div class="text-emerald-600 font-bold mb-2 px-1 border-b border-emerald-100 pb-1">Previewing ${data.recipients.length} recipients...</div>`;
                    data.recipients.forEach(r => {
                        html += `
                            <div class="flex items-center justify-between bg-white p-2 rounded border border-slate-100 mb-1 shadow-sm">
                                <div>
                                    <div class="font-bold text-slate-700">${r.student_name}</div>
                                    <div class="text-[10px] text-slate-400 font-medium">${r.class}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold text-slate-600 text-[11px]"><i class="fas fa-phone mr-1 text-slate-300"></i>${r.contact}</div>
                                </div>
                            </div>
                        `;
                    });
                    list.innerHTML = html;
                })
                .catch(err => {
                    loading.classList.add('hidden');
                    list.classList.remove('hidden');
                    list.innerHTML = '<div class="text-rose-500 p-2 font-bold">Error loading preview.</div>';
                });
        });
    </script>
</body>
</html>
