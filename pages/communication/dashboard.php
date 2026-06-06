<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_term         = getCurrentSemester($conn);
$academic_year        = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);
$current_user         = $_SESSION['username'] ?? '';

// Check if tables exist
$conn->query("CREATE TABLE IF NOT EXISTS announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255), message TEXT, audience VARCHAR(50) DEFAULT 'all', created_by VARCHAR(100), created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, sender VARCHAR(100), recipient VARCHAR(100), subject VARCHAR(255), body TEXT, is_read TINYINT(1) DEFAULT 0, sent_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$conn->query("CREATE TABLE IF NOT EXISTS sms_logs (id INT AUTO_INCREMENT PRIMARY KEY, recipient_phone VARCHAR(50), message_body TEXT, sender_id VARCHAR(50), provider VARCHAR(50), status VARCHAR(50), api_response TEXT, sent_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

// Stats Filtering Logic
$filter = $_GET['filter'] ?? 'all';
$date_where_ann = "1=1";
$date_where_msg = "1=1";
$date_where_sms = "1=1";

if ($filter === 'daily') {
    $date_where_ann = "DATE(created_at) = CURDATE()";
    $date_where_msg = "DATE(sent_at) = CURDATE()";
    $date_where_sms = "DATE(sent_at) = CURDATE()";
} elseif ($filter === 'weekly') {
    $date_where_ann = "YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
    $date_where_msg = "YEARWEEK(sent_at, 1) = YEARWEEK(CURDATE(), 1)";
    $date_where_sms = "YEARWEEK(sent_at, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'monthly') {
    $date_where_ann = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    $date_where_msg = "MONTH(sent_at) = MONTH(CURDATE()) AND YEAR(sent_at) = YEAR(CURDATE())";
    $date_where_sms = "MONTH(sent_at) = MONTH(CURDATE()) AND YEAR(sent_at) = YEAR(CURDATE())";
} elseif ($filter === 'yearly') {
    $date_where_ann = "YEAR(created_at) = YEAR(CURDATE())";
    $date_where_msg = "YEAR(sent_at) = YEAR(CURDATE())";
    $date_where_sms = "YEAR(sent_at) = YEAR(CURDATE())";
} elseif ($filter === 'termly') {
    $term_start = getSystemSetting($conn, 'semester_start_date', date('Y-01-01'));
    $term_end = getSystemSetting($conn, 'semester_end_date', date('Y-12-31'));
    $date_where_ann = "DATE(created_at) BETWEEN '{$term_start}' AND '{$term_end}'";
    $date_where_msg = "DATE(sent_at) BETWEEN '{$term_start}' AND '{$term_end}'";
    $date_where_sms = "DATE(sent_at) BETWEEN '{$term_start}' AND '{$term_end}'";
}

// Stats
$total_announcements = $conn->query("SELECT COUNT(*) as c FROM announcements WHERE $date_where_ann")->fetch_assoc()['c'] ?? 0;
$total_messages      = $conn->query("SELECT COUNT(*) as c FROM messages WHERE $date_where_msg")->fetch_assoc()['c'] ?? 0;
$unread_messages     = $conn->query("SELECT COUNT(*) as c FROM messages WHERE recipient = '{$current_user}' AND is_read = 0")->fetch_assoc()['c'] ?? 0;

$sms_sent = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM sms_logs WHERE status = 'success' AND $date_where_sms");
if ($res) $sms_sent = $res->fetch_assoc()['c'] ?? 0;

// Recent Data
$recent_announcements = [];
$r = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $recent_announcements[] = $row;

$recent_inbox = [];
$r = $conn->query("SELECT * FROM messages WHERE recipient = '{$current_user}' ORDER BY sent_at DESC LIMIT 4");
if ($r) while ($row = $r->fetch_assoc()) $recent_inbox[] = $row;

$recent_sms = [];
$r = $conn->query("SELECT * FROM sms_logs ORDER BY sent_at DESC LIMIT 5");
if ($r) while ($row = $r->fetch_assoc()) $recent_sms[] = $row;

// Handle Quick SMS Dispatch
$success_msg = ''; $error_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_sms') {
    include_once '../../includes/sms_gateway.php';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    $res = send_sms($phone, $message);
    if($res['success']) {
        $success_msg = "SMS successfully dispatched to $phone!";
    } else {
        $error_msg = "Failed to send SMS: " . $res['error'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Dashboard — <?= htmlspecialchars($school_name) ?></title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
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
        
        <?php if ($success_msg): ?>
            <div class="bg-emerald-500 text-white px-6 py-3 font-medium text-sm flex items-center justify-between">
                <span><i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($success_msg) ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="bg-rose-500 text-white px-6 py-3 font-medium text-sm flex items-center justify-between">
                <span><i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($error_msg) ?></span>
                <button onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <!-- Colorful yet Professional Header -->
        <header class="bg-gradient-to-r from-blue-700 via-indigo-600 to-purple-600 shadow-md relative overflow-hidden">
            <div class="absolute inset-0 bg-black/5"></div>
            <div class="px-6 md:px-10 py-10 relative z-10">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-1.5">
                            <span class="text-[0.65rem] font-bold text-indigo-700 bg-white px-2.5 py-0.5 rounded shadow-sm uppercase tracking-wider">Communication Hub</span>
                            <span class="text-white/80 text-xs font-semibold"><i class="far fa-calendar-alt mr-1"></i> <?= $current_term ?> &middot; <?= $display_academic_year ?></span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white drop-shadow-sm">Communication Overview</h1>
                        <p class="text-indigo-100 text-sm mt-1 font-medium">A high-level summary of all messaging and announcement activity.</p>
                    </div>
                    
                    <!-- Time Filter Dropdown -->
                    <div class="relative mt-2 md:mt-0">
                        <form method="GET" action="" id="filter-form" class="flex items-center bg-white/10 border border-white/20 rounded-lg p-1 backdrop-blur-sm shadow-sm hover:bg-white/20 transition-colors">
                            <i class="fas fa-filter text-white/70 ml-3 text-sm"></i>
                            <select name="filter" onchange="document.getElementById('filter-form').submit();" class="bg-transparent text-white text-sm font-bold border-none outline-none focus:ring-0 cursor-pointer appearance-none pl-2 pr-8 py-2">
                                <option value="all" class="text-slate-800" <?= $filter === 'all' ? 'selected' : '' ?>>All Time</option>
                                <option value="daily" class="text-slate-800" <?= $filter === 'daily' ? 'selected' : '' ?>>Today</option>
                                <option value="weekly" class="text-slate-800" <?= $filter === 'weekly' ? 'selected' : '' ?>>This Week</option>
                                <option value="monthly" class="text-slate-800" <?= $filter === 'monthly' ? 'selected' : '' ?>>This Month</option>
                                <option value="termly" class="text-slate-800" <?= $filter === 'termly' ? 'selected' : '' ?>>This Term</option>
                                <option value="yearly" class="text-slate-800" <?= $filter === 'yearly' ? 'selected' : '' ?>>This Year</option>
                            </select>
                            <i class="fas fa-chevron-down text-white/70 text-[10px] absolute right-4 pointer-events-none"></i>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-6 md:px-10 py-8 w-full">
            
            <!-- Key Metrics Row -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8 -mt-6 relative z-20">
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-orange-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-orange-100">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Broadcasts</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?php echo $total_announcements; ?></div>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300 relative overflow-hidden">
                    <?php if($unread_messages > 0): ?>
                    <div class="absolute top-0 right-0 bg-rose-500 text-white text-[10px] font-bold px-2 py-1 rounded-bl-lg">
                        <?= $unread_messages ?> UNREAD
                    </div>
                    <?php endif; ?>
                    <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-blue-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-blue-100">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">System Messages</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?php echo $total_messages; ?></div>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-emerald-100">
                        <i class="fas fa-comment-sms"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">SMS Sent</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5"><?= $sms_sent ?></div>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-md shadow-slate-200/50 flex items-center gap-4 group hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 rounded-lg bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0 group-hover:bg-purple-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-purple-100">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">Emails Sent</div>
                        <div class="text-2xl font-bold text-slate-800 mt-0.5">0</div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Links -->
            <div class="flex flex-wrap gap-3 mb-8">
                <button onclick="document.getElementById('modal-quick-sms').classList.remove('hidden')" class="bg-emerald-600 border-emerald-700 text-white text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-emerald-700 transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i> Send SMS
                </button>
                <a href="messages/view_messages.php" class="bg-white border border-slate-200 text-slate-700 text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-50 hover:text-blue-600 transition-colors">
                    <i class="fas fa-inbox mr-2"></i> Send Internal Message
                </a>
                <a href="announcements/view_announcements.php" class="bg-white border border-slate-200 text-slate-700 text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-50 hover:text-orange-600 transition-colors">
                    <i class="fas fa-bullhorn mr-2"></i> Post Announcement
                </a>
                <a href="settings.php" class="bg-slate-800 text-white text-sm font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-900 transition-colors ml-auto">
                    <i class="fas fa-cog mr-2"></i> Comm Settings
                </a>
            </div>

            <!-- 3 Column Data Summary -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Live SMS Feed Widget -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900"><i class="fas fa-satellite-dish text-emerald-500 mr-1.5"></i> Live SMS Feed</h2>
                        <a href="settings.php" class="text-xs font-bold text-emerald-600 hover:underline">Gateways</a>
                    </div>
                    <div class="divide-y divide-slate-100 flex-1">
                        <?php if (empty($recent_sms)): ?>
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-xl mx-auto mb-3"><i class="fas fa-comment-slash"></i></div>
                                <h3 class="text-sm font-bold text-slate-600">No SMS Dispatched</h3>
                                <p class="text-xs text-slate-400 mt-1">The gateway engine is quiet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_sms as $sms): ?>
                            <div class="p-4 hover:bg-slate-50 transition-colors block">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2">
                                        <?php if($sms['status'] === 'success'): ?>
                                            <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                        <?php else: ?>
                                            <div class="w-2 h-2 rounded-full bg-rose-500"></div>
                                        <?php endif; ?>
                                        <span class="text-xs font-bold text-slate-800"><?= htmlspecialchars($sms['recipient_phone']) ?></span>
                                    </div>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">
                                        <?php echo date('g:i A', strtotime($sms['sent_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 font-medium line-clamp-1 mb-1">
                                    <?php echo htmlspecialchars($sms['message_body']); ?>
                                </p>
                                <div class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1">
                                    <i class="fas fa-id-card text-[8px]"></i> <?= htmlspecialchars($sms['sender_id']) ?> 
                                    <span class="px-1 text-slate-300">&bull;</span>
                                    <?= htmlspecialchars($sms['provider']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Your Inbox Summary Widget -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900"><i class="fas fa-inbox text-blue-500 mr-1.5"></i> Recent Messages</h2>
                        <a href="messages/view_messages.php" class="text-xs font-bold text-blue-600 hover:underline">View All</a>
                    </div>
                    <div class="divide-y divide-slate-100 flex-1">
                        <?php if (empty($recent_inbox)): ?>
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-xl mx-auto mb-3"><i class="fas fa-envelope-open"></i></div>
                                <h3 class="text-sm font-bold text-slate-600">Inbox Empty</h3>
                                <p class="text-xs text-slate-400 mt-1">You have no recent messages.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_inbox as $msg): ?>
                            <a href="messages/view_messages.php?tab=inbox" class="p-4 block hover:bg-slate-50 transition-colors <?php echo !$msg['is_read'] ? 'border-l-4 border-blue-500 bg-blue-50/30' : ''; ?>">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="w-7 h-7 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center text-[10px] font-bold flex-shrink-0">
                                        <?php echo strtoupper(substr($msg['sender'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex justify-between items-center mb-0.5">
                                            <span class="text-xs font-bold <?php echo !$msg['is_read'] ? 'text-slate-900' : 'text-slate-700'; ?>">
                                                From: <?php echo htmlspecialchars($msg['sender']); ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">
                                                <?php echo date('M j', strtotime($msg['sent_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 font-medium line-clamp-2">
                                            <?php echo htmlspecialchars($msg['subject'] ?: '(No Subject)'); ?> &middot; <?php echo htmlspecialchars($msg['body']); ?>
                                        </p>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Announcements Summary Widget -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-slate-900"><i class="fas fa-bullhorn text-orange-500 mr-1.5"></i> Broadcasts</h2>
                        <a href="announcements/view_announcements.php" class="text-xs font-bold text-orange-600 hover:underline">View All</a>
                    </div>
                    <div class="divide-y divide-slate-100 flex-1">
                        <?php if (empty($recent_announcements)): ?>
                            <div class="p-8 text-center">
                                <div class="w-12 h-12 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-xl mx-auto mb-3"><i class="fas fa-comment-slash"></i></div>
                                <h3 class="text-sm font-bold text-slate-600">No Announcements</h3>
                                <p class="text-xs text-slate-400 mt-1">No broadcasts have been published.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_announcements as $ann): ?>
                            <div class="p-4 hover:bg-slate-50 transition-colors block">
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-[8px] font-black uppercase tracking-widest bg-orange-100 text-orange-600 px-2 py-0.5 rounded">
                                        <?= htmlspecialchars(ucfirst($ann['audience'])) ?>
                                    </span>
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">
                                        <?php echo date('M j, Y', strtotime($ann['created_at'])); ?>
                                    </span>
                                </div>
                                <h3 class="text-xs font-bold text-slate-800 leading-tight mb-1"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                <p class="text-[11px] text-slate-500 font-medium line-clamp-2">
                                    <?php echo htmlspecialchars($ann['message']); ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Quick SMS Modal -->
    <div id="modal-quick-sms" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-widest"><i class="fas fa-paper-plane text-emerald-500 mr-2"></i> Send Quick SMS</h3>
                <button onclick="document.getElementById('modal-quick-sms').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form method="POST" action="" class="p-6">
                <input type="hidden" name="action" value="quick_sms">
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Recipient Phone Number *</label>
                    <input type="text" name="phone" required placeholder="e.g. 024XXXXXXX" class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-3 outline-none font-medium">
                </div>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Message Body *</label>
                    <textarea name="message" required rows="4" placeholder="Type your text message here..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 text-sm rounded-lg focus:ring-emerald-500 focus:border-emerald-500 block p-3 outline-none font-medium resize-none"></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold py-2.5 px-6 rounded-lg shadow-sm transition-colors flex-1">
                        <i class="fas fa-paper-plane mr-1.5"></i> Send Message
                    </button>
                    <button type="button" onclick="document.getElementById('modal-quick-sms').classList.add('hidden')" class="bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold py-2.5 px-6 rounded-lg transition-colors flex-1">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
