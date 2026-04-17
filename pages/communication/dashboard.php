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

// Announcement stats
$total_announcements = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM announcements");
if ($r) $total_announcements = $r->fetch_assoc()['c'] ?? 0;

$recent_announcements = [];
$r = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $recent_announcements[] = $row;
    }
}

// Messages stats
$total_messages = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM messages");
if ($r) $total_messages = $r->fetch_assoc()['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Hub — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-xs font-bold text-orange-600 uppercase tracking-widest bg-orange-50 px-3 py-1 rounded-full">
                    <i class="fas fa-envelope mr-1"></i> Communication
                </span>
                <span class="text-xs text-gray-400">
                    <?php echo htmlspecialchars($current_term); ?> &middot; <?php echo htmlspecialchars($display_academic_year); ?>
                </span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">Communication Hub</h1>
            <p class="text-gray-500 mt-1">Send announcements, manage messages, and keep staff and parents informed</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-orange-500"><?php echo $total_announcements; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Announcements</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-blue-600"><?php echo $total_messages; ?></div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Messages</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-green-500">0</div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">SMS Sent</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="text-3xl font-bold text-purple-600">0</div>
                <div class="text-xs font-semibold text-gray-400 uppercase mt-1">Notifications</div>
            </div>
        </div>

        <!-- Module Cards -->
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-bolt"></i> Communication Tools
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">

            <a href="announcements/view_announcements.php"
               class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-orange-200 transition-all group">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-bullhorn text-orange-500"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 group-hover:text-orange-600 transition-colors">Announcements</h3>
                </div>
                <p class="text-sm text-gray-400">Post and manage school-wide announcements for staff and parents</p>
            </a>

            <a href="messages/view_messages.php"
               class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 hover:shadow-md hover:border-blue-200 transition-all group">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                        <i class="fas fa-message text-blue-500"></i>
                    </div>
                    <h3 class="font-bold text-gray-800 group-hover:text-blue-600 transition-colors">Messages</h3>
                </div>
                <p class="text-sm text-gray-400">Send and view messages between staff members and parents</p>
            </a>

            <a href="settings.php"
               class="bg-indigo-900 border border-indigo-700 rounded-xl shadow-xl p-5 hover:shadow-2xl hover:bg-indigo-950 transition-all group">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-tower-broadcast text-white"></i>
                    </div>
                    <h3 class="font-bold text-white transition-colors">Comm Settings</h3>
                </div>
                <p class="text-sm text-indigo-300">Provision SMS gateways, SMTP servers, and notification triggers</p>
                <span class="mt-3 inline-block text-xs font-black bg-indigo-500/20 text-indigo-400 px-3 py-1 rounded-full uppercase tracking-widest">Configure Node</span>
            </a>

        </div>

        <!-- Recent Announcements -->
        <?php if (!empty($recent_announcements)): ?>
        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3 flex items-center gap-2">
            <i class="fas fa-clock"></i> Recent Announcements
        </h2>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <?php foreach ($recent_announcements as $ann): ?>
            <div class="p-4 border-b border-gray-50 last:border-b-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($ann['title'] ?? '—'); ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?php echo htmlspecialchars(substr($ann['message'] ?? '', 0, 120)); ?>...</p>
                    </div>
                    <span class="text-xs text-gray-300 whitespace-nowrap">
                        <?php echo date('M j', strtotime($ann['created_at'] ?? 'now')); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</body>
</html>
