<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in()) { header('Location: ../../../login'); exit; }

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_user         = $_SESSION['username'] ?? '';
$user_id              = $_SESSION['user_id'] ?? 0;
$user_role            = $_SESSION['role'] ?? 'staff';

// Ensure messages table exists
$conn->query("CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender VARCHAR(100) NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) DEFAULT '',
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Handle send POST
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = trim($_POST['recipient'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $body      = trim($_POST['body'] ?? '');

    if ($recipient && $body) {
        // Check recipient exists
        $chk = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
        $chk->bind_param('s', $recipient);
        $chk->execute();
        $chk->store_result();
        $exists = $chk->num_rows > 0;
        $chk->close();

        if ($exists) {
            $ins = $conn->prepare("INSERT INTO messages (sender, recipient, subject, body) VALUES (?,?,?,?)");
            $ins->bind_param('ssss', $current_user, $recipient, $subject, $body);
            if ($ins->execute()) {
                $flash = ['type' => 'success', 'message' => "Message sent to '{$recipient}' successfully."];
            } else {
                $flash = ['type' => 'danger', 'message' => 'Error sending message.'];
            }
            $ins->close();
        } else {
            $flash = ['type' => 'danger', 'message' => "User '{$recipient}' does not exist."];
        }
    } else {
        $flash = ['type' => 'danger', 'message' => 'Recipient and message body are required.'];
    }
}

// Inbox (messages where current user is recipient)
$inbox = [];
$r = $conn->prepare("SELECT * FROM messages WHERE recipient=? ORDER BY sent_at DESC LIMIT 50");
if ($r) { $r->bind_param('s', $current_user); $r->execute(); $inbox = $r->get_result()->fetch_all(MYSQLI_ASSOC); $r->close(); }

// Sent (messages sent by current user)
$sent = [];
$s = $conn->prepare("SELECT * FROM messages WHERE sender=? ORDER BY sent_at DESC LIMIT 50");
if ($s) { $s->bind_param('s', $current_user); $s->execute(); $sent = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close(); }

// Mark unread as read
$conn->prepare("UPDATE messages SET is_read=1 WHERE recipient=? AND is_read=0")?->execute();

// Other users list for the recipient dropdown
$users = [];
$esc  = $conn->real_escape_string($current_user);
$ur   = $conn->query("SELECT username FROM users WHERE username != '{$esc}' ORDER BY username");
if (!$ur) $ur = $conn->query("SELECT username FROM users ORDER BY username");
if ($ur) while ($row = $ur->fetch_assoc()) $users[] = $row['username'];

$active_tab = $_GET['tab'] ?? 'inbox';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <!-- Header -->
        <div class="clean-page-header">
            <div class="mb-1">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Communication
                </a>
            </div>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-message mr-2 text-blue-500"></i>Messages</h1>
                    <p class="clean-page-subtitle">Internal messaging between staff members</p>
                </div>
                <button onclick="document.getElementById('compose-modal').classList.toggle('hidden')"
                        class="rounded-clean-primary">
                    <i class="fas fa-pen"></i> Compose
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="clean-alert clean-alert-<?php echo $flash['type']; ?> mx-8 mt-4">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <span><?php echo htmlspecialchars($flash['message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- Compose Modal -->
        <div id="compose-modal" class="<?php echo ($flash && $flash['type'] !== 'success') ? '' : 'hidden'; ?> mx-8 mt-4">
            <div class="bg-white rounded-xl border border-blue-200 shadow-sm p-6">
                <h3 class="font-bold text-blue-600 text-sm uppercase tracking-wide mb-4">
                    <i class="fas fa-pen mr-2"></i>New Message
                </h3>
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="clean-form-group">
                            <label class="clean-label" for="recipient">To *</label>
                            <select class="clean-select" id="recipient" name="recipient" required>
                                <option value="">Select recipient...</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>"><?php echo htmlspecialchars($u); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="clean-form-group">
                            <label class="clean-label" for="subject">Subject</label>
                            <input type="text" class="clean-input" id="subject" name="subject" placeholder="Message subject...">
                        </div>
                    </div>
                    <div class="clean-form-group">
                        <label class="clean-label" for="body">Message *</label>
                        <textarea class="clean-textarea" id="body" name="body" rows="4"
                                  placeholder="Write your message..." required></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="rounded-clean-primary">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
                        <button type="button"
                                onclick="document.getElementById('compose-modal').classList.add('hidden')"
                                class="rounded-clean-outline">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mx-8 mt-4">
            <div class="flex border-b border-gray-200 mb-4">
                <a href="?tab=inbox"
                   class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors <?php echo $active_tab === 'inbox' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'; ?>">
                    <i class="fas fa-inbox mr-2"></i>Inbox
                    <span class="ml-1 bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded-full"><?php echo count($inbox); ?></span>
                </a>
                <a href="?tab=sent"
                   class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors <?php echo $active_tab === 'sent' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-400 hover:text-gray-600'; ?>">
                    <i class="fas fa-paper-plane mr-2"></i>Sent
                </a>
            </div>

            <?php $list = $active_tab === 'sent' ? $sent : $inbox; ?>
            <?php if (empty($list)): ?>
            <div class="clean-empty-state">
                <div class="clean-empty-icon"><i class="fas fa-envelope-open"></i></div>
                <h4 class="clean-empty-title"><?php echo $active_tab === 'inbox' ? 'Your inbox is empty' : 'No sent messages'; ?></h4>
                <p class="clean-empty-text">Messages will appear here once sent or received.</p>
            </div>
            <?php else: ?>
            <div class="space-y-2 pb-8">
                <?php foreach ($list as $msg): ?>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-start gap-4
                            <?php echo ($active_tab === 'inbox' && !$msg['is_read']) ? 'border-l-4 border-l-blue-400' : ''; ?>">
                    <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-sm font-bold flex-shrink-0">
                        <?php echo strtoupper(substr($active_tab === 'inbox' ? $msg['sender'] : $msg['recipient'], 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 gap-2">
                            <span class="text-sm font-semibold text-gray-800">
                                <?php echo $active_tab === 'inbox'
                                    ? 'From: ' . htmlspecialchars($msg['sender'])
                                    : 'To: ' . htmlspecialchars($msg['recipient']); ?>
                            </span>
                            <span class="text-xs text-gray-300 whitespace-nowrap">
                                <?php echo date('M j, g:i A', strtotime($msg['sent_at'])); ?>
                            </span>
                        </div>
                        <?php if ($msg['subject']): ?>
                        <p class="text-sm font-medium text-gray-600 mt-0.5"><?php echo htmlspecialchars($msg['subject']); ?></p>
                        <?php endif; ?>
                        <p class="text-sm text-gray-400 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($msg['body'], 0, 160)); ?><?php echo strlen($msg['body']) > 160 ? '...' : ''; ?></p>
                    </div>
                    <?php if (!$msg['is_read'] && $active_tab === 'inbox'): ?>
                    <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
