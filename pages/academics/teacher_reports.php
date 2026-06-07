<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];
$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_term = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);

// --- 1. DYNAMIC MIGRATION FOR REVIEW OVERRIDE ---
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$tables_to_check = ['lesson_plans', 'weekly_reports'];
foreach ($tables_to_check as $table) {
    // Check & Add admin_comments
    $exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = '$table' AND COLUMN_NAME = 'admin_comments'")->fetch_row()[0];
    if (!$exists) {
        $conn->query("ALTER TABLE $table ADD COLUMN admin_comments TEXT NULL");
    }
    // Check & Add admin_id
    $exists2 = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = '$table' AND COLUMN_NAME = 'admin_id'")->fetch_row()[0];
    if (!$exists2) {
        $conn->query("ALTER TABLE $table ADD COLUMN admin_id INT NULL");
    }
}

// --- 2. POST REVIEW HANDLE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_admin_review'])) {
    $report_id = intval($_POST['item_id']);
    $type = $_POST['item_type']; // 'lesson_plan' or 'weekly_report'
    $status = $_POST['status']; // 'approved' or 'rejected'
    $comments = trim($_POST['comments']);
    
    if (in_array($status, ['approved', 'rejected']) && in_array($type, ['lesson_plan', 'weekly_report'])) {
        $table = ($type === 'lesson_plan') ? 'lesson_plans' : 'weekly_reports';
        $stmt = $conn->prepare("UPDATE $table SET status = ?, admin_comments = ?, admin_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $comments, $uid, $report_id);
        
        if ($stmt->execute()) {
            set_flash('success', "Teacher report successfully evaluated by Admin.");
        } else {
            set_flash('error', "Failed to submit admin evaluation.");
        }
        $stmt->close();
    }
    
    // Redirect back preserving get parameters
    header("Location: teacher_reports.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// --- 3. FILTER SETTINGS ---
$week_f   = intval($_GET['week'] ?? 0);
$class_f  = trim($_GET['class'] ?? '');
$status_f = trim($_GET['status'] ?? '');
$search_f = trim($_GET['search'] ?? '');

$filter_where = " WHERE 1=1";
if ($week_f)   $filter_where .= " AND l.week_number = $week_f";
if ($class_f)  $filter_where .= " AND l.class_name = '" . $conn->real_escape_string($class_f) . "'";
if ($status_f) $filter_where .= " AND l.status = '" . $conn->real_escape_string($status_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $filter_where .= " AND (u.username LIKE '%$s%' OR sp.full_name LIKE '%$s%' OR l.class_name LIKE '%$s%')";
}

// Get filter helper lists
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));
$classes_res = $conn->query("SELECT name as class_name FROM classes ORDER BY name ASC");
if ($classes_res && $classes_res->num_rows === 0) {
    $classes_res = $conn->query("SELECT DISTINCT class as class_name FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class ASC");
}

// --- 4. DATA FETCH & AGGREGATE STATS ---
// Lesson plans stats
$lp_total = $conn->query("SELECT COUNT(*) FROM lesson_plans")->fetch_row()[0] ?? 0;
$lp_pending_admin = $conn->query("SELECT COUNT(*) FROM lesson_plans WHERE admin_id IS NULL AND status = 'pending'")->fetch_row()[0] ?? 0;

// Weekly reports stats
$wr_total = $conn->query("SELECT COUNT(*) FROM weekly_reports")->fetch_row()[0] ?? 0;
$wr_pending_admin = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE admin_id IS NULL AND status = 'pending'")->fetch_row()[0] ?? 0;

// Fetch Lesson Plans List
$lp_list = $conn->query("
    SELECT l.*, u.username, COALESCE(sp.full_name, u.username) as teacher_name,
           s.name as subject_name,
           su.username as supervisor_name,
           COALESCE(s_sp.full_name, su.username) as supervisor_full_name
    FROM lesson_plans l
    JOIN users u ON l.teacher_id = u.id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    LEFT JOIN subjects s ON l.subject_id = s.id
    LEFT JOIN users su ON l.supervisor_id = su.id
    LEFT JOIN staff_profiles s_sp ON su.id = s_sp.user_id
    $filter_where
    ORDER BY l.created_at DESC
");

// Fetch Weekly Reports List
$wr_list = $conn->query("
    SELECT l.*, u.username, COALESCE(sp.full_name, u.username) as teacher_name,
           su.username as supervisor_name,
           COALESCE(s_sp.full_name, su.username) as supervisor_full_name
    FROM weekly_reports l
    JOIN users u ON l.teacher_id = u.id
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    LEFT JOIN users su ON l.supervisor_id = su.id
    LEFT JOIN staff_profiles s_sp ON su.id = s_sp.user_id
    $filter_where
    ORDER BY l.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Reports Review Room — <?= htmlspecialchars($school_name) ?></title>
    <link rel="icon" href="<?= BASE_URL . getSystemLogo($conn) ?>">
    
    <!-- Modern Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: { 50: '#f0f9ff', 100: '#e0f2fe', 500: '#0ea5e9', 600: '#0284c7', 900: '#0c4a6e' },
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <style>
        body { background-color: #f8fafc; }
        .glass-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .stat-card-hover:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
    <script>
        function openAdminReviewModal(itemId, itemName, itemWeek, itemType, currentComments, currentStatus) {
            document.getElementById('modal-item-id').value = itemId;
            document.getElementById('modal-item-type').value = itemType;
            document.getElementById('modal-title').innerText = "Admin Override Review: " + itemName;
            document.getElementById('modal-sub').innerText = "Evaluating " + itemType.replace('_', ' ') + " (Wk " + itemWeek + ")";
            
            // Set current comments
            document.getElementById('modal-comments').value = currentComments;
            
            // Set current radio status
            if (currentStatus === 'approved') {
                document.getElementById('status-approved').checked = true;
            } else if (currentStatus === 'rejected') {
                document.getElementById('status-rejected').checked = true;
            } else {
                document.getElementById('status-approved').checked = false;
                document.getElementById('status-rejected').checked = false;
            }
            
            document.getElementById('admin-review-modal').classList.remove('hidden');
        }
        
        function closeAdminReviewModal() {
            document.getElementById('admin-review-modal').classList.add('hidden');
        }
        
        function switchMainTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-indigo-600', 'text-indigo-600', 'bg-indigo-50/50');
                el.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            });
            
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            activeBtn.classList.add('border-indigo-600', 'text-indigo-600', 'bg-indigo-50/50');
            
            // Store active tab selection in session/localStorage to persist on reload
            localStorage.setItem('admin_reports_active_tab', tabId);
        }
        
        document.addEventListener("DOMContentLoaded", function() {
            const activeTab = localStorage.getItem('admin_reports_active_tab') || 'tab-weekly-reports';
            switchMainTab(activeTab);
        });
    </script>
</head>
<body class="text-slate-800 antialiased selection:bg-primary-500 selection:text-white">

    <?php include '../../includes/sidebar.php'; ?>

    <!-- Flash Messages -->
    <?php if (isset($_SESSION['flash'])): ?>
        <div id="flash-banner" class="fixed top-20 left-1/2 -translate-x-1/2 z-50 animate-[slide-down_0.3s_ease-out]">
            <div class="px-6 py-3 rounded-full font-black text-sm uppercase tracking-widest shadow-2xl <?= $_SESSION['flash']['type'] === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white' ?>">
                <?= htmlspecialchars($_SESSION['flash']['message']) ?>
            </div>
        </div>
        <?php unset($_SESSION['flash']); ?>
        <script>setTimeout(() => document.getElementById('flash-banner')?.remove(), 4000);</script>
    <?php endif; ?>

    <main class="lg:ml-72 min-h-screen pb-12 transition-all duration-300">

        <!-- Animated Background Header -->
        <div class="relative bg-gradient-to-br from-slate-900 via-indigo-900 to-purple-900 pt-16 md:pt-20 pb-24 overflow-hidden">
            <div class="absolute inset-0 bg-[url('../../assets/images/pattern-light.svg')] opacity-10"></div>
            <div class="absolute -right-20 -top-20 w-96 h-96 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse"></div>
            <div class="absolute -left-20 top-20 w-72 h-72 bg-indigo-500 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-pulse" style="animation-delay: 2s;"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <span class="bg-white/20 backdrop-blur text-white text-[0.65rem] font-bold uppercase tracking-widest px-3 py-1 rounded-full border border-white/20">
                                <i class="fas fa-paste mr-1"></i> Admin Oversight
                            </span>
                            <span class="text-white/80 text-sm font-medium">
                                <?= htmlspecialchars($current_term) ?> &middot; <?= htmlspecialchars($display_academic_year) ?>
                            </span>
                        </div>
                        <h1 class="text-3xl md:text-4xl font-extrabold text-white font-display tracking-tight drop-shadow-sm">Teacher Reports</h1>
                        <p class="text-indigo-100 mt-2 max-w-2xl text-sm md:text-base">Comprehensive review and override evaluations for all weekly records and weekly lesson plans.</p>
                    </div>
                    <div>
                        <a href="../administration/dashboard.php" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 text-white text-xs font-bold px-4 py-2.5 rounded-xl border border-white/20 transition-all shadow-md">
                            <i class="fas fa-arrow-left"></i> Administration Hub
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 relative z-20 space-y-6">

            <!-- STATS ROW -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Weekly Reports -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-teal-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Weekly Reports</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($wr_total) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-teal-100 text-teal-600 flex items-center justify-center text-lg shadow-inner border border-teal-200">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Weekly Action -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-amber-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Pending Weekly Review</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($wr_pending_admin) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg shadow-inner border border-amber-200">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Lesson Plans -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-indigo-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Lesson Plans</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($lp_total) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-lg shadow-inner border border-indigo-200">
                            <i class="fas fa-file-signature"></i>
                        </div>
                    </div>
                </div>

                <!-- Pending Lesson Plans -->
                <div class="glass-card rounded-2xl p-5 stat-card-hover transition-all duration-300 relative overflow-hidden group">
                    <div class="absolute -right-6 -top-6 w-24 h-24 bg-rose-50 rounded-full group-hover:scale-110 transition-transform"></div>
                    <div class="flex justify-between items-start relative z-10">
                        <div>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mb-1">Pending Lesson Review</p>
                            <h3 class="text-2xl font-extrabold font-display text-slate-800"><?= number_format($lp_pending_admin) ?></h3>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg shadow-inner border border-rose-200">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTERS BAR -->
            <div class="glass-card rounded-3xl p-4 shadow-sm border border-slate-200/60">
                <form class="flex flex-wrap items-center gap-4 w-full" method="GET">
                    
                    <div class="relative flex-1 min-w-[200px]">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search class or teacher..." class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border border-slate-200/50 rounded-xl focus:bg-white focus:border-indigo-500 outline-none transition-all text-xs font-semibold">
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <label class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Class</label>
                        <select name="class" class="px-3 py-2.5 bg-slate-50 border border-slate-200/50 rounded-xl focus:bg-white outline-none text-xs font-semibold appearance-none min-w-[120px]">
                            <option value="">All Classes</option>
                            <?php if($classes_res) {
                                $classes_res->data_seek(0);
                                while($c = $classes_res->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($c['class_name']) ?>" <?= $class_f === $c['class_name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                                <?php endwhile;
                            } ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Week</label>
                        <select name="week" class="px-3 py-2.5 bg-slate-50 border border-slate-200/50 rounded-xl focus:bg-white outline-none text-xs font-semibold appearance-none min-w-[90px]">
                            <option value="0">All Weeks</option>
                            <?php for($i=1; $i<=$total_weeks; $i++): ?>
                                <option value="<?= $i ?>" <?= $week_f == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-[0.6rem] font-black text-slate-400 uppercase tracking-widest whitespace-nowrap">Status</label>
                        <select name="status" class="px-3 py-2.5 bg-slate-50 border border-slate-200/50 rounded-xl focus:bg-white outline-none text-xs font-semibold appearance-none min-w-[110px]">
                            <option value="">All Statuses</option>
                            <option value="pending" <?= $status_f === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status_f === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status_f === 'rejected' ? 'selected' : '' ?>>Needs Revision</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest hover:bg-indigo-700 transition shadow-md shadow-indigo-100 flex items-center gap-2">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <?php if($week_f || $class_f || $status_f || $search_f): ?>
                        <a href="teacher_reports.php" class="text-[0.65rem] font-black text-slate-400 uppercase hover:text-rose-500 transition tracking-widest">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- UNIFIED TABLES CONTAINER -->
            <div class="bg-white rounded-3xl border border-slate-200/60 shadow-sm overflow-hidden mb-12">
                <!-- Tab Headers -->
                <div class="flex border-b border-slate-100 bg-slate-50/50">
                    <button type="button" id="btn-tab-weekly-reports" onclick="switchMainTab('tab-weekly-reports')" class="tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-indigo-600 text-indigo-600 bg-indigo-50/50 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-clipboard-list"></i> Weekly Performance Reports
                        <span class="bg-indigo-600 text-white px-2 py-0.5 rounded-full text-[0.6rem] font-bold"><?= $wr_list ? $wr_list->num_rows : 0 ?></span>
                    </button>
                    <button type="button" id="btn-tab-lesson-plans" onclick="switchMainTab('tab-lesson-plans')" class="tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-file-signature"></i> Weekly Lesson Plans
                        <span class="bg-slate-400 text-white px-2 py-0.5 rounded-full text-[0.6rem] font-bold"><?= $lp_list ? $lp_list->num_rows : 0 ?></span>
                    </button>
                </div>

                <!-- TAB 1: WEEKLY PERFORMANCE REPORTS -->
                <div id="tab-weekly-reports" class="tab-content p-6">
                    <?php if(!$wr_list || $wr_list->num_rows === 0): ?>
                        <div class="py-16 text-center text-slate-400">
                            <i class="fas fa-inbox text-5xl opacity-20 mb-3 block"></i>
                            <p class="text-xs font-semibold">No weekly performance reports found matching criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/30 text-[0.65rem] uppercase tracking-widest text-slate-400 font-black">
                                        <th class="px-6 py-3">Week / Class</th>
                                        <th class="px-6 py-3">Teacher</th>
                                        <th class="px-6 py-3">Supervisor Status</th>
                                        <th class="px-6 py-3">Admin Overrides</th>
                                        <th class="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php while ($r = $wr_list->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="font-extrabold text-slate-800">Class: <?= htmlspecialchars($r['class_name']) ?></div>
                                            <div class="text-[0.65rem] font-bold text-indigo-600 uppercase mt-1">Week <?= $r['week_number'] ?> Ending <?= $r['week_ending'] ? date('jS M, Y', strtotime($r['week_ending'])) : '-' ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-bold text-slate-700"><?= htmlspecialchars($r['teacher_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($r['supervisor_id']): ?>
                                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                                                    <span class="w-2 h-2 rounded-full <?= ($r['status'] === 'approved') ? 'bg-emerald-500' : (($r['status'] === 'rejected') ? 'bg-rose-500' : 'bg-amber-500') ?>"></span>
                                                    <?= ucfirst(htmlspecialchars($r['status'] ?? '')) ?>
                                                </div>
                                                <div class="text-[0.6rem] font-bold text-slate-400 mt-1 uppercase">By <?= htmlspecialchars($r['supervisor_full_name'] ?? '') ?></div>
                                                <?php if($r['supervisor_comments']): ?>
                                                    <div class="text-[0.65rem] font-medium text-slate-500 italic mt-1 max-w-xs truncate" title="<?= htmlspecialchars($r['supervisor_comments'] ?? '') ?>">"<?= htmlspecialchars($r['supervisor_comments'] ?? '') ?>"</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">Not Reviewed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($r['admin_id']): ?>
                                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <i class="fas fa-shield text-indigo-500"></i>
                                                    <span class="font-black text-indigo-600"><?= ($r['status'] === 'approved') ? 'Approved' : 'Needs Revision' ?></span>
                                                </div>
                                                <?php if($r['admin_comments']): ?>
                                                    <div class="text-[0.65rem] font-semibold text-slate-600 italic mt-1 max-w-xs truncate" title="<?= htmlspecialchars($r['admin_comments'] ?? '') ?>">"<?= htmlspecialchars($r['admin_comments'] ?? '') ?>"</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">No Override</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-2">
                                                <a href="../teacher/print_weekly_report.php?id=<?= $r['id'] ?>&view=html" target="_blank" class="h-9 px-3 bg-slate-100 text-slate-600 rounded-xl flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-widest hover:bg-slate-200 transition">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <button type="button" 
                                                        onclick="openAdminReviewModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['class_name'])) ?>', <?= $r['week_number'] ?>, 'weekly_report', '<?= htmlspecialchars(addslashes($r['admin_comments'] ?? '')) ?>', '<?= htmlspecialchars($r['status']) ?>')" 
                                                        class="h-9 px-3 bg-indigo-600 text-white rounded-xl flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                                                    <i class="fas fa-edit"></i> Evaluate
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- TAB 2: WEEKLY LESSON PLANS -->
                <div id="tab-lesson-plans" class="tab-content p-6 hidden">
                    <?php if(!$lp_list || $lp_list->num_rows === 0): ?>
                        <div class="py-16 text-center text-slate-400">
                            <i class="fas fa-inbox text-5xl opacity-20 mb-3 block"></i>
                            <p class="text-xs font-semibold">No lesson plans found matching criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/30 text-[0.65rem] uppercase tracking-widest text-slate-400 font-black">
                                        <th class="px-6 py-3">Week / Class / Subject</th>
                                        <th class="px-6 py-3">Teacher</th>
                                        <th class="px-6 py-3">Supervisor Status</th>
                                        <th class="px-6 py-3">Admin Overrides</th>
                                        <th class="px-6 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <?php while ($r = $lp_list->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="font-extrabold text-slate-800">Class: <?= htmlspecialchars($r['class_name']) ?></div>
                                            <div class="text-xs font-medium text-slate-500 mt-0.5">Subject: <?= htmlspecialchars($r['subject_name'] ?? 'General') ?></div>
                                            <div class="text-[0.65rem] font-bold text-indigo-600 uppercase mt-1">Week <?= $r['week_number'] ?> Ending <?= $r['week_ending'] ? date('jS M, Y', strtotime($r['week_ending'])) : '-' ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-bold text-slate-700"><?= htmlspecialchars($r['teacher_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($r['supervisor_id']): ?>
                                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                                                    <span class="w-2 h-2 rounded-full <?= ($r['status'] === 'approved') ? 'bg-emerald-500' : (($r['status'] === 'rejected') ? 'bg-rose-500' : 'bg-amber-500') ?>"></span>
                                                    <?= ucfirst(htmlspecialchars($r['status'] ?? '')) ?>
                                                </div>
                                                <div class="text-[0.6rem] font-bold text-slate-400 mt-1 uppercase">By <?= htmlspecialchars($r['supervisor_full_name'] ?? '') ?></div>
                                                <?php if($r['supervisor_comments']): ?>
                                                    <div class="text-[0.65rem] font-medium text-slate-500 italic mt-1 max-w-xs truncate" title="<?= htmlspecialchars($r['supervisor_comments'] ?? '') ?>">"<?= htmlspecialchars($r['supervisor_comments'] ?? '') ?>"</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">Not Reviewed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($r['admin_id']): ?>
                                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                                    <i class="fas fa-shield text-indigo-500"></i>
                                                    <span class="font-black text-indigo-600"><?= ($r['status'] === 'approved') ? 'Approved' : 'Needs Revision' ?></span>
                                                </div>
                                                <?php if($r['admin_comments']): ?>
                                                    <div class="text-[0.65rem] font-semibold text-slate-600 italic mt-1 max-w-xs truncate" title="<?= htmlspecialchars($r['admin_comments'] ?? '') ?>">"<?= htmlspecialchars($r['admin_comments'] ?? '') ?>"</div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest bg-slate-50 border border-slate-100 px-2 py-0.5 rounded">No Override</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-2">
                                                <a href="../teacher/print_lesson_plan.php?id=<?= $r['id'] ?>&view=html" target="_blank" class="h-9 px-3 bg-slate-100 text-slate-600 rounded-xl flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-widest hover:bg-slate-200 transition">
                                                    <i class="fas fa-eye"></i> Preview
                                                </a>
                                                <button type="button" 
                                                        onclick="openAdminReviewModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['class_name'])) ?>', <?= $r['week_number'] ?>, 'lesson_plan', '<?= htmlspecialchars(addslashes($r['admin_comments'] ?? '')) ?>', '<?= htmlspecialchars($r['status']) ?>')" 
                                                        class="h-9 px-3 bg-indigo-600 text-white rounded-xl flex items-center gap-1.5 text-[0.65rem] font-bold uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                                                    <i class="fas fa-edit"></i> Evaluate
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </main>

    <!-- ADMIN EVALUATE MODAL -->
    <div id="admin-review-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-[scale-in_0.2s_ease-out]">
            <form method="POST" action="">
                <div class="p-6 md:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 id="modal-title" class="text-xl font-extrabold font-display text-slate-900 tracking-tight">Evaluate Report</h2>
                            <p id="modal-sub" class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest mt-1">Evaluating Details</p>
                        </div>
                        <button type="button" onclick="closeAdminReviewModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition"><i class="fas fa-times"></i></button>
                    </div>
                    
                    <input type="hidden" name="item_id" id="modal-item-id" value="">
                    <input type="hidden" name="item_type" id="modal-item-type" value="">
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[0.65rem] font-black text-slate-400 uppercase tracking-widest mb-3">Admin Decision</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="status" id="status-approved" value="approved" required class="peer sr-only">
                                    <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 hover:bg-slate-50 transition-all text-center">
                                        <i class="fas fa-circle-check text-2xl text-indigo-500 mb-2 transition-transform"></i>
                                        <div class="font-extrabold text-sm text-slate-800">Approve</div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-indigo-500 transition-opacity"><i class="fas fa-check"></i></div>
                                </label>
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="status" id="status-rejected" value="rejected" required class="peer sr-only">
                                    <div class="p-4 rounded-2xl border-2 border-slate-200 peer-checked:border-rose-500 peer-checked:bg-rose-50 hover:bg-slate-50 transition-all text-center">
                                        <i class="fas fa-circle-xmark text-2xl text-rose-500 mb-2 transition-transform"></i>
                                        <div class="font-extrabold text-sm text-slate-800">Needs Revision</div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-rose-500 transition-opacity"><i class="fas fa-check"></i></div>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[0.65rem] font-black text-slate-400 uppercase tracking-widest mb-2">Admin Remarks & Comments (Goes to Teacher Dashboard Alert)</label>
                            <textarea name="comments" id="modal-comments" rows="4" required class="w-full p-4 bg-slate-50 border border-slate-200 rounded-2xl focus:bg-white focus:border-indigo-500 outline-none transition-all resize-none text-sm font-semibold text-slate-700" placeholder="Provide admin feedback or overwrite reasons..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 rounded-b-3xl">
                    <button type="button" onclick="closeAdminReviewModal()" class="px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest text-slate-500 hover:bg-slate-200 transition">Cancel</button>
                    <button type="submit" name="submit_admin_review" class="px-6 py-3 rounded-xl font-bold text-xs uppercase tracking-widest bg-slate-900 text-white hover:bg-black transition shadow-lg">Save Override Review</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
