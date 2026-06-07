<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

$success = '';
$error = '';
$uid = $_SESSION['user_id'];

$teacher_id_get = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$tid_query = $teacher_id_get > 0 ? "?teacher_id=" . $teacher_id_get : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_report'])) {
    $report_id = intval($_POST['report_id']);
    $status = $_POST['status']; // 'approved' or 'rejected'
    $comments = trim($_POST['comments']);
    
    // Server-side check: Superseded by Admin
    $check_admin = $conn->query("SELECT admin_id FROM weekly_reports WHERE id = $report_id")->fetch_row()[0] ?? null;
    if ($check_admin !== null) {
        redirect("weekly_reports{$tid_query}", 'error', "This report is locked by Admin and cannot be modified.");
    }
    
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE weekly_reports SET status = ?, supervisor_comments = ?, supervisor_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $comments, $uid, $report_id);
        if ($stmt->execute()) {
            redirect("weekly_reports{$tid_query}", 'success', "Weekly report marked as " . strtoupper($status) . ".");
        } else {
            set_flash('error', "Failed to update weekly report.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revert_report'])) {
    $report_id = intval($_POST['report_id']);
    
    // Server-side check: Superseded by Admin
    $check_admin = $conn->query("SELECT admin_id FROM weekly_reports WHERE id = $report_id")->fetch_row()[0] ?? null;
    if ($check_admin !== null) {
        redirect("weekly_reports{$tid_query}", 'error', "This report is locked by Admin and cannot be modified.");
    }
    
    $stmt = $conn->prepare("UPDATE weekly_reports SET status = 'pending' WHERE id = ?");
    $stmt->bind_param("i", $report_id);
    if ($stmt->execute()) {
        redirect("weekly_reports{$tid_query}", 'success', "Report status reverted to PENDING.");
    } else {
        set_flash('error', "Failed to revert report status.");
    }
}

// Filters
$week_f = intval($_GET['week'] ?? 0);
$class_f = $_GET['class'] ?? '';
$search_f = trim($_GET['search'] ?? '');

$filter_where = "";
if ($week_f) $filter_where .= " AND l.week_number = $week_f";
if ($class_f) $filter_where .= " AND l.class_name = '" . $conn->real_escape_string($class_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $filter_where .= " AND (s.name LIKE '%$s%' OR u.username LIKE '%$s%' OR sp.full_name LIKE '%$s%' OR l.class_name LIKE '%$s%')";
}

$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));
$classes_res = $conn->query("SELECT name as class_name FROM classes ORDER BY name ASC");
if ($classes_res && $classes_res->num_rows === 0) {
    $classes_res = $conn->query("SELECT DISTINCT class as class_name FROM students WHERE status='active' AND class IS NOT NULL AND class != '' ORDER BY class ASC");
}

// Determine Current Academic Year & Week
$current_academic_year = getAcademicYear($conn);
$current_week = 1;
if (function_exists('getWeekNumberForDate')) {
    $current_week = getWeekNumberForDate($conn, date('Y-m-d'));
}

// Mode Switch: Main Dashboard vs Teacher View
$is_teacher_view = ($teacher_id_get > 0);

if ($is_teacher_view) {
    $filter_where .= " AND l.teacher_id = $teacher_id_get";
    
    // Fetch Teacher Name
    $tname_query = $conn->query("SELECT COALESCE(sp.full_name, u.username) as teacher_name FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $teacher_id_get");
    $active_teacher_name = $tname_query->fetch_row()[0] ?? 'Unknown Teacher';
    
    // Filter classes to only those allocated to this teacher
    $classes_res = $conn->query("SELECT DISTINCT class_name FROM teacher_allocations WHERE teacher_id = $teacher_id_get AND year = '$current_academic_year' ORDER BY class_name ASC");

    // Calculate precise expectations vs actuals for THIS teacher
    $expected_list = [];
    if ($classes_res) {
        $classes_res->data_seek(0); // reset pointer
        while($r = $classes_res->fetch_assoc()) {
            $expected_list[] = trim($r['class_name']);
        }
        $classes_res->data_seek(0); // reset pointer again for the UI dropdown
    }
    
    // Submitted Actuals
    $actual_list = [];
    $w = $week_f ?: $current_week;
    $act_res = $conn->query("SELECT class_name FROM weekly_reports WHERE teacher_id = $teacher_id_get AND week_number = $w AND status != 'draft'");
    if ($act_res) {
        while($r = $act_res->fetch_assoc()) {
            $actual_list[] = trim($r['class_name']);
        }
    }
    
    $owing_list = array_diff($expected_list, $actual_list);
} else {
    // ---- EXPECTATIONS LOGIC ----
    $expectations = [];
    
    // 1. Get Teacher Assignments (One report expected per assigned class, matching Teacher View)
    $sub_res = $conn->query("
        SELECT teacher_id, COUNT(DISTINCT class_name) as c 
        FROM teacher_allocations 
        WHERE year = '$current_academic_year' 
        GROUP BY teacher_id
    ");
    if ($sub_res) { 
        while($row = $sub_res->fetch_assoc()) { 
            $expectations[$row['teacher_id']] = (int)$row['c']; 
        } 
    }
    
    // 5. This Week's Actuals (from weekly_reports)
    $actuals = [];
    $act_res = $conn->query("SELECT teacher_id, COUNT(DISTINCT class_name) as c FROM weekly_reports WHERE week_number = $current_week AND status != 'draft' GROUP BY teacher_id");
    if ($act_res) { while($row = $act_res->fetch_assoc()) { $actuals[$row['teacher_id']] = (int)$row['c']; } }

    // ---- MAIN DASHBOARD QUERIES ----
    $total_facilitators = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'facilitator'")->fetch_row()[0];
    
    // This Week expectations needed for cumulative calculation
    $total_expected_this_week = array_sum($expectations);
    
    // Overall Rate (Cumulative Submitted up to completed weeks / Cumulative Expected)
    // We evaluate up to the completed week (current_week - 1) to avoid penalizing the active rate for an incomplete week.
    $completed_weeks = max(1, $current_week - 1);
    $cumulative_expected = $total_expected_this_week * $completed_weeks;
    
    // Count distinct submissions per teacher, class, and week to avoid double counting
    $cumulative_submitted_query = $conn->query("
        SELECT COUNT(DISTINCT CONCAT(teacher_id, '-', class_name, '-', week_number))
        FROM weekly_reports 
        WHERE week_number > 0 AND week_number <= $completed_weeks AND status != 'draft'
    ");
    $cumulative_submitted = $cumulative_submitted_query ? ($cumulative_submitted_query->fetch_row()[0] ?? 0) : 0;
    $overall_rate = $cumulative_expected > 0 ? round(($cumulative_submitted / $cumulative_expected) * 100) : 0;
    
    // This Week Rate (Total Actuals / Total Expectations)
    $total_actual_this_week = array_sum($actuals);
    $weekly_rate = $total_expected_this_week > 0 ? round(($total_actual_this_week / $total_expected_this_week) * 100) : 0;
    
    // Fetch Facilitator Folders (Only users with role = 'facilitator')
    $folders_query = $conn->query("
        SELECT u.id as teacher_id, COALESCE(sp.full_name, u.username) as teacher_name,
               SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN l.status IN ('approved', 'rejected') THEN 1 ELSE 0 END) as reviewed_count
        FROM users u
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        LEFT JOIN weekly_reports l ON u.id = l.teacher_id $filter_where
        LEFT JOIN teacher_allocations ta ON l.teacher_id = ta.teacher_id AND l.class_name COLLATE utf8mb4_unicode_ci = ta.class_name COLLATE utf8mb4_unicode_ci
        WHERE u.role = 'facilitator' AND (l.id IS NULL OR ta.id IS NOT NULL)
        GROUP BY u.id, teacher_name
        ORDER BY teacher_name ASC
    ");
}

// Fetch pending and reviewed reports (used in both views, but filtered by teacher in Teacher View)
$pending_reports = $conn->query("
    SELECT l.*, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM weekly_reports l 
    JOIN teacher_allocations ta ON l.teacher_id = ta.teacher_id AND l.class_name COLLATE utf8mb4_unicode_ci = ta.class_name COLLATE utf8mb4_unicode_ci
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE l.status = 'pending' $filter_where
    ORDER BY teacher_name ASC, l.created_at ASC
");

$grouped_pending = [];
if ($pending_reports && $pending_reports->num_rows > 0) {
    while ($p = $pending_reports->fetch_assoc()) {
        $teacher = $p['teacher_name'];
        if (!isset($grouped_pending[$teacher])) {
            $grouped_pending[$teacher] = [
                'teacher_id' => $p['teacher_id'],
                'reports' => []
            ];
        }
        $grouped_pending[$teacher]['reports'][] = $p;
    }
}

$reviewed_reports = $conn->query("
    SELECT l.*, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM weekly_reports l 
    JOIN teacher_allocations ta ON l.teacher_id = ta.teacher_id AND l.class_name COLLATE utf8mb4_unicode_ci = ta.class_name COLLATE utf8mb4_unicode_ci
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE l.status IN ('approved', 'rejected') $filter_where
    ORDER BY l.updated_at DESC
    LIMIT 100
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_teacher_view ? "{$active_teacher_name}'s Reports" : "Supervisor | Weekly Reports" ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function openReviewModal(reportId, classSubject, week) {
            document.getElementById('modal-report-id').value = reportId;
            document.getElementById('modal-title').innerText = "Review " + classSubject;
            document.getElementById('modal-week').innerText = "Wk " + week;
            document.getElementById('review-modal').classList.remove('hidden');
        }
        function closeReviewModal() {
            document.getElementById('review-modal').classList.add('hidden');
        }
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');
            
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('border-teal-600', 'text-teal-600', 'bg-teal-50');
                el.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            });
            
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:bg-gray-50');
            activeBtn.classList.add('border-teal-600', 'text-teal-600', 'bg-teal-50');
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 pb-20">

    <?php include '../../includes/top_nav.php'; ?>

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

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 max-w-7xl mx-auto">
        
        <?php if($is_teacher_view): ?>
            <!-- TEACHER SPECIFIC VIEW -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <a href="weekly_reports" class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest hover:text-teal-600 transition flex items-center gap-2 mb-2">
                        <i class="fas fa-arrow-left"></i> Back to Main Dashboard
                    </a>
                    <h1 class="text-4xl font-black text-gray-900 tracking-tight"><?= htmlspecialchars($active_teacher_name) ?></h1>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Teacher Portfolio & Submissions</p>
                </div>
            </div>

            <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-2xl mb-8 shadow-sm">
                <h2 class="text-red-800 font-bold text-lg mb-3 flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle"></i> Missing Reports (Week <?= $week_f ?: $current_week ?>)
                </h2>
                <?php if(empty($owing_list)): ?>
                    <p class="text-green-700 font-bold bg-green-100/50 p-3 rounded-lg flex items-center gap-2">
                        <i class="fas fa-check-circle"></i> This teacher has submitted all expected weekly reports for this week.
                    </p>
                <?php else: ?>
                    <p class="text-red-600 mb-3 text-sm">The following Expected Classes have not been reported on:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($owing_list as $owing): ?>
                            <span class="bg-white border border-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">
                                <i class="fas fa-times text-red-400 mr-1"></i> <?= htmlspecialchars($owing) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- MAIN DASHBOARD VIEW -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                <div>
                    <h1 class="text-4xl font-black text-gray-900 tracking-tight">Report <span class="text-teal-600">Review</span></h1>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-widest mt-2">Manage & evaluate teacher weekly reports</p>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-10">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-teal-50 rounded-bl-full -z-10"></div>
                    <div class="w-16 h-16 bg-teal-100 rounded-2xl flex items-center justify-center text-teal-600 text-2xl shadow-inner">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">This Week Submission Rate</div>
                        <div class="text-3xl font-extrabold text-gray-900"><?= $weekly_rate ?>%</div>
                        <div class="text-[0.625rem] font-bold text-gray-500 uppercase tracking-widest mt-1">Week <?= $current_week ?>: <?= $total_actual_this_week ?> submitted / <?= $total_expected_this_week ?> expected</div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100 flex items-center gap-6 relative overflow-hidden">
                    <div class="absolute right-0 top-0 w-32 h-32 bg-blue-50 rounded-bl-full -z-10"></div>
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 text-2xl shadow-inner">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">Overall Active Rate</div>
                        <div class="text-3xl font-extrabold text-gray-900"><?= $overall_rate ?>%</div>
                        <div class="text-[0.625rem] font-bold text-gray-500 uppercase tracking-widest mt-1"><?= $cumulative_submitted ?> of <?= $cumulative_expected ?> total expected reports submitted</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-3xl shadow-sm border border-gray-100 mb-10">
            <form class="flex flex-wrap items-center gap-4 w-full">
                <?php if($is_teacher_view): ?>
                    <input type="hidden" name="teacher_id" value="<?= $teacher_id_get ?>">
                <?php endif; ?>
                
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search class<?= !$is_teacher_view ? ', teacher' : '' ?>..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold">
                </div>
                
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class</label>
                    <select name="class" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-2xl focus:bg-white focus:ring-4 focus:ring-teal-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[120px]">
                        <option value="">All Classes</option>
                        <?php if($classes_res) while($c = $classes_res->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['class_name']) ?>" <?= $class_f === $c['class_name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endwhile; ?>
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
                
                <button type="submit" class="bg-gray-800 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-gray-900 transition shadow-lg shadow-gray-200">
                    Filter
                </button>
                <?php if($week_f || $class_f || $search_f): ?>
                    <a href="weekly_reports<?= $is_teacher_view ? '?teacher_id='.$teacher_id_get : '' ?>" class="text-[0.625rem] font-black text-gray-400 uppercase hover:text-red-500 transition tracking-widest">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- MAIN TABS -->
        <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden mb-12">
            <!-- Tab Headers -->
            <div class="flex border-b border-gray-100 bg-gray-50/50">
                <button type="button" id="btn-tab-pending" onclick="switchTab('tab-pending')" class="tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-teal-600 text-teal-600 bg-teal-50 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-inbox"></i> Pending Review
                    <?php if(!$is_teacher_view && count($grouped_pending)>0): ?>
                        <span class="bg-teal-600 text-white px-2 py-0.5 rounded-full text-[0.625rem]"><?= $pending_reports->num_rows ?></span>
                    <?php endif; ?>
                </button>
                <?php if(!$is_teacher_view): ?>
                <button type="button" id="btn-tab-folders" onclick="switchTab('tab-folders')" class="tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-folder-open"></i> Teacher Folders
                </button>
                <?php endif; ?>
                <button type="button" id="btn-tab-reviewed" onclick="switchTab('tab-reviewed')" class="tab-btn flex-1 py-4 text-xs font-black uppercase tracking-widest border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                    <i class="fas fa-history"></i> Review History
                </button>
            </div>

            <!-- TAB 1: PENDING REVIEW -->
            <div id="tab-pending" class="tab-content p-6">
                <?php if(empty($grouped_pending)): ?>
                    <div class="py-16 text-center">
                        <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 text-4xl mx-auto mb-4">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3 class="text-lg font-black text-gray-900 mb-1">All Caught Up!</h3>
                        <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">No pending reports to review</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-8">
                        <?php foreach($grouped_pending as $teacher => $data): ?>
                            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
                                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-teal-100 text-teal-600 flex items-center justify-center font-black">
                                            <?= strtoupper(substr($teacher, 0, 1)) ?>
                                        </div>
                                        <h3 class="font-black text-gray-900 text-sm uppercase tracking-widest"><?= htmlspecialchars($teacher) ?></h3>
                                    </div>
                                    <span class="text-[0.625rem] font-black text-teal-600 bg-teal-50 px-3 py-1 rounded-full border border-teal-100">
                                        <?= count($data['reports']) ?> Pending
                                    </span>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-white border-b border-gray-100">
                                            <tr>
                                                <th class="px-6 py-3 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Week</th>
                                                <th class="px-6 py-3 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest">Class</th>
                                                <th class="px-6 py-3 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <?php foreach($data['reports'] as $p): ?>
                                            <tr class="hover:bg-gray-50 transition-colors group">
                                                <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-teal-600">Wk <?= $p['week_number'] ?></span></td>
                                                <td class="px-6 py-4">
                                                    <div class="font-black text-gray-900"><?= htmlspecialchars($p['class_name']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                                    <div class="flex justify-end gap-2">
                                                        <a href="../teacher/print_weekly_report?id=<?= $p['id'] ?>&view=html" target="_blank" class="h-9 px-4 bg-gray-100 text-gray-600 rounded-xl flex items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest hover:bg-gray-200 transition">
                                                            <i class="fas fa-eye"></i> Preview
                                                        </a>
                                                        <button type="button" onclick="openReviewModal(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['class_name'])) ?>', <?= $p['week_number'] ?>)" class="h-9 px-4 bg-indigo-600 text-white rounded-xl flex items-center gap-2 text-[0.625rem] font-black uppercase tracking-widest hover:bg-indigo-700 transition shadow-lg shadow-indigo-100">
                                                            <i class="fas fa-clipboard-check"></i> Evaluate
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: TEACHER FOLDERS (Only visible in Main Dashboard) -->
            <?php if(!$is_teacher_view): ?>
            <div id="tab-folders" class="tab-content p-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php if($folders_query) while($t = $folders_query->fetch_assoc()): ?>
                        <a href="weekly_reports?teacher_id=<?= $t['teacher_id'] ?>" class="block p-5 bg-white border border-gray-200 rounded-2xl hover:border-teal-400 hover:shadow-lg transition-all group relative overflow-hidden">
                            <div class="absolute right-0 top-0 w-16 h-16 bg-gray-50 group-hover:bg-teal-50 rounded-bl-full -z-10 transition-colors"></div>
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 bg-gray-100 group-hover:bg-teal-100 text-gray-600 group-hover:text-teal-600 rounded-full flex items-center justify-center font-black text-lg transition-colors">
                                    <?= strtoupper(substr($t['teacher_name'], 0, 1)) ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="font-black text-gray-900 truncate"><?= htmlspecialchars($t['teacher_name']) ?></h3>
                                    <p class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest">Teacher Portfolio</p>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <?php if($t['pending_count'] > 0): ?>
                                    <span class="flex-1 bg-yellow-50 text-yellow-600 py-1.5 px-2 rounded-lg text-center text-[0.625rem] font-black uppercase tracking-widest border border-yellow-100">
                                        <?= $t['pending_count'] ?> Pending
                                    </span>
                                <?php endif; ?>
                                <span class="flex-1 bg-gray-50 text-gray-500 py-1.5 px-2 rounded-lg text-center text-[0.625rem] font-black uppercase tracking-widest border border-gray-100">
                                    <?= $t['reviewed_count'] ?> Reviewed
                                </span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAB 3: REVIEW HISTORY -->
            <div id="tab-reviewed" class="tab-content p-6 hidden">
                <?php if($reviewed_reports && $reviewed_reports->num_rows > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-gray-50/50 border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Teacher</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Status</th>
                                    <th class="px-6 py-4 text-[0.625rem] font-black text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php while($r = $reviewed_reports->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-black text-gray-900"><?= htmlspecialchars($r['teacher_name']) ?></div>
                                            <div class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest mt-1">Review: <?= date('M j, Y', strtotime($r['updated_at'])) ?></div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-black text-gray-900"><?= htmlspecialchars($r['class_name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap"><span class="font-black text-gray-600">Wk <?= $r['week_number'] ?></span></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if($r['status'] == 'approved'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-[0.625rem] font-black uppercase tracking-widest">
                                                    <i class="fas fa-check-circle"></i> Approved
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-600 rounded-lg text-[0.625rem] font-black uppercase tracking-widest">
                                                    <i class="fas fa-times-circle"></i> Rejected
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">
                                            <div class="flex justify-end gap-2">
                                                <a href="../teacher/print_weekly_report?id=<?= $r['id'] ?>&view=html" target="_blank" class="w-9 h-9 bg-gray-50 text-gray-500 rounded-xl flex items-center justify-center hover:bg-gray-200 hover:text-gray-900 transition" title="View Document">
                                                    <i class="fas fa-file-alt"></i>
                                                </a>
                                                <?php if (!empty($r['admin_id'])): ?>
                                                    <span class="inline-flex h-9 px-3 bg-blue-50 text-blue-600 border border-blue-100 rounded-xl items-center gap-1 text-[0.6rem] font-bold uppercase tracking-widest" title="Locked by Admin">
                                                        <i class="fas fa-lock"></i> Locked by Admin
                                                    </span>
                                                <?php else: ?>
                                                    <form method="POST" action="" onsubmit="return confirm('Revert status to pending? This will allow you to review it again.');">
                                                        <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                                                        <button type="submit" name="revert_report" class="w-9 h-9 bg-gray-50 text-gray-400 rounded-xl flex items-center justify-center hover:bg-yellow-50 hover:text-yellow-600 transition" title="Revert to Pending">
                                                            <i class="fas fa-rotate-left text-xs"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="py-12 text-center text-gray-400">
                        <i class="fas fa-history text-4xl mb-3 text-gray-200"></i>
                        <p class="font-bold text-sm uppercase tracking-widest">No review history found</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- REVIEW MODAL -->
    <div id="review-modal" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-[scale-in_0.2s_ease-out]">
            <form method="POST" action="weekly_reports<?= $is_teacher_view ? '?teacher_id='.$teacher_id_get : '' ?>">
                <div class="p-6 md:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 id="modal-title" class="text-xl font-black text-gray-900 tracking-tight">Evaluate Report</h2>
                            <p id="modal-week" class="text-[0.625rem] font-bold text-gray-400 uppercase tracking-widest mt-1">Week Info</p>
                        </div>
                        <button type="button" onclick="closeReviewModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition"><i class="fas fa-times"></i></button>
                    </div>
                    
                    <input type="hidden" name="report_id" id="modal-report-id" value="">
                    
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-3">Evaluation Decision</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="status" value="approved" required class="peer sr-only">
                                    <div class="p-4 rounded-2xl border-2 border-gray-200 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 hover:bg-gray-50 transition-all text-center">
                                        <i class="fas fa-check-circle text-2xl text-emerald-500 mb-2 peer-checked:scale-110 transition-transform"></i>
                                        <div class="font-black text-sm text-gray-900">Approve</div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-emerald-500 transition-opacity"><i class="fas fa-check"></i></div>
                                </label>
                                <label class="relative cursor-pointer group">
                                    <input type="radio" name="status" value="rejected" required class="peer sr-only">
                                    <div class="p-4 rounded-2xl border-2 border-gray-200 peer-checked:border-red-500 peer-checked:bg-red-50 hover:bg-gray-50 transition-all text-center">
                                        <i class="fas fa-times-circle text-2xl text-red-500 mb-2 peer-checked:scale-110 transition-transform"></i>
                                        <div class="font-black text-sm text-gray-900">Needs Revision</div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-red-500 transition-opacity"><i class="fas fa-check"></i></div>
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[0.625rem] font-black text-gray-400 uppercase tracking-widest mb-2">Supervisor Feedback & Remarks</label>
                            <textarea name="comments" rows="4" class="w-full p-4 bg-gray-50 border border-gray-200 rounded-2xl focus:bg-white focus:border-teal-500 outline-none transition-all resize-none text-sm font-medium" placeholder="Provide constructive feedback..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="p-6 md:p-8 bg-gray-50 border-t border-gray-100 flex justify-end gap-3 rounded-b-3xl">
                    <button type="button" onclick="closeReviewModal()" class="px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest text-gray-500 hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" name="review_report" class="px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest bg-gray-900 text-white hover:bg-black transition shadow-lg shadow-gray-200">Submit Evaluation</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
