<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['teacher', 'facilitator', 'admin'])) {
    header('Location: ../../login');
    exit;
}

$user_id = $_SESSION['user_id'];
$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

// Filters
$week_f = intval($_GET['week'] ?? 0);
$class_f = $_GET['class'] ?? '';
$search_f = trim($_GET['search'] ?? '');

$where = "l.teacher_id = $user_id";
if ($week_f) $where .= " AND l.week_number = $week_f";
if ($class_f) $where .= " AND l.class_name = '" . $conn->real_escape_string($class_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $where .= " AND (s.name LIKE '%$s%' OR l.class_name LIKE '%$s%')";
}

// Fetch Teacher's Allocated Classes for Filter
$current_academic_year = getAcademicYear($conn);
$teacher_classes_res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $user_id AND year = '$current_academic_year' ORDER BY class_name ASC");
$teacher_classes = [];
if ($teacher_classes_res) {
    while($r = $teacher_classes_res->fetch_assoc()) $teacher_classes[] = $r['class_name'];
}

// Stats (Filtered)
$stats = ['draft' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
$st_res = $conn->query("SELECT l.status, COUNT(*) as count FROM weekly_reports l LEFT JOIN subjects s ON l.subject_id = s.id WHERE $where GROUP BY l.status");
if ($st_res) {
    while($r = $st_res->fetch_assoc()) $stats[$r['status']] = $r['count'];
}

// Fetch Profile Data
$prof_res = $conn->query("SELECT u.username, u.role, sp.* FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $user_id LIMIT 1");
$staff = $prof_res->fetch_assoc();

// Expected vs Submitted calculations
$current_week = 1;
if (function_exists('getWeekNumberForDate')) {
    $current_week = getWeekNumberForDate($conn, date('Y-m-d'));
}
$teacher_classes_count = count($teacher_classes);
$cumulative_expected = $teacher_classes_count * $current_week;
$cumulative_submitted = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE teacher_id = $user_id AND status != 'draft'")->fetch_row()[0] ?? 0;

function getReports($conn, $where, $status) {
    return $conn->query("
        SELECT l.* 
        FROM weekly_reports l 
        WHERE $where AND l.status = '$status' 
        ORDER BY l.created_at DESC
    ");
}

$drafts = getReports($conn, $where, 'draft');
$pending = getReports($conn, $where, 'pending');
$approved = getReports($conn, $where, 'approved');
$rejected = getReports($conn, $where, 'rejected');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporting Dashboard | Teacher Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.portfolio-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            
            document.querySelectorAll('.portfolio-tab-btn').forEach(el => {
                el.classList.remove('border-teal-600', 'text-teal-600', 'bg-teal-50');
                el.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            });
            
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            activeBtn.classList.add('border-teal-600', 'text-teal-600', 'bg-teal-50');
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
            <div>
                <h1 class="text-4xl font-black text-gray-900 tracking-tight">Weekly <span class="text-teal-600">Reports</span></h1>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Manage, Track, and Submit your performance reports</p>
            </div>
            <a href="weekly_reports" class="bg-teal-600 text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-700 transition shadow-xl shadow-teal-100 flex items-center gap-3 active:scale-95">
                <i class="fas fa-plus"></i> Create New Report
            </a>
        </div>

        <!-- Stats Bar -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-teal-200 transition-all">
                <div class="w-14 h-14 bg-teal-50 rounded-2xl flex items-center justify-center text-teal-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-file-pen"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Drafts</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['draft'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-yellow-200 transition-all">
                <div class="w-14 h-14 bg-yellow-50 rounded-2xl flex items-center justify-center text-yellow-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Pending Review</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['pending'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-emerald-200 transition-all">
                <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-check-double"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Approved</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['approved'] ?></div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-5 group hover:border-red-200 transition-all">
                <div class="w-14 h-14 bg-red-50 rounded-2xl flex items-center justify-center text-red-500 text-2xl group-hover:scale-110 transition-transform shadow-inner">
                    <i class="fas fa-circle-exclamation"></i>
                </div>
                <div>
                    <div class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Rejected</div>
                    <div class="text-3xl font-black text-gray-900"><?= $stats['rejected'] ?></div>
                </div>
            </div>
        </div>

        <!-- Progress Overview -->
        <div class="bg-teal-600 rounded-3xl p-6 md:p-8 mb-10 shadow-xl shadow-teal-200 text-white relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="absolute right-0 top-0 opacity-10 pointer-events-none p-4">
                <i class="fas fa-chart-line text-[10rem]"></i>
            </div>
            <div class="relative z-10 flex items-center gap-6">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl backdrop-blur-sm border border-white/30">
                    <i class="fas fa-bullseye"></i>
                </div>
                <div>
                    <h2 class="text-sm font-black text-teal-200 uppercase tracking-widest mb-1">Semester Submission Progress</h2>
                    <div class="text-3xl font-black"><?= $cumulative_submitted ?> <span class="text-xl font-bold text-teal-200">/ <?= $cumulative_expected ?> Expected</span></div>
                    <div class="text-xs text-teal-100 mt-1">Based on <?= $teacher_classes_count ?> reports per week (up to Week <?= $current_week ?>)</div>
                </div>
            </div>
            <div class="relative z-10 w-full md:w-1/3">
                <div class="flex justify-between text-xs font-bold mb-2">
                    <span>Progress</span>
                    <span><?= $cumulative_expected > 0 ? round(($cumulative_submitted / $cumulative_expected) * 100) : 0 ?>%</span>
                </div>
                <div class="w-full bg-teal-900/50 rounded-full h-3">
                    <div class="bg-white rounded-full h-3 transition-all duration-1000" style="width: <?= $cumulative_expected > 0 ? min(100, ($cumulative_submitted / $cumulative_expected) * 100) : 0 ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 mb-10">
            <form class="flex flex-wrap items-center gap-4 w-full">
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search by class..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold">
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class</label>
                    <select name="class" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[120px]">
                        <option value="">All Classes</option>
                        <?php foreach($teacher_classes as $tc): ?>
                            <option value="<?= htmlspecialchars($tc) ?>" <?= $class_f === $tc ? 'selected' : '' ?>><?= htmlspecialchars($tc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</label>
                    <select name="week" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[100px]">
                        <option value="0">All Weeks</option>
                        <?php for($i=1; $i<=$total_weeks; $i++): ?>
                            <option value="<?= $i ?>" <?= $week_f == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" class="bg-teal-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-teal-700 transition shadow-lg shadow-teal-100">
                    Filter
                </button>
                <?php if($week_f || $class_f || $search_f): ?>
                    <a href="report_portfolio" class="text-[0.625rem] font-black text-gray-400 uppercase hover:text-red-500 transition tracking-widest">Clear All</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Dashboard Content -->
        <div class="space-y-12">
            
            <!-- PRIORITY ZONE: Rejected & Drafts -->
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <!-- Rejected Section -->
                <?php if($rejected && $rejected->num_rows > 0): ?>
                <section>
                    <h3 class="text-xs font-black text-red-500 uppercase tracking-[0.2em] mb-4 flex items-center gap-3">
                        <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span> Needs Revision (<?= $stats['rejected'] ?>)
                    </h3>
                    <div class="bg-white rounded-3xl border border-red-100 overflow-hidden shadow-sm">
                        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-red-50/30 border-b border-red-100">
                                    <tr>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-red-600 uppercase tracking-widest whitespace-nowrap">Week</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-red-600 uppercase tracking-widest whitespace-nowrap">Class & Subject</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-red-600 uppercase tracking-widest whitespace-nowrap">Supervisor Remark</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-red-600 uppercase tracking-widest text-right whitespace-nowrap">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-red-50">
                                    <?php while($p = $rejected->fetch_assoc()): ?>
                                        <tr class="hover:bg-red-50/20 transition-colors group">
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-red-600">Wk <?= $p['week_number'] ?></span></td>
                                            <td class="px-6 py-4 min-w-[200px]">
                                                <div class="font-black text-gray-900"><?= htmlspecialchars($p['class_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 min-w-[300px]">
                                                <div class="p-3 bg-red-50/50 rounded-xl border border-red-100/50">
                                                    <p class="text-xs font-bold text-red-800 italic leading-relaxed">"<?= htmlspecialchars($p['supervisor_comments'] ?: 'No specific comments provided. Please review and resubmit.') ?>"</p>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                                <a href="weekly_reports?edit=<?= $p['id'] ?>" class="inline-flex h-9 px-4 bg-red-600 text-white rounded-xl items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest hover:bg-red-700 transition shadow-lg shadow-red-100">
                                                    <i class="fas fa-edit"></i> Fix & Resubmit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

                <!-- Drafts Section -->
                <?php if($drafts && $drafts->num_rows > 0): ?>
                <section>
                    <h3 class="text-xs font-black text-gray-500 uppercase tracking-[0.2em] mb-4 flex items-center gap-3">
                        <span class="w-2 h-2 bg-gray-400 rounded-full"></span> Active Drafts (<?= $stats['draft'] ?>)
                    </h3>
                    <div class="bg-white rounded-3xl border border-gray-100 overflow-hidden shadow-sm">
                        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Last Modified</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php while($p = $drafts->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50/50 transition-colors group">
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-teal-600">Wk <?= $p['week_number'] ?></span></td>
                                            <td class="px-6 py-4 min-w-[200px]">
                                                <div class="font-black text-gray-900"><?= htmlspecialchars($p['class_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-bold text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                                <div class="flex justify-end gap-2">
                                                    <a href="weekly_reports?edit=<?= $p['id'] ?>" class="h-9 px-4 bg-teal-600 text-white rounded-xl flex items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest hover:bg-teal-700 transition shadow-lg shadow-teal-100">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" action="weekly_reports" onsubmit="return confirm('Delete this draft permanently?');">
                                                        <input type="hidden" name="report_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="delete_report" class="w-9 h-9 border border-gray-100 text-gray-400 rounded-xl flex items-center justify-center hover:bg-red-50 hover:text-red-500 transition">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <hr class="border-gray-200/60 my-8">

            <!-- SUBMITTED ARCHIVE (TABS) -->
            <section class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                
                <!-- Tab Headers -->
                <div class="flex border-b border-gray-100 bg-gray-50/50">
                    <button type="button" id="btn-tab-pending" onclick="switchTab('tab-pending')" class="portfolio-tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-teal-600 text-teal-600 bg-teal-50 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-hourglass-half"></i> Pending Review (<?= $stats['pending'] ?>)
                    </button>
                    <button type="button" id="btn-tab-approved" onclick="switchTab('tab-approved')" class="portfolio-tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                        <i class="fas fa-check-double"></i> Approved Archive (<?= $stats['approved'] ?>)
                    </button>
                </div>

                <!-- Tab: Pending Review -->
                <div id="tab-pending" class="portfolio-tab-content p-6">
                    <?php if($pending && $pending->num_rows > 0): ?>
                        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class & Subject</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Submitted On</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Status</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php while($p = $pending->fetch_assoc()): ?>
                                        <tr class="hover:bg-yellow-50/20 transition-colors group">
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-yellow-600">Wk <?= $p['week_number'] ?></span></td>
                                            <td class="px-6 py-4 min-w-[200px]">
                                                <div class="font-black text-gray-900"><?= htmlspecialchars($p['class_name']) ?></div>
                                                <div class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest mt-1"><?= htmlspecialchars($p['subject_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-bold text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-yellow-50 text-yellow-600 rounded-lg text-[0.625rem] font-black uppercase tracking-widest">
                                                    <i class="fas fa-spinner fa-spin"></i> In Queue
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                                <div class="flex justify-end gap-2">
                                                    <a href="print_weekly_report?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-teal-600 transition" title="Preview"><i class="fas fa-eye text-xs"></i></a>
                                                    <form method="POST" action="weekly_reports" onsubmit="return confirm('Unsubmit this report back to drafts?');">
                                                        <input type="hidden" name="report_id" value="<?= $p['id'] ?>">
                                                        <button type="submit" name="unsubmit_report" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-yellow-600 transition" title="Unsubmit">
                                                            <i class="fas fa-rotate-left text-xs"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3 text-gray-200"></i>
                            <p class="font-bold text-sm uppercase tracking-widest">No reports pending review</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab: Approved Archive -->
                <div id="tab-approved" class="portfolio-tab-content p-6 hidden">
                    <?php if($approved && $approved->num_rows > 0): ?>
                        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50/50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Wk</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class & Subject</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Approved On</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Supervisor Remark</th>
                                        <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php while($p = $approved->fetch_assoc()): ?>
                                        <tr class="hover:bg-emerald-50/20 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-emerald-600">Wk <?= $p['week_number'] ?></span></td>
                                            <td class="px-6 py-4 min-w-[200px]">
                                                <div class="font-black text-gray-900"><?= htmlspecialchars($p['class_name']) ?></div>
                                                <div class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest mt-1"><?= htmlspecialchars($p['subject_name']) ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-xs font-bold text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                            <td class="px-6 py-4 min-w-[200px]">
                                                <?php if($p['supervisor_comments']): ?>
                                                    <div class="text-[0.625rem] font-bold text-gray-400 italic leading-relaxed" title="<?= htmlspecialchars($p['supervisor_comments']) ?>">
                                                        "<?= htmlspecialchars($p['supervisor_comments']) ?>"
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-[0.625rem] font-bold text-gray-300 italic">No remarks</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                                <div class="flex justify-end gap-2">
                                                    <a href="print_weekly_report?id=<?= $p['id'] ?>&view=html" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-teal-600 transition" title="Preview"><i class="fas fa-eye text-xs"></i></a>
                                                    <a href="print_weekly_report?id=<?= $p['id'] ?>" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:text-red-500 transition" title="Download PDF"><i class="fas fa-file-pdf text-xs"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="py-12 text-center text-gray-400">
                            <i class="fas fa-archive text-4xl mb-3 text-gray-200"></i>
                            <p class="font-bold text-sm uppercase tracking-widest">No approved reports yet</p>
                        </div>
                    <?php endif; ?>
                </div>

            </section>


        </div>
    </main>

    <!-- Floating Action Button for Mobile -->
    <a href="weekly_reports" class="md:hidden fixed bottom-6 right-6 w-14 h-14 bg-teal-600 text-white rounded-full flex items-center justify-center shadow-2xl z-50 active:scale-95 transition-transform">
        <i class="fas fa-plus text-xl"></i>
    </a>

</body>
</html>
