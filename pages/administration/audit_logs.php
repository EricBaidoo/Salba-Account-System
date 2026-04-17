<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login');
    exit;
}

// Filtering Logic
$user_filter = $_GET['user'] ?? '';
$action_filter = $_GET['action'] ?? '';
$date_filter = $_GET['date'] ?? '';

$query = "
    SELECT l.*, u.username 
    FROM system_audit_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE 1=1
";
$params = [];
$types = "";

if ($user_filter) {
    $query .= " AND u.username = ?";
    $params[] = $user_filter;
    $types .= "s";
}
if ($action_filter) {
    $query .= " AND l.action = ?";
    $params[] = $action_filter;
    $types .= "s";
}
if ($date_filter) {
    $query .= " AND DATE(l.created_at) = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$query .= " ORDER BY l.created_at DESC LIMIT 500";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch unique actions and users for filters
$actions_res = $conn->query("SELECT DISTINCT action FROM system_audit_logs ORDER BY action ASC");
$users_res = $conn->query("SELECT DISTINCT username FROM users ORDER BY username ASC");

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Audit Logs - <?= htmlspecialchars($school_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .log-row:hover { background-color: #f8fafc; }
        .json-viewer { font-family: 'Courier New', Courier, monospace; font-size: 11px; white-space: pre-wrap; }
    </style>
</head>
<body class="bg-gray-50 flex">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="flex-1 lg:ml-72 min-h-screen">
        
        <!-- Sticky Header -->
        <header class="bg-white/80 backdrop-blur-md border-b border-gray-100 px-8 py-6 sticky top-0 z-40">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h1 class="text-3xl font-black text-slate-900 tracking-tighter flex items-center gap-3">
                        <i class="fas fa-fingerprint text-indigo-600"></i> System Audit Trail
                    </h1>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-[0.2em] mt-1">Forensic Activity Monitoring</p>
                </div>
                
                <!-- Quick Filters -->
                <form class="flex flex-wrap items-center gap-3">
                    <select name="user" onchange="this.form.submit()" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <option value="">All Users</option>
                        <?php while($u = $users_res->fetch_assoc()): ?>
                            <option value="<?= $u['username'] ?>" <?= $user_filter === $u['username'] ? 'selected' : '' ?>><?= $u['username'] ?></option>
                        <?php endwhile; ?>
                    </select>

                    <select name="action" onchange="this.form.submit()" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-indigo-500/10">
                        <option value="">All Actions</option>
                        <?php while($a = $actions_res->fetch_assoc()): ?>
                            <option value="<?= $a['action'] ?>" <?= $action_filter === $a['action'] ? 'selected' : '' ?>><?= $a['action'] ?></option>
                        <?php endwhile; ?>
                    </select>

                    <input type="date" name="date" value="<?= $date_filter ?>" onchange="this.form.submit()" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2 text-xs font-bold text-slate-600 outline-none focus:ring-2 focus:ring-indigo-500/10">
                    
                    <?php if($user_filter || $action_filter || $date_filter): ?>
                        <a href="audit_logs.php" class="text-rose-500 hover:text-rose-700 font-black text-[10px] uppercase tracking-widest ml-2">Clear Filters</a>
                    <?php endif; ?>
                </form>
            </div>
        </header>

        <div class="p-8">
            <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest pl-10">Timestamp</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Operator</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Event Category</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Action Details</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Device Metadata</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php if(empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-20 text-center">
                                        <div class="w-16 h-16 bg-slate-50 rounded-2xl flex items-center justify-center mx-auto mb-4 text-slate-300">
                                            <i class="fas fa-search text-2xl"></i>
                                        </div>
                                        <p class="text-slate-400 font-bold text-sm">No matching audit records found.</p>
                                    </td>
                                </tr>
                            <?php else: foreach($logs as $log): 
                                $badgeColor = match($log['action']) {
                                    'Auth' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                    'Identity' => 'bg-indigo-50 text-indigo-600 border-indigo-100',
                                    'System' => 'bg-orange-50 text-orange-600 border-orange-100',
                                    'Security' => 'bg-rose-50 text-rose-600 border-rose-100',
                                    default => 'bg-slate-50 text-slate-600 border-slate-100'
                                };
                            ?>
                                <tr class="log-row transition-colors group">
                                    <td class="px-6 py-6 pl-10">
                                        <div class="flex items-center gap-3">
                                            <div class="w-2 h-2 rounded-full bg-slate-200 group-hover:bg-indigo-500 transition-colors"></div>
                                            <div>
                                                <p class="text-xs font-black text-slate-900 leading-none mb-1"><?= date('H:i:s', strtotime($log['created_at'])) ?></p>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase"><?= date('M j, Y', strtotime($log['created_at'])) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center font-black text-[10px]">
                                                <?= strtoupper(substr($log['username'] ?? 'Sys', 0, 2)) ?>
                                            </div>
                                            <p class="text-xs font-black text-slate-700">@<?= htmlspecialchars($log['username'] ?? 'System') ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border <?= $badgeColor ?>">
                                            <?= $log['action'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-6 max-w-sm">
                                        <p class="text-xs font-bold text-slate-800 leading-relaxed"><?= htmlspecialchars($log['description']) ?></p>
                                        <?php if($log['old_values'] || $log['new_values']): ?>
                                            <button onclick="toggleDetails('det-<?= $log['id'] ?>')" class="mt-2 text-[9px] font-black text-indigo-600 uppercase tracking-widest hover:text-black">
                                                View State Changes <i class="fas fa-chevron-down ml-1"></i>
                                            </button>
                                            <div id="det-<?= $log['id'] ?>" class="hidden mt-4 grid grid-cols-1 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100 animate-in fade-in slide-in-from-top-2">
                                                <?php if($log['old_values']): ?>
                                                    <div>
                                                        <p class="text-[8px] font-black text-rose-400 uppercase tracking-widest mb-1">Before</p>
                                                        <div class="json-viewer p-2 bg-white rounded-lg border border-slate-100 overflow-x-auto"><?= htmlspecialchars($log['old_values']) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if($log['new_values']): ?>
                                                    <div>
                                                        <p class="text-[8px] font-black text-emerald-400 uppercase tracking-widest mb-1">After</p>
                                                        <div class="json-viewer p-2 bg-white rounded-lg border border-slate-100 overflow-x-auto"><?= htmlspecialchars($log['new_values']) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="flex items-start gap-4">
                                            <div class="p-2 bg-slate-50 rounded-lg text-slate-400">
                                                <i class="fas fa-network-wired text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-[10px] font-black text-slate-900"><?= $log['ip_address'] ?></p>
                                                <p class="text-[9px] font-medium text-slate-400 line-clamp-1 max-w-[150px]" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= htmlspecialchars($log['user_agent']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="px-10 py-6 bg-slate-50/50 border-t border-gray-100 flex items-center justify-between">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Displaying latest 500 security events</p>
                    <div class="flex items-center gap-4">
                        <!-- Pagination etc -->
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script>
        function toggleDetails(id) {
            const el = document.getElementById(id);
            el.classList.toggle('hidden');
        }
    </script>
</body>
</html>
