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

// Safe Migration: Ensure lesson_plans has all modern columns (MySQL 5.7+ compatible)
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    'status' => "VARCHAR(20) DEFAULT 'pending' AFTER objectives",
    'attachment' => "VARCHAR(255) NULL AFTER status",
    'supervisor_comments' => "TEXT NULL AFTER status",
    'supervisor_id' => "INT NULL AFTER supervisor_comments",
    'references' => "TEXT NULL",
    'tlm' => "TEXT NULL"
];
foreach ($cols_to_check as $col => $def) {
    $exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = '$col'")->fetch_row()[0];
    if (!$exists) {
        $conn->query("ALTER TABLE lesson_plans ADD COLUMN `$col` $def");
    } elseif ($col === 'status') {
        // Ensure status is at least VARCHAR(20) to support 'draft'
        $conn->query("ALTER TABLE lesson_plans MODIFY COLUMN `$col` VARCHAR(20) DEFAULT 'pending'");
    }
}

$teacher_id_get = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
$tid_query = $teacher_id_get > 0 ? "?teacher_id=" . $teacher_id_get : "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_plan'])) {
    $plan_id = intval($_POST['plan_id']);
    $status = $_POST['status']; // 'approved' or 'rejected'
    $comments = trim($_POST['comments']);
    
    // Server-side check: Locked by Admin
    $check_admin = $conn->query("SELECT admin_id FROM lesson_plans WHERE id = $plan_id")->fetch_row()[0] ?? null;
    if ($check_admin !== null) {
        redirect("lesson_plans{$tid_query}", 'error', "This lesson plan is locked by Admin and cannot be modified.");
    }
    
    if (in_array($status, ['approved', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE lesson_plans SET status = ?, supervisor_comments = ?, supervisor_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $status, $comments, $uid, $plan_id);
        if ($stmt->execute()) {
            redirect("lesson_plans{$tid_query}", 'success', "Lesson plan marked as " . strtoupper($status) . ".");
        } else {
            set_flash('error', "Failed to update lesson plan.");
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revert_plan'])) {
    $plan_id = intval($_POST['plan_id']);
    
    // Server-side check: Locked by Admin
    $check_admin = $conn->query("SELECT admin_id FROM lesson_plans WHERE id = $plan_id")->fetch_row()[0] ?? null;
    if ($check_admin !== null) {
        redirect("lesson_plans{$tid_query}", 'error', "This lesson plan is locked by Admin and cannot be modified.");
    }
    
    $stmt = $conn->prepare("UPDATE lesson_plans SET status = 'pending' WHERE id = ?");
    $stmt->bind_param("i", $plan_id);
    if ($stmt->execute()) {
        redirect("lesson_plans{$tid_query}", 'success', "Lesson plan status reverted to PENDING.");
    } else {
        set_flash('error', "Failed to revert lesson plan status.");
    }
}

// Filters
$week_f = intval($_GET['week'] ?? 0);
$date_f = $_GET['date'] ?? '';
$class_f = $_GET['class'] ?? '';
$search_f = trim($_GET['search'] ?? '');

$filter_where = "";
if ($week_f) $filter_where .= " AND l.week_number = $week_f";
if ($date_f) $filter_where .= " AND l.week_ending = '" . $conn->real_escape_string($date_f) . "'";
if ($class_f) $filter_where .= " AND l.class_name = '" . $conn->real_escape_string($class_f) . "'";
if ($search_f) {
    $s = $conn->real_escape_string($search_f);
    $filter_where .= " AND (l.topic LIKE '%$s%' OR s.name LIKE '%$s%' OR u.username LIKE '%$s%' OR sp.full_name LIKE '%$s%' OR l.class_name LIKE '%$s%')";
}

$total_weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));
$classes_res = $conn->query("SELECT name as class_name FROM classes ORDER BY name ASC");
// Fallback if classes table is empty but we still want to show something
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
    
    // 1. Subject Teacher specific classes
    $t_subs = $conn->query("SELECT ta.class_name, s.name as subject_name FROM teacher_allocations ta JOIN subjects s ON ta.subject_id = s.id WHERE ta.teacher_id = $teacher_id_get AND ta.year = '$current_academic_year' AND ta.is_subject_teacher = 1");
    if ($t_subs) {
        while($r = $t_subs->fetch_assoc()) {
            $expected_list[] = trim($r['class_name']) . ' - ' . trim($r['subject_name']);
        }
    }
    
    // 2. Class Teacher fallback
    $ct_res = $conn->query("SELECT class_name FROM teacher_allocations WHERE teacher_id = $teacher_id_get AND year = '$current_academic_year' AND is_class_teacher = 1");
    if ($ct_res) {
        while($r = $ct_res->fetch_assoc()) {
            $cn = $conn->real_escape_string($r['class_name']);
            $all_subs = $conn->query("SELECT s.name as subject_name FROM class_subjects cs JOIN subjects s ON cs.subject_id = s.id WHERE cs.class_name = '$cn'");
            $taken_subs = [];
            $taken_res = $conn->query("SELECT s.name as subject_name FROM teacher_allocations ta JOIN subjects s ON ta.subject_id = s.id WHERE ta.class_name = '$cn' AND ta.year = '$current_academic_year' AND ta.is_subject_teacher = 1");
            if ($taken_res) { while($tr = $taken_res->fetch_assoc()) { $taken_subs[] = trim($tr['subject_name']); } }
            
            if ($all_subs) {
                while($sr = $all_subs->fetch_assoc()) {
                    if (!in_array(trim($sr['subject_name']), $taken_subs)) {
                        $expected_list[] = trim($r['class_name']) . ' - ' . trim($sr['subject_name']);
                    }
                }
            }
        }
    }
    $expected_list = array_unique($expected_list);
    
    // 3. Submitted Actuals
    $actual_list = [];
    $w = $week_f ?: $current_week;
    $act_res = $conn->query("
        SELECT l.class_name, s.name as subject_name 
        FROM lesson_plans l 
        JOIN subjects s ON l.subject_id = s.id 
        JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_id = cs.subject_id
        WHERE l.teacher_id = $teacher_id_get AND l.week_number = $w AND l.status != 'draft'
    ");
    if ($act_res) {
        while($r = $act_res->fetch_assoc()) {
            $actual_list[] = trim($r['class_name']) . ' - ' . trim($r['subject_name'] ?? '');
        }
    }
    $owing_list = array_diff($expected_list, $actual_list);

} else {
    // ---- EXPECTATIONS LOGIC ----
    $expectations = [];
    
    // 1. Get Subject Teacher assignments (must JOIN subjects to ensure subject actually exists, matching Teacher View)
    $sub_res = $conn->query("
        SELECT ta.teacher_id, COUNT(ta.id) as c 
        FROM teacher_allocations ta 
        JOIN subjects s ON ta.subject_id = s.id 
        WHERE ta.year = '$current_academic_year' AND ta.is_subject_teacher = 1 
        GROUP BY ta.teacher_id
    ");
    if ($sub_res) { while($row = $sub_res->fetch_assoc()) { $expectations[$row['teacher_id']] = (int)$row['c']; } }
    
    // 2. Class Subject Counts
    $class_total_subs = [];
    $cs_res = $conn->query("
        SELECT cs.class_name, COUNT(DISTINCT cs.subject_id) as total_subs 
        FROM class_subjects cs 
        JOIN subjects s ON cs.subject_id = s.id 
        GROUP BY cs.class_name
    ");
    if ($cs_res) { while($row = $cs_res->fetch_assoc()) { $class_total_subs[strtoupper(trim($row['class_name']))] = (int)$row['total_subs']; } }
    
    // 3. Subject Teacher assignments per class (ONLY count subjects that are actually mapped to the class!)
    $class_taken_subs = [];
    $cs_taken_res = $conn->query("
        SELECT ta.class_name, COUNT(DISTINCT ta.subject_id) as taken_subs 
        FROM teacher_allocations ta 
        JOIN class_subjects cs ON ta.class_name = cs.class_name AND ta.subject_id = cs.subject_id 
        JOIN subjects s ON ta.subject_id = s.id
        WHERE ta.year = '$current_academic_year' AND ta.is_subject_teacher = 1 
        GROUP BY ta.class_name
    ");
    if ($cs_taken_res) { while($row = $cs_taken_res->fetch_assoc()) { $class_taken_subs[strtoupper(trim($row['class_name']))] = (int)$row['taken_subs']; } }
    
    // 4. Class Teacher Expectations
    $ct_res = $conn->query("SELECT teacher_id, class_name FROM teacher_allocations WHERE year = '$current_academic_year' AND is_class_teacher = 1");
    if ($ct_res) {
        while($row = $ct_res->fetch_assoc()) {
            $tid = $row['teacher_id'];
            $cn = strtoupper(trim($row['class_name']));
            $total = $class_total_subs[$cn] ?? 0;
            $taken = $class_taken_subs[$cn] ?? 0;
            $expected = max(0, $total - $taken);
            if (!isset($expectations[$tid])) $expectations[$tid] = 0;
            $expectations[$tid] += $expected;
        }
    }
    
    // 5. This Week's Actuals (from lesson_plans)
    $actuals = [];
    $act_res = $conn->query("
        SELECT l.teacher_id, COUNT(l.id) as c 
        FROM lesson_plans l 
        JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_id = cs.subject_id
        WHERE l.week_number = $current_week 
        GROUP BY l.teacher_id
    ");
    if ($act_res) { while($row = $act_res->fetch_assoc()) { $actuals[$row['teacher_id']] = (int)$row['c']; } }

    // ---- MAIN DASHBOARD QUERIES ----
    $total_facilitators = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'facilitator'")->fetch_row()[0];
    
    // This Week expectations needed for cumulative calculation
    $total_expected_this_week = array_sum($expectations);
    
    // Overall Rate (Cumulative Submitted up to current week / Cumulative Expected)
    $cumulative_expected = $total_expected_this_week * $current_week;
    $cumulative_submitted = $conn->query("
        SELECT COUNT(*) 
        FROM lesson_plans l 
        JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_id = cs.subject_id
        WHERE l.week_number > 0 AND l.week_number <= $current_week
    ")->fetch_row()[0] ?? 0;
    $overall_rate = $cumulative_expected > 0 ? round(($cumulative_submitted / $cumulative_expected) * 100) : 0;
    
    // This Week Rate (Total Actuals / Total Expectations)
    $total_expected_this_week = array_sum($expectations);
    $total_actual_this_week = array_sum($actuals);
    $weekly_rate = $total_expected_this_week > 0 ? round(($total_actual_this_week / $total_expected_this_week) * 100) : 0;
    
    // Fetch Facilitator Folders (Only users with role = 'facilitator')
    $folders_query = $conn->query("
        SELECT u.id as teacher_id, COALESCE(sp.full_name, u.username) as teacher_name,
               SUM(CASE WHEN l.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN l.status IN ('approved', 'rejected') THEN 1 ELSE 0 END) as reviewed_count
        FROM users u
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        LEFT JOIN lesson_plans l ON u.id = l.teacher_id $filter_where
        WHERE u.role = 'facilitator'
        GROUP BY u.id, teacher_name
        ORDER BY teacher_name ASC
    ");
}

// Fetch pending and reviewed plans (used in both views, but filtered by teacher in Teacher View)
$pending_plans = $conn->query("
    SELECT l.*, s.name as subject_name, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM lesson_plans l 
    LEFT JOIN subjects s ON l.subject_id = s.id 
    LEFT JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_id = cs.subject_id
    LEFT JOIN teacher_allocations ta ON l.teacher_id = ta.teacher_id AND l.class_name = ta.class_name AND (ta.is_class_teacher = 1 OR ta.subject_id = l.subject_id)
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE l.status = 'pending' $filter_where
    ORDER BY teacher_name ASC, l.created_at ASC
");

$grouped_pending = [];
if ($pending_plans && $pending_plans->num_rows > 0) {
    while ($p = $pending_plans->fetch_assoc()) {
        $teacher = $p['teacher_name'];
        if (!isset($grouped_pending[$teacher])) {
            $grouped_pending[$teacher] = [
                'teacher_id' => $p['teacher_id'],
                'plans' => []
            ];
        }
        $grouped_pending[$teacher]['plans'][] = $p;
    }
}

$reviewed_plans = $conn->query("
    SELECT l.*, s.name as subject_name, u.username, COALESCE(sp.full_name, u.username) as teacher_name
    FROM lesson_plans l 
    LEFT JOIN subjects s ON l.subject_id = s.id 
    LEFT JOIN class_subjects cs ON l.class_name = cs.class_name AND l.subject_id = cs.subject_id
    LEFT JOIN teacher_allocations ta ON l.teacher_id = ta.teacher_id AND l.class_name = ta.class_name AND (ta.is_class_teacher = 1 OR ta.subject_id = l.subject_id)
    JOIN users u ON l.teacher_id = u.id 
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE l.status IN ('approved', 'rejected') $filter_where
    ORDER BY l.updated_at DESC LIMIT 100
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Lesson Plans - Supervisor Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-8 pt-20 md:pt-24 relative">
        <div class="flex justify-between items-end mb-6">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="fas fa-file-signature text-green-500"></i> 
                <?php if($is_teacher_view): ?>
                    <?= htmlspecialchars($active_teacher_name) ?>'s Lesson Plans
                <?php else: ?>
                    Supervisor's Approvals
                <?php endif; ?>
            </h1>
            <?php if($is_teacher_view): ?>
                <a href="lesson_plans" class="bg-gray-800 text-white px-5 py-2.5 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-gray-900 transition flex items-center gap-2 shadow-lg shadow-gray-200">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            <?php endif; ?>
        </div>

        <?php if($is_teacher_view): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded-2xl mb-8 shadow-sm">
            <h2 class="text-red-800 font-bold text-lg mb-3 flex items-center gap-2">
                <i class="fas fa-exclamation-triangle"></i> Missing Submissions (Week <?= $week_f ?: $current_week ?>)
            </h2>
            <?php if(empty($owing_list)): ?>
                <p class="text-green-700 font-bold bg-green-100/50 p-3 rounded-lg flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> This teacher has submitted all expected lesson plans for this week.
                </p>
            <?php else: ?>
                <p class="text-red-600 mb-3 text-sm">The following Expected Classes/Subjects have not been submitted:</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach($owing_list as $owing): ?>
                        <span class="bg-white border border-red-200 text-red-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm">
                            <i class="fas fa-times text-red-400 mr-1"></i> <?= htmlspecialchars($owing) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Global Flash Messages handled by top_nav.php -->

        <!-- Filter Bar -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 mb-8">
            <form method="GET" class="flex flex-wrap items-center gap-4">
                <?php if($is_teacher_view): ?>
                    <input type="hidden" name="teacher_id" value="<?= $teacher_id_get ?>">
                <?php endif; ?>
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-300"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search_f) ?>" placeholder="Search by topic, class..." class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 outline-none transition-all text-sm font-bold">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Class</label>
                    <select name="class" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[120px]">
                        <option value="">All Classes</option>
                        <?php while($c = $classes_res->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($c['class_name']) ?>" <?= $class_f == $c['class_name'] ? 'selected' : '' ?>><?= htmlspecialchars($c['class_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-[0.625rem] font-black text-gray-400 uppercase tracking-widest whitespace-nowrap">Week</label>
                    <select name="week" class="px-4 py-3 bg-gray-50 border border-gray-100 rounded-xl focus:bg-white focus:ring-4 focus:ring-green-500/10 outline-none transition-all text-sm font-bold appearance-none min-w-[100px]">
                        <option value="0">All Weeks</option>
                        <?php for($i=1; $i<=$total_weeks; $i++): ?>
                            <option value="<?= $i ?>" <?= $week_f == $i ? 'selected' : '' ?>>Week <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-green-700 transition shadow-lg shadow-green-100">
                        Filter
                    </button>
                    <?php if($week_f || $date_f || $search_f || $class_f): ?>
                        <a href="lesson_plans<?= $is_teacher_view ? '?teacher_id='.$teacher_id_get : '' ?>" class="bg-gray-100 text-gray-500 px-4 py-3 rounded-xl font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition flex items-center justify-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if(!$is_teacher_view): ?>
        <!-- MAIN DASHBOARD: STATS & FOLDERS -->
        <div class="mb-10">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex items-center gap-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 text-gray-50 opacity-50">
                        <i class="fas fa-chart-line text-[8rem]"></i>
                    </div>
                    <div class="w-14 h-14 rounded-full bg-indigo-50 text-indigo-500 flex items-center justify-center text-2xl relative z-10">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">Overall Active Rate</div>
                        <div class="text-3xl font-extrabold text-gray-900"><?= $overall_rate ?>%</div>
                        <div class="text-xs font-bold text-gray-500"><?= $cumulative_submitted ?> of <?= $cumulative_expected ?> total expected plans submitted</div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm flex items-center gap-5 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 text-gray-50 opacity-50">
                        <i class="fas fa-calendar-week text-[8rem]"></i>
                    </div>
                    <div class="w-14 h-14 rounded-full bg-green-50 text-green-500 flex items-center justify-center text-2xl relative z-10">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="relative z-10">
                        <div class="text-sm font-black text-gray-400 uppercase tracking-widest">This Week (Week <?= $current_week ?>)</div>
                        <div class="text-3xl font-extrabold text-gray-900"><?= $weekly_rate ?>%</div>
                        <div class="text-xs font-bold text-gray-500"><?= $total_actual_this_week ?> submitted / <?= $total_expected_this_week ?> expected school-wide</div>
                    </div>
                </div>
            </div>

            <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-folder-open text-indigo-500"></i> Facilitator Folders</h2>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php if($folders_query && $folders_query->num_rows > 0): while($tf = $folders_query->fetch_assoc()): 
                    $t_id = $tf['teacher_id'];
                    $t_exp = $expectations[$t_id] ?? 0;
                    $t_act = $actuals[$t_id] ?? 0;
                ?>
                    <a href="?teacher_id=<?= $t_id ?>" class="block bg-white border border-gray-200 rounded-2xl p-5 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group relative">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                                <i class="fas fa-folder"></i>
                            </div>
                            <?php if($tf['pending_count'] > 0): ?>
                                <span class="bg-orange-100 text-orange-700 font-black text-[0.625rem] px-2 py-1 rounded-full uppercase tracking-widest animate-pulse">
                                    <?= $tf['pending_count'] ?> Pending
                                </span>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-500 font-black text-[0.625rem] px-2 py-1 rounded-full uppercase tracking-widest">
                                    Queue Clear
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-bold text-gray-900 text-lg leading-tight mb-3"><?= htmlspecialchars($tf['teacher_name']) ?></h3>
                        
                        <!-- Week Status -->
                        <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[0.625rem] font-black text-gray-500 uppercase tracking-widest">Week <?= $current_week ?> Submissions</span>
                                <span class="text-xs font-extrabold <?= $t_act >= $t_exp && $t_exp > 0 ? 'text-green-600' : 'text-gray-900' ?>"><?= $t_act ?> / <?= $t_exp ?></span>
                            </div>
                            <?php if($t_exp > 0): ?>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full <?= $t_act >= $t_exp ? 'bg-green-500' : 'bg-indigo-500' ?>" style="width: <?= min(100, ($t_act / $t_exp) * 100) ?>%"></div>
                                </div>
                            <?php else: ?>
                                <div class="text-[0.625rem] text-gray-400 font-medium italic mt-1">No expected plans</div>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; else: ?>
                    <div class="col-span-full bg-white p-12 text-center rounded-2xl border border-dashed border-gray-300 text-gray-400">
                        <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                        <p class="font-medium">No facilitators found in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if($is_teacher_view || (!empty($grouped_pending))): ?>
        <div class="space-y-8">
            <!-- Pending Queue (Always shown in Teacher View, or on Main if we somehow skip the condition, but handled above) -->
            <?php if($is_teacher_view): ?>
            <div>
                <h2 class="font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="fas fa-clock text-yellow-500"></i> Pending Review Queue</h2>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-gray-50 font-bold text-gray-600 border-b border-gray-200">
                                <tr>
                                    <th class="py-3 px-4 whitespace-nowrap">Class & Subject</th>
                                    <th class="py-3 px-4 min-w-[200px]">Topic</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Week</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (!empty($grouped_pending)): foreach ($grouped_pending as $teacher_name => $t_data): ?>
                                    <!-- Lesson Plan Rows -->
                                    <?php foreach ($t_data['plans'] as $p): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="py-4 px-4 align-top">
                                                <div class="font-bold text-gray-800"><?= htmlspecialchars($p['class_name']) ?></div>
                                                <div class="text-xs text-gray-500 font-medium"><?= htmlspecialchars($p['subject_name']) ?></div>
                                            </td>
                                            <td class="py-4 px-4 align-top">
                                                <div class="font-bold text-gray-900 text-base mb-1 leading-tight flex flex-wrap items-center gap-2">
                                                    <span><?= htmlspecialchars($p['topic']) ?></span>
                                                    <?php if(!empty($p['attachment'])): ?>
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[0.55rem] font-black uppercase tracking-wider"><i class="fas fa-paperclip"></i> Direct Upload</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center gap-3 text-xs">
                                                    <?php if(!empty($p['attachment'])): ?>
                                                        <a href="<?= BASE_URL ?>uploads/lesson_attachments/<?= htmlspecialchars($p['attachment']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                                                            <i class="fas fa-paperclip"></i> View File
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>&view=html" target="_blank" class="text-indigo-600 hover:text-indigo-800 font-bold flex items-center gap-1">
                                                            <i class="fas fa-eye"></i> View Note
                                                        </a>
                                                        <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $p['id'] ?>" target="_blank" class="text-red-600 hover:text-red-800 font-bold flex items-center gap-1">
                                                            <i class="fas fa-file-pdf"></i> PDF
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 align-top">
                                                <span class="inline-block bg-gray-100 text-gray-700 text-xs font-bold px-2 py-1 rounded border border-gray-200">
                                                    Week <?= $p['week_number'] ?>
                                                </span>
                                                <div class="text-[0.625rem] text-gray-400 mt-1 uppercase font-bold tracking-widest"><?= htmlspecialchars($p['duration'] ?? '-') ?></div>
                                            </td>
                                            <td class="py-4 px-4 align-top min-w-[250px]">
                                                <form method="POST" class="flex flex-col gap-2">
                                                    <input type="hidden" name="review_plan" value="1">
                                                    <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                                    <input type="text" name="comments" placeholder="Add remark..." class="w-full px-3 py-1.5 border border-gray-300 rounded text-xs focus:border-green-500 focus:ring-1 focus:ring-green-500 outline-none">
                                                    <div class="flex gap-2">
                                                        <button type="submit" name="status" value="approved" class="flex-1 bg-green-600 text-white font-bold text-xs py-1.5 rounded hover:bg-green-700 transition"><i class="fas fa-check mr-1"></i> Approve</button>
                                                        <button type="submit" name="status" value="rejected" class="flex-1 bg-red-600 text-white font-bold text-xs py-1.5 rounded hover:bg-red-700 transition"><i class="fas fa-times mr-1"></i> Reject</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; else: ?>
                                    <tr>
                                        <td colspan="4" class="py-12 text-center bg-gray-50/50">
                                            <i class="fas fa-check-double text-4xl mb-3 text-gray-300 block"></i>
                                            <span class="font-bold text-gray-400">All caught up! No pending lesson plans.</span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent History -->
            <div>
                <h2 class="font-bold text-gray-800 mb-4 border-b border-gray-200 pb-2">Recently Reviewed <?= $is_teacher_view ? "for this Teacher" : "Across All Teachers" ?></h2>
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead class="bg-gray-50 font-bold text-gray-600 border-b border-gray-200">
                                <tr>
                                    <?php if(!$is_teacher_view): ?>
                                        <th class="py-3 px-4 whitespace-nowrap">Teacher</th>
                                    <?php endif; ?>
                                    <th class="py-3 px-4 whitespace-nowrap">Class & Subject</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Topic</th>
                                    <th class="py-3 px-4 text-center whitespace-nowrap">Status</th>
                                    <th class="py-3 px-4 whitespace-nowrap">Your Remark</th>
                                    <th class="py-3 px-4 text-right whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if($reviewed_plans && $reviewed_plans->num_rows > 0): while($rp = $reviewed_plans->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <?php if(!$is_teacher_view): ?>
                                            <td class="py-3 px-4 font-semibold text-gray-800 whitespace-nowrap">
                                                <a href="?teacher_id=<?= $rp['teacher_id'] ?>" class="text-indigo-600 hover:underline">
                                                    <?= htmlspecialchars($rp['username']) ?>
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                        <td class="py-3 px-4 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($rp['class_name']) ?> | <?= htmlspecialchars($rp['subject_name']) ?></td>
                                        <td class="py-3 px-4 font-medium text-gray-800 min-w-[200px]">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span><?= htmlspecialchars($rp['topic']) ?></span>
                                                <?php if(!empty($rp['attachment'])): ?>
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-600 rounded text-[0.55rem] font-black uppercase tracking-wider"><i class="fas fa-paperclip"></i> Direct Upload</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-center whitespace-nowrap">
                                            <?php if($rp['status'] === 'approved'): ?>
                                                <span class="text-xs font-bold text-green-700 bg-green-100 px-2 py-1 rounded">Approved</span>
                                            <?php else: ?>
                                                <span class="text-xs font-bold text-red-700 bg-red-100 px-2 py-1 rounded">Rejected</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4 text-gray-500 italic max-w-xs truncate" title="<?= htmlspecialchars($rp['supervisor_comments']) ?>"><?= htmlspecialchars($rp['supervisor_comments']) ?></td>
                                        <td class="py-3 px-4 text-right whitespace-nowrap">
                                            <div class="flex items-center gap-4 justify-end">
                                                 <?php if (!empty($rp['admin_id'])): ?>
                                                     <span class="text-[0.6rem] font-bold uppercase tracking-widest text-blue-600 bg-blue-50 px-2 py-1 rounded" title="Locked by Admin">
                                                         <i class="fas fa-lock"></i> Locked
                                                     </span>
                                                 <?php else: ?>
                                                     <form method="POST" onsubmit="return confirm('Revert this plan back to PENDING queue?');" class="inline">
                                                         <input type="hidden" name="plan_id" value="<?= $rp['id'] ?>">
                                                         <button type="submit" name="revert_plan" class="text-[0.625rem] font-black uppercase tracking-widest text-gray-400 hover:text-orange-600 transition-colors flex items-center gap-1">
                                                             <i class="fas fa-rotate-left"></i> Revert
                                                         </button>
                                                     </form>
                                                 <?php endif; ?>
                                                 <?php if(!empty($rp['attachment'])): ?>
                                                     <a href="<?= BASE_URL ?>uploads/lesson_attachments/<?= htmlspecialchars($rp['attachment']) ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-bold text-xs flex items-center gap-1">
                                                         <i class="fas fa-paperclip"></i> View File
                                                     </a>
                                                 <?php else: ?>
                                                     <a href="<?= BASE_URL ?>pages/teacher/print_lesson_plan?id=<?= $rp['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-900 font-bold text-xs flex items-center gap-1">
                                                         <i class="fas fa-file-pdf"></i> View / PDF
                                                     </a>
                                                 <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                    <tr>
                                        <td colspan="<?= $is_teacher_view ? 5 : 6 ?>" class="py-12 text-center text-gray-300 font-bold text-xs uppercase tracking-widest">No recently reviewed plans</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>
