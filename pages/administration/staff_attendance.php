<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

// Allow both Admins and Supervisors to view daily attendance
if (!is_logged_in() || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor')) {
    header('Location: ../../login');
    exit;
}

$school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Safe Migration: Ensure staff_attendance has modern columns
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$cols_to_check = [
    'device_info' => "VARCHAR(255) NULL AFTER longitude"
];
foreach ($cols_to_check as $col => $def) {
    $exists = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$db_name' AND TABLE_NAME = 'staff_attendance' AND COLUMN_NAME = '$col'")->fetch_row()[0];
    if (!$exists) {
        $conn->query("ALTER TABLE staff_attendance ADD COLUMN `$col` $def");
    }
}

// Deletion Handler (Admin Protocol)
// Attendance Action Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor') {
        $_SESSION['error'] = "Institutional Violation: Unauthorized action attempt detected.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'delete_attendance') {
            $log_id = intval($_POST['log_id']);
            $del_stmt = $conn->prepare("DELETE FROM staff_attendance WHERE id = ?");
            $del_stmt->bind_param("i", $log_id);
            $del_stmt->execute();
            log_activity($conn, 'Security Audit', "Personnel attendance record #$log_id purged from manifest.", $_SESSION['user_id']);
            $_SESSION['success'] = "Resource purged successfully.";
        }
        
        if ($action === 'manual_clockin') {
            $target_user_id = intval($_POST['user_id']);
            $manual_date = $_POST['check_in_date'];
            $manual_time = $_POST['check_in_time'];
            $combined_time = $manual_date . ' ' . $manual_time . ':00';
            
            // Security Protocol: Prevent duplicate records for the same day
            $date_check = $conn->query("SELECT id, check_out_time FROM staff_attendance WHERE user_id = $target_user_id AND DATE(check_in_time) = '$manual_date' LIMIT 1");
            
            if ($date_check->num_rows > 0) {
                $existing = $date_check->fetch_assoc();
                if ($existing['check_out_time'] === null) {
                    $_SESSION['error'] = "Personnel already has an ACTIVE clock-in for this date. Please clock them out instead.";
                } else {
                    $_SESSION['error'] = "Personnel already has a COMPLETED attendance record for this date. Duplicate entries are prohibited.";
                }
            } else {
                // Also check for unclosed shifts from OTHER days to maintain data integrity
                $active_check_stmt = $conn->prepare("SELECT id, check_in_time FROM staff_attendance WHERE user_id = ? AND check_out_time IS NULL LIMIT 1");
                $active_check_stmt->bind_param("i", $target_user_id);
                $active_check_stmt->execute();
                $active_check = $active_check_stmt->get_result();
                if ($active_check->num_rows > 0) {
                    $active_row = $active_check->fetch_assoc();
                    $active_date = date('Y-m-d', strtotime($active_row['check_in_time']));
                    $_SESSION['error'] = "Personnel has an unclosed shift from $active_date. Please close that record before creating a new one.";
                } else {
                $school_lat_val = getSystemSetting($conn, 'attendance_lat', '5.5786875');
                $school_lng_val = getSystemSetting($conn, 'attendance_lng', '-0.2911875');
                
                $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude, device_info) VALUES (?, ?, ?, ?, 'Manual Entry by Supervisor')");
                $stmt->bind_param("isss", $target_user_id, $combined_time, $school_lat_val, $school_lng_val);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Manual clock-in registered successfully.";
                    log_activity($conn, 'Attendance', "Manual clock-in for user #$target_user_id at $combined_time.", $_SESSION['user_id']);
                    $selected_date = $manual_date;
                } else {
                    $_SESSION['error'] = "Resource Error: Manual override rejected.";
                }
            }
        }
    }

    if ($action === 'manual_clockout') {
            $log_id = intval($_POST['log_id']);
            $stmt = $conn->prepare("UPDATE staff_attendance SET check_out_time = NOW() WHERE id = ?");
            $stmt->bind_param("i", $log_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Personnel clocked out successfully.";
                log_activity($conn, 'Attendance', "Manual clock-out for record #$log_id.", $_SESSION['user_id']);
            } else {
                $_SESSION['error'] = "Failed to update clock-out time.";
            }
        }

        if ($action === 'edit_attendance') {
            $log_id = intval($_POST['log_id']);
            $in_date = $_POST['check_in_date'];
            $in_time = $_POST['check_in_time'];
            $combined_in = $in_date . ' ' . $in_time . ':00';
            
            $clear_out = isset($_POST['clear_check_out']);
            $out_date = $_POST['check_out_date'] ?? '';
            $out_time = $_POST['check_out_time'] ?? '';
            
            if ($clear_out || empty($out_date) || empty($out_time)) {
                $stmt = $conn->prepare("UPDATE staff_attendance SET check_in_time = ?, check_out_time = NULL WHERE id = ?");
                $stmt->bind_param("si", $combined_in, $log_id);
            } else {
                $combined_out = $out_date . ' ' . $out_time . ':00';
                $stmt = $conn->prepare("UPDATE staff_attendance SET check_in_time = ?, check_out_time = ? WHERE id = ?");
                $stmt->bind_param("ssi", $combined_in, $combined_out, $log_id);
            }
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Personnel attendance record updated successfully.";
                log_activity($conn, 'Attendance', "Manual update of attendance record #$log_id. In: $combined_in.", $_SESSION['user_id']);
            } else {
                $_SESSION['error'] = "Failed to update attendance record.";
            }
            $stmt->close();
        }
    }
    
    // AJAX Interceptor for client-side API requests
    if (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        if (isset($_SESSION['error'])) {
            $err = $_SESSION['error'];
            unset($_SESSION['error']);
            echo json_encode(['status' => 'error', 'message' => $err]);
        } else {
            $succ = $_SESSION['success'] ?? 'Action completed successfully.';
            unset($_SESSION['success']);
            echo json_encode(['status' => 'success', 'message' => $succ]);
        }
        exit;
    }
    
    header("Location: staff_attendance.php?date=$selected_date");
    exit;
}

// Geofence Calibration (Pulled from System Settings)
$school_lat = floatval(getSystemSetting($conn, 'attendance_lat', '5.5786875'));
$school_lng = floatval(getSystemSetting($conn, 'attendance_lng', '-0.2911875'));
$allowed_radius = intval(getSystemSetting($conn, 'attendance_radius', '300'));

// Fetch Dynamic Punctuality Rules
$early_limit_setting = getSystemSetting($conn, 'attendance_early_limit', '06:30');
$ontime_limit_setting = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
$early_limit = date('H:i:s', strtotime($early_limit_setting));
$ontime_limit = date('H:i:s', strtotime($ontime_limit_setting));

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

// Fetch Staff Attendance Logs
$logs_stmt = $conn->prepare("SELECT sa.*, u.username, u.role, sp.full_name, sp.job_title, sp.photo_path
        FROM staff_attendance sa
        JOIN users u ON sa.user_id = u.id
        LEFT JOIN staff_profiles sp ON sa.user_id = sp.user_id
        WHERE DATE(sa.check_in_time) = ?
        ORDER BY sa.check_in_time DESC");
$logs_stmt->bind_param("s", $selected_date);
$logs_stmt->execute();
$logs_res = $logs_stmt->get_result();

$attendance = [];
$stats = [
    'total' => 0, 'present' => 0, 'geofence_violations' => 0, 'early' => 0, 'on_time' => 0, 'late' => 0
];

if ($logs_res) {
    while ($row = $logs_res->fetch_assoc()) {
        $dist = getDistanceMeters($school_lat, $school_lng, $row['latitude'], $row['longitude']);
        $row['distance_m'] = round($dist);
        $row['geofence_status'] = ($dist <= $allowed_radius) ? 'Verified' : 'Perimeter Variance';
        
        $check_time = date('H:i:s', strtotime($row['check_in_time']));
        if ($check_time < $early_limit) { $row['punctuality'] = 'Early'; $stats['early']++; }
        elseif ($check_time < $ontime_limit) { $row['punctuality'] = 'On-Time'; $stats['on_time']++; }
        else { $row['punctuality'] = 'Late'; $stats['late']++; }
        
        $attendance[] = $row;
        $stats['present']++;
        if ($row['geofence_status'] === 'Perimeter Variance') $stats['geofence_violations']++;
    }
}

// Fetch Absent Staff List for Selected Date
$absent_stmt = $conn->prepare("
    SELECT u.id, u.username, sp.full_name, sp.job_title, sp.photo_path
    FROM users u
    LEFT JOIN staff_profiles sp ON u.id = sp.user_id
    WHERE u.role IN ('facilitator', 'supervisor', 'teacher')
      AND u.id NOT IN (
          SELECT DISTINCT sa.user_id 
          FROM staff_attendance sa 
          WHERE DATE(sa.check_in_time) = ?
      )
    ORDER BY sp.full_name ASC, u.username ASC
");
$absent_stmt->bind_param("s", $selected_date);
$absent_stmt->execute();
$absent_res = $absent_stmt->get_result();
$absent_staff = [];
while ($row = $absent_res->fetch_assoc()) {
    $absent_staff[] = $row;
}

$total_staff = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('facilitator', 'supervisor', 'teacher')")->fetch_row()[0];
$stats['total'] = $total_staff;
$stats['absent'] = count($absent_staff);

$selected_tab = $_GET['tab'] ?? 'present';

// Fetch All Staff for Manual Entry
$all_staff_res = $conn->query("SELECT u.id, u.username, sp.full_name FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.role IN ('facilitator', 'supervisor', 'teacher', 'admin') ORDER BY sp.full_name ASC, u.username ASC");
$all_staff = [];
while($as = $all_staff_res->fetch_assoc()) $all_staff[] = $as;

// --- Performance Summary Logic ---
$active_view = $_GET['view'] ?? 'daily';
$sum_month = $_GET['s_month'] ?? '';
$sum_week = $_GET['s_week'] ?? ''; // Format: Week X (Start Date)

$sem_start_str = getSystemSetting($conn, 'semester_start_date', date('Y-m-01'));
$sem_end_str = getSystemSetting($conn, 'semester_end_date', date('Y-m-t'));
$sem_start = new DateTime($sem_start_str);
$weeks_total = intval(getSystemSetting($conn, 'weeks_per_semester', 12));

$range_start = null;
$range_end = null;

if ($sum_month) {
    $m_start = date('Y-m-01', strtotime($sum_month));
    $m_end = date('Y-m-t', strtotime($sum_month));
    
    // Constraint: Only count days within the semester boundary
    $range_start = ($m_start > $sem_start_str) ? $m_start : $sem_start_str;
    $range_end = ($m_end < $sem_end_str) ? $m_end : $sem_end_str;
} elseif ($sum_week) {
    // Parse "Week X (YYYY-MM-DD)"
    if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $sum_week, $matches)) {
        $w_start_str = $matches[1];
        $w_start = new DateTime($w_start_str);
        $w_end = clone $w_start;
        $w_end->modify('+6 days');
        $w_end_str = $w_end->format('Y-m-d');

        // Constraint: Only count days within the semester boundary
        $range_start = ($w_start_str > $sem_start_str) ? $w_start_str : $sem_start_str;
        $range_end = ($w_end_str < $sem_end_str) ? $w_end_str : $sem_end_str;
    }
}

// Default to current month if no filter and historical view
if ($active_view === 'historical' && !$range_start) {
    $range_start = date('Y-m-01');
    $range_end = date('Y-m-t');
}

$perf_summary = [];
if ($range_start && $range_end) {
    // 1. Calculate School Days in Range (Mon-Fri)
    $school_days = 0;
    $curr = new DateTime($range_start);
    $end = new DateTime($range_end);
    $holidays = [];
    $h_stmt = $conn->prepare("SELECT event_date FROM academic_calendar WHERE event_date BETWEEN ? AND ?");
    $h_stmt->bind_param("ss", $range_start, $range_end);
    $h_stmt->execute();
    $h_res = $h_stmt->get_result();
    while($h = $h_res->fetch_row()) $holidays[] = $h[0];

    $today_dt = new DateTime();
    while ($curr <= $end) {
        if ($curr > $today_dt) break; // Cap working days at today for accurate "to-date" reporting

        $w = $curr->format('N');
        if ($w <= 5 && !in_array($curr->format('Y-m-d'), $holidays)) {
            $school_days++;
        }
        $curr->modify('+1 day');
    }

    // 2. Fetch All Staff and their presence count
    $range_start_dt = $range_start . ' 00:00:00';
    $range_end_dt = $range_end . ' 23:59:59';
    $staff_perf_stmt = $conn->prepare("
        SELECT u.id, u.username, sp.full_name, sp.job_title,
               COUNT(sa.id) as presence_count
        FROM users u
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        LEFT JOIN staff_attendance sa ON u.id = sa.user_id AND sa.check_in_time BETWEEN ? AND ?
        WHERE u.role IN ('facilitator', 'supervisor', 'teacher', 'admin')
        GROUP BY u.id
        ORDER BY sp.full_name ASC, u.username ASC
    ");
    $staff_perf_stmt->bind_param("ss", $range_start_dt, $range_end_dt);
    $staff_perf_stmt->execute();
    $staff_perf_res = $staff_perf_stmt->get_result();
    while($row = $staff_perf_res->fetch_assoc()) {
        $row['school_days'] = $school_days;
        $perf_summary[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personnel Security Oversight | Salba Montessori</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
        }
        /* Custom Slim Scrollbar for lists */
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.02);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-indigo-50/40 via-slate-50 to-slate-100/50">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content <?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : '' ?> p-6 md:p-8 min-h-screen">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-black bg-gradient-to-r from-indigo-950 via-slate-900 to-indigo-900 bg-clip-text text-transparent tracking-tight">Staff Attendance</h1>
                <p class="text-xs text-indigo-600 font-bold tracking-wider mt-1 uppercase"><?= date('l, F jS, Y', strtotime($selected_date)) ?></p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="document.getElementById('manualClockModal').classList.remove('hidden')" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-750 text-white rounded-none text-xs font-bold transition-all shadow-md shadow-indigo-500/10 active:scale-95 flex items-center gap-1.5 duration-200">
                    <i class="fas fa-hand-pointer text-[10px]"></i> Manual Override
                </button>
            </div>
        </div>

        <!-- Filter & Control Toolbar -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 bg-white/60 backdrop-blur-md p-2 rounded-none border border-slate-200/40 shadow-sm">
            <!-- View Switcher -->
            <div class="flex items-center gap-1 bg-slate-100/70 p-1 rounded-none">
                <a href="?view=daily&date=<?= $selected_date ?>&tab=<?= $selected_tab ?>" class="px-4 py-2 rounded-none text-xs font-bold transition-all <?= $active_view === 'daily' ? 'bg-white text-slate-800 shadow-sm border border-slate-200/10' : 'text-slate-500 hover:text-slate-900' ?>">Daily Manifest</a>
                <a href="?view=historical&date=<?= $selected_date ?>" class="px-4 py-2 rounded-none text-xs font-bold tracking-wide transition-all <?= $active_view === 'historical' ? 'bg-white text-slate-800 shadow-sm border border-slate-200/10' : 'text-slate-500 hover:text-slate-900' ?>">Historical Audit</a>
            </div>

            <!-- Date Selector -->
            <div class="flex items-center gap-2 bg-slate-50 px-3 py-2 rounded-none border border-slate-200/50 shadow-inner">
                <i class="fas fa-calendar-day text-slate-400 text-sm"></i>
                <form method="GET" class="inline flex items-center">
                    <input type="hidden" name="view" value="<?= $active_view ?>">
                    <input type="hidden" name="tab" value="<?= $selected_tab ?>">
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-transparent border-none text-xs font-bold text-slate-700 outline-none p-0 cursor-pointer">
                </form>
            </div>
        </div>

        <?php if($active_view === 'daily'): ?>
        <!-- Stats HUD (Minimalist Modern Row) -->
        <div id="stats-hud-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <!-- Staff Present Card -->
            <div class="bg-white rounded-none border border-slate-100 p-5 shadow-sm flex items-center gap-4 transition-all hover:shadow-md duration-300">
                <div class="w-11 h-11 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-users text-lg"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider">Present / Total</p>
                    <div class="text-lg font-extrabold text-slate-800 leading-none mt-1">
                        <?= $stats['present'] ?> <span class="text-xs font-medium text-slate-400">/ <?= $total_staff ?></span>
                    </div>
                </div>
            </div>
            
            <!-- On-Time Card -->
            <div class="bg-white rounded-none border border-slate-100 p-5 shadow-sm flex items-center gap-4 transition-all hover:shadow-md duration-300">
                <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-user-clock text-lg"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider">On-Time</p>
                    <div class="text-lg font-extrabold text-slate-800 leading-none mt-1"><?= $stats['on_time'] + $stats['early'] ?></div>
                </div>
            </div>

            <!-- Geofence Card -->
            <div class="bg-white rounded-none border border-slate-100 p-5 shadow-sm flex items-center gap-4 transition-all hover:shadow-md duration-300">
                <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-location-dot text-lg"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider">Geofence Variance</p>
                    <div class="text-lg font-extrabold text-slate-800 leading-none mt-1"><?= $stats['geofence_violations'] ?></div>
                </div>
            </div>

            <!-- Late Card -->
            <div class="bg-white rounded-none border border-slate-100 p-5 shadow-sm flex items-center gap-4 transition-all hover:shadow-md duration-300">
                <div class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                    <i class="fas fa-triangle-exclamation text-lg"></i>
                </div>
                <div>
                    <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider">Late Arrivals</p>
                    <div class="text-lg font-extrabold text-slate-800 leading-none mt-1"><?= $stats['late'] ?></div>
                </div>
            </div>
        </div>

        <!-- Section Switcher Tabs & Live Search -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 border-b border-slate-200/50 pb-3" id="tabs-container">
            <div class="flex gap-2">
                <a href="?view=daily&date=<?= $selected_date ?>&tab=present" id="tab-btn-present" onclick="switchTab('present', event)" class="tab-btn px-4 py-2 text-xs font-bold transition-all relative <?= $selected_tab === 'present' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-slate-500 hover:text-slate-900' ?>">
                    Present (<span id="present-count"><?= count($attendance) ?></span>)
                </a>
                <a href="?view=daily&date=<?= $selected_date ?>&tab=absent" id="tab-btn-absent" onclick="switchTab('absent', event)" class="tab-btn px-4 py-2 text-xs font-bold transition-all relative <?= $selected_tab === 'absent' ? 'text-rose-600 border-b-2 border-rose-600' : 'text-slate-500 hover:text-slate-900' ?>">
                    Absent (<span id="absent-count"><?= count($absent_staff) ?></span>)
                </a>
            </div>
            
            <!-- Live Search Bar -->
            <div class="relative w-full md:w-72 shrink-0">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none">
                    <i class="fas fa-search text-slate-400 text-xs"></i>
                </span>
                <input type="text" id="attendance-search" oninput="filterAttendanceList()" placeholder="Search staff by name or role..." class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200/80 rounded-none text-xs font-semibold text-slate-700 placeholder-slate-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition-all shadow-sm">
            </div>
        </div>

        <!-- Present Panel -->
        <div id="panel-present" class="tab-panel <?= $selected_tab === 'present' ? '' : 'hidden' ?> transition-opacity duration-300">
            <!-- Present Log -->
            <div id="present-table-container" class="bg-white rounded-none border border-slate-100 shadow-xl shadow-slate-250/30 overflow-hidden ring-1 ring-slate-900/5">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62.5rem] text-left border-collapse">
                        <thead class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4">Time Logged</th>
                                <th class="px-6 py-4">Location Status</th>
                                <th class="px-6 py-4 text-center">Map Location</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60" id="present-tbody">
                            <?php if(empty($attendance)): ?>
                                <tr id="present-empty-row"><td colspan="5" class="py-20 text-center text-slate-400 font-bold uppercase text-[0.65rem] tracking-[0.3em] italic bg-slate-50/30">No personnel checked in today</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendance as $log): 
                                // Get Initials for placeholder avatar
                                $words = explode(" ", $log['full_name'] ?: $log['username']);
                                $initials = "";
                                foreach ($words as $w) {
                                    if (isset($w[0])) $initials .= strtoupper($w[0]);
                                }
                                $initials = substr($initials, 0, 2);
                            ?>
                                <tr class="hover:bg-indigo-50/20 transition-all duration-300 group search-row" data-search-name="<?= htmlspecialchars(strtolower($log['full_name'] ?: $log['username'])) ?>" data-search-role="<?= htmlspecialchars(strtolower($log['job_title'] ?: 'authorized staff')) ?>">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-11 h-11 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl overflow-hidden border border-slate-200 shadow-sm shrink-0 flex items-center justify-center text-white text-xs font-black">
                                                <?php if($log['photo_path']): ?>
                                                    <img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?= $initials ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="text-slate-900 font-bold text-sm tracking-tight mb-0.5 uppercase"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                                <p class="text-[0.65rem] font-bold text-indigo-500 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Authorized Staff') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <span class="text-[0.65rem] font-bold uppercase tracking-widest text-slate-400 w-8">IN:</span>
                                            <div class="font-extrabold text-slate-800 text-sm leading-none"><?= date('H:i A', strtotime($log['check_in_time'])) ?></div>
                                        </div>
                                        <div class="flex items-center gap-2 mb-2.5">
                                            <span class="text-[0.65rem] font-bold uppercase tracking-widest text-slate-400 w-8">OUT:</span>
                                            <?php if($log['check_out_time']): ?>
                                                <div class="font-bold text-slate-600 text-sm leading-none"><?= date('H:i A', strtotime($log['check_out_time'])) ?></div>
                                            <?php else: ?>
                                                <div class="flex items-center gap-3">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="relative flex h-2 w-2">
                                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                                                        </span>
                                                        <span class="font-bold text-amber-500 text-[0.65rem] uppercase tracking-wider">Active Shift</span>
                                                    </div>
                                                    <form method="POST" onsubmit="return confirm('Clock out this personnel now?')" class="attendance-action-form inline">
                                                        <input type="hidden" name="action" value="manual_clockout">
                                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                        <button type="submit" class="text-[0.65rem] font-black uppercase tracking-wider text-indigo-600 hover:text-indigo-800 transition-colors border-b border-dashed border-indigo-200 hover:border-indigo-500">
                                                            Clock-out
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-full text-[0.55rem] font-bold uppercase tracking-wider <?= $log['punctuality'] === 'Late' ? 'bg-orange-50 text-orange-600 border border-orange-100' : ($log['punctuality'] === 'Early' ? 'bg-sky-50 text-sky-600 border border-sky-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100') ?>">
                                            <i class="fas <?= $log['punctuality'] === 'Late' ? 'fa-clock' : 'fa-circle-check' ?> mr-0.5 text-[8px]"></i> <?= $log['punctuality'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col gap-1.5 items-start">
                                            <span class="px-2.5 py-1 rounded-full text-[0.55rem] font-bold uppercase tracking-wider <?= $log['geofence_status'] === 'Perimeter Variance' ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-sky-50 text-sky-600 border border-sky-100' ?>">
                                                <i class="fas <?= $log['geofence_status'] === 'Perimeter Variance' ? 'fa-triangle-exclamation' : 'fa-hand-shield' ?> mr-0.5 text-[8px]"></i> <?= $log['geofence_status'] ?>
                                            </span>
                                            <span class="text-[0.65rem] font-bold text-slate-400 italic"><?= $log['distance_m'] ?>m from hub</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-none bg-slate-50 border border-slate-200/60 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200/50 shadow-sm transition-all duration-350" title="View Coordinates PIN">
                                            <i class="fas fa-map-pin"></i>
                                        </a>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if($_SESSION['role'] === 'admin'): ?>
                                                <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-50 border border-slate-200 text-slate-600 rounded-none text-[0.65rem] font-bold uppercase tracking-wider hover:bg-indigo-600 hover:text-white hover:border-indigo-600 transition-all duration-300" title="Detailed Audit Log">
                                                    <i class="fas fa-folder-open"></i> History
                                                </a>
                                            <?php endif; ?>
 
                                            <!-- Edit button visible to admins and supervisors -->
                                            <button type="button" onclick="openEditModal(<?= $log['id'] ?>, '<?= addslashes(htmlspecialchars($log['full_name'] ?: $log['username'])) ?>', '<?= $log['check_in_time'] ?>', '<?= $log['check_out_time'] ?? '' ?>')" class="w-9 h-9 inline-flex items-center justify-center rounded-none bg-amber-50 border border-amber-100 text-amber-500 hover:bg-amber-500 hover:text-white transition-all shadow-sm" title="Edit Record">
                                                <i class="fas fa-pen-to-square text-sm"></i>
                                            </button>
 
                                            <form method="POST" onsubmit="return confirm('CRITICAL: Purge this record from institutional manifest? This action is immutable.')" class="attendance-action-form inline">
                                                <input type="hidden" name="action" value="delete_attendance">
                                                <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                <button type="submit" class="w-9 h-9 inline-flex items-center justify-center rounded-none bg-rose-50 border border-rose-100 text-rose-500 hover:bg-rose-500 hover:text-white transition-all shadow-sm" title="Purge Record">
                                                    <i class="fas fa-trash-can text-sm"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Absent Panel -->
        <div id="panel-absent" class="tab-panel <?= $selected_tab === 'absent' ? '' : 'hidden' ?> transition-opacity duration-300">
            <!-- Absent List & Quick Clock-in -->
            <div id="absent-table-container" class="bg-white rounded-none border border-slate-100 shadow-xl shadow-slate-250/30 overflow-hidden ring-1 ring-slate-900/5">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62.5rem] text-left border-collapse">
                        <thead class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4">Staff Member</th>
                                <th class="px-6 py-4">Position / Role</th>
                                <th class="px-6 py-4">Attendance Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60" id="absent-tbody">
                            <?php if(empty($absent_staff)): ?>
                                <tr id="absent-empty-row"><td colspan="4" class="py-24 text-center bg-slate-50/20">
                                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 text-2xl mx-auto mb-4"><i class="fas fa-check-double"></i></div>
                                    <h3 class="text-base font-bold text-slate-700 mb-1 uppercase tracking-wider">Perfect Check-in!</h3>
                                    <p class="text-xs text-slate-400 font-semibold">Every active staff member has clocked in today.</p>
                                </td></tr>
                            <?php endif; ?>
                            <?php foreach ($absent_staff as $staff): 
                                // Get Initials
                                $words = explode(" ", $staff['full_name'] ?: $staff['username']);
                                $initials = "";
                                foreach ($words as $w) {
                                    if (isset($w[0])) $initials .= strtoupper($w[0]);
                                }
                                $initials = substr($initials, 0, 2);
                            ?>
                                <tr class="hover:bg-indigo-50/10 transition-all duration-300 search-row" data-search-name="<?= htmlspecialchars(strtolower($staff['full_name'] ?: $staff['username'])) ?>" data-search-role="<?= htmlspecialchars(strtolower($staff['job_title'] ?: 'instructional staff')) ?>">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-4">
                                            <div class="w-11 h-11 bg-gradient-to-br from-slate-400 to-slate-500 rounded-2xl overflow-hidden border border-slate-200 shadow-sm shrink-0 flex items-center justify-center text-white text-xs font-black">
                                                <?php if($staff['photo_path']): ?>
                                                    <img src="../../<?= htmlspecialchars($staff['photo_path']) ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?= $initials ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="text-slate-900 font-bold text-sm tracking-tight uppercase"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></div>
                                                <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest">Username: <?= htmlspecialchars($staff['username']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                        <?= htmlspecialchars($staff['job_title'] ?: 'Instructional Staff') ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="px-3 py-1.5 rounded-full text-[0.55rem] font-black uppercase tracking-widest bg-rose-50 text-rose-600 border border-rose-100/50">
                                            <i class="fas fa-times-circle mr-1"></i> Absent
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <form method="POST" class="attendance-action-form inline">
                                            <input type="hidden" name="action" value="manual_clockin">
                                            <input type="hidden" name="user_id" value="<?= $staff['id'] ?>">
                                            <input type="hidden" name="check_in_date" value="<?= $selected_date ?>">
                                            <input type="hidden" name="check_in_time" value="<?= date('H:i') ?>">
                                            <button type="submit" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white rounded-none text-[0.65rem] font-black uppercase tracking-widest hover:from-indigo-700 hover:to-indigo-800 shadow-md shadow-indigo-500/20 hover:shadow-indigo-500/35 hover:-translate-y-0.5 active:scale-95 transition-all duration-300 flex items-center gap-1.5 ml-auto">
                                                <i class="fas fa-hand-pointer text-[10px]"></i> Quick Clock-in
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if($active_view === 'historical'): ?>
        <!-- Performance Summary Ledger -->
        <div>
            <div class="px-6 py-4 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-6 bg-white rounded-none border border-slate-100 shadow-sm">
                <div>
                    <h3 class="text-xs font-black text-indigo-500 uppercase tracking-widest">Personnel Performance Ledger</h3>
                    <p class="text-xs text-slate-400 font-semibold mt-1">Total presence vs school working days in selected period</p>
                </div>
                
                <form method="GET" class="flex flex-wrap items-center gap-3">
                    <input type="hidden" name="view" value="historical">
                    <input type="hidden" name="date" value="<?= $selected_date ?>">
                    
                    <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-none border border-slate-200/50">
                        <label class="text-[0.65rem] font-black text-slate-400 uppercase tracking-wider">Month</label>
                        <input type="month" name="s_month" value="<?= $sum_month ?>" class="bg-transparent border-none py-1.5 text-xs font-bold text-slate-700 outline-none">
                    </div>
                    
                    <div class="flex items-center gap-2 px-3 py-1 bg-slate-50 rounded-none border border-slate-200/50">
                        <label class="text-[0.65rem] font-black text-slate-400 uppercase tracking-wider">Week</label>
                        <select name="s_week" class="bg-transparent border-none py-1.5 text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="">Select Week...</option>
                            <?php 
                            $temp_start = clone $sem_start;
                            for($i=1; $i<=$weeks_total; $i++): 
                                $w_label = "Week $i (" . $temp_start->format('Y-m-d') . ")";
                                $is_sel = ($sum_week === $w_label) ? 'selected' : '';
                            ?>
                                <option value="<?= $w_label ?>" <?= $is_sel ?>><?= $w_label ?></option>
                            <?php 
                                $temp_start->modify('+7 days');
                            endfor; ?>
                        </select>
                    </div>
 
                    <button type="submit" class="bg-slate-900 text-white px-5 py-2.5 rounded-none text-xs font-bold uppercase tracking-wider hover:bg-indigo-600 transition-all duration-200 active:scale-95 shadow-sm">
                        Generate Report
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-none border border-slate-100 shadow-xl shadow-slate-250/30 overflow-hidden ring-1 ring-slate-900/5">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62.5rem] text-left border-collapse">
                        <thead class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-slate-400 bg-slate-50/50 border-b border-slate-100">
                            <tr>
                                <th class="px-8 py-5">Personnel</th>
                                <th class="px-8 py-5">Role</th>
                                <th class="px-8 py-5 text-center">Days Present</th>
                                <th class="px-8 py-5 text-center">Working Days</th>
                                <th class="px-8 py-5 text-center">Attendance %</th>
                                <th class="px-8 py-5 text-right">Audit Detail</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/60">
                            <?php foreach($perf_summary as $row): 
                                $percent = $row['school_days'] > 0 ? round(($row['presence_count'] / $row['school_days']) * 100) : 0;
                                $color = $percent >= 90 ? 'emerald' : ($percent >= 75 ? 'sky' : ($percent >= 50 ? 'orange' : 'rose'));
                            ?>
                                <tr class="hover:bg-indigo-50/10 transition-colors">
                                    <td class="px-8 py-5">
                                        <div class="font-extrabold text-slate-900 text-sm tracking-tight uppercase"><?= htmlspecialchars($row['full_name'] ?: $row['username']) ?></div>
                                    </td>
                                    <td class="px-8 py-5">
                                        <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($row['job_title'] ?: 'Authorized Staff') ?></span>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <div class="text-xl font-extrabold text-slate-800 leading-none"><?= $row['presence_count'] ?></div>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <div class="text-xl font-extrabold text-slate-350 leading-none"><?= $row['school_days'] ?></div>
                                    </td>
                                    <td class="px-8 py-5 text-center">
                                        <div class="inline-flex flex-col items-center">
                                            <div class="text-2xl font-black text-<?= $color ?>-600 leading-none mb-1.5"><?= $percent ?><span class="text-xs">%</span></div>
                                            <div class="h-1.5 w-14 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-<?= $color ?>-500" style="width: <?= $percent ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 text-right">
                                        <a href="staff_history.php?user_id=<?= $row['id'] ?>" class="w-9 h-9 inline-flex items-center justify-center rounded-none bg-slate-50 border border-slate-200 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200/50 transition-all duration-300" title="Open Historical Details">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Manual Clock-in Modal -->
    <div id="manualClockModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[100] flex items-center justify-center p-4">
        <div class="bg-slate-900 text-white w-full max-w-lg rounded-none shadow-2xl overflow-hidden border border-slate-800 animate-in fade-in zoom-in duration-300">
            <div class="p-8 bg-gradient-to-br from-slate-900 to-indigo-950 text-white relative overflow-hidden border-b border-slate-800/60">
                <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-500/5 rounded-full -mr-10 -mt-10 blur-2xl"></div>
                <div class="flex justify-between items-center relative z-10">
                    <div>
                        <h3 class="text-lg font-extrabold uppercase tracking-widest text-slate-200">Manual Override</h3>
                        <p class="text-slate-500 text-[0.65rem] mt-1 font-bold tracking-widest uppercase"><i class="fas fa-shield-halved"></i> Personnel Protocol</p>
                    </div>
                    <button onclick="document.getElementById('manualClockModal').classList.add('hidden')" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-2xl flex items-center justify-center transition border border-white/5">
                        <i class="fas fa-times text-slate-400 hover:text-white"></i>
                    </button>
                </div>
            </div>
            
            <form method="POST" class="p-8 space-y-6">
                <input type="hidden" name="action" value="manual_clockin">
                
                <div class="space-y-2 relative animate-in fade-in duration-300" id="custom-select-container">
                    <label class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest ml-1">Staff Member</label>
                    
                    <!-- Selected Value Presenter Button -->
                    <button type="button" id="custom-select-trigger" onclick="toggleCustomSelect()" class="w-full px-5 py-4 bg-slate-955 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200 text-left flex justify-between items-center cursor-pointer" style="background-color: #0c111d;">
                        <span id="custom-select-selected-text">Select personnel...</span>
                        <i class="fas fa-chevron-down text-slate-500 text-xs"></i>
                    </button>
                    
                    <!-- Hidden input to store selected value -->
                    <input type="hidden" name="user_id" id="modal-select-user-id" required>
                    
                    <!-- Dropdown Panel -->
                    <div id="custom-select-dropdown" class="hidden absolute top-full left-0 w-full mt-2 bg-slate-900 border border-slate-800 rounded-none shadow-2xl p-4 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                        <!-- Search Box -->
                        <div class="relative mb-3">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-search text-slate-500 text-[10px]"></i>
                            </span>
                            <input type="text" id="custom-select-search" oninput="filterCustomSelect()" placeholder="Search staff member..." class="w-full pl-8 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-none outline-none text-xs text-slate-200 placeholder-slate-500 focus:border-indigo-500 transition-all">
                        </div>
                        
                        <!-- Options List -->
                        <div class="max-h-48 overflow-y-auto space-y-1 pr-1 custom-scrollbar" id="custom-select-options">
                            <?php foreach($all_staff as $s): 
                                $s_name = htmlspecialchars($s['full_name'] ?: $s['username']);
                            ?>
                                <button type="button" onclick="selectCustomOption(this, <?= $s['id'] ?>, '<?= addslashes($s_name) ?>')" class="custom-select-option w-full px-3 py-2.5 rounded-none text-left text-xs font-semibold text-slate-350 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-between" data-search-name="<?= htmlspecialchars(strtolower($s_name)) ?>">
                                    <span><?= $s_name ?></span>
                                    <i class="fas fa-check text-[10px] hidden text-white check-icon"></i>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest ml-1">Log Date</label>
                        <input type="date" name="check_in_date" value="<?= date('Y-m-d') ?>" required class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest ml-1">Log Time</label>
                        <input type="time" name="check_in_time" value="<?= date('H:i') ?>" required class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                    </div>
                </div>

                <div class="p-4 bg-amber-500/20 border border-amber-500/40 rounded-none">
                    <p class="text-[0.65rem] font-bold text-amber-400 leading-relaxed uppercase tracking-wide">
                        <i class="fas fa-shield-halved mr-1 text-sm"></i> Note: Manual entry bypasses geofencing and records the location as "Verified Hub" in the audit manifest.
                    </p>
                </div>

                <div class="flex flex-col gap-2 pt-4">
                    <button type="submit" class="w-full bg-indigo-600 text-white font-extrabold py-4.5 rounded-none hover:bg-indigo-750 hover:shadow-lg hover:shadow-indigo-500/20 transition flex items-center justify-center gap-2 uppercase tracking-widest text-xs active:scale-95">
                        <i class="fas fa-check-double"></i> Authorize Entry
                    </button>
                    <button type="button" onclick="document.getElementById('manualClockModal').classList.add('hidden')" class="w-full bg-slate-800/60 text-slate-400 font-extrabold py-4 rounded-none hover:bg-slate-800 hover:text-slate-300 transition uppercase tracking-widest text-[0.65rem]">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div id="editAttendanceModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[100] flex items-center justify-center p-4">
        <div class="bg-slate-900 text-white w-full max-w-lg rounded-none shadow-2xl overflow-hidden border border-slate-800 animate-in fade-in zoom-in duration-300">
            <div class="p-8 bg-gradient-to-br from-slate-900 to-indigo-950 text-white relative overflow-hidden border-b border-slate-800/60">
                <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-500/5 rounded-full -mr-10 -mt-10 blur-2xl"></div>
                <div class="flex justify-between items-center relative z-10">
                    <div>
                        <h3 class="text-lg font-extrabold uppercase tracking-widest text-slate-200">Edit Log</h3>
                        <p class="text-indigo-400 text-[0.65rem] mt-1 font-bold tracking-widest uppercase"><i class="fas fa-pen-to-square"></i> <span id="edit-staff-name">Personnel</span></p>
                    </div>
                    <button type="button" onclick="document.getElementById('editAttendanceModal').classList.add('hidden')" class="w-10 h-10 bg-white/5 hover:bg-white/10 rounded-2xl flex items-center justify-center transition border border-white/5">
                        <i class="fas fa-times text-slate-400 hover:text-white"></i>
                    </button>
                </div>
            </div>
            
            <form method="POST" class="attendance-action-form p-8 space-y-6">
                <input type="hidden" name="action" value="edit_attendance">
                <input type="hidden" name="log_id" id="edit-log-id">
                
                <!-- Check-in section -->
                <div class="space-y-3">
                    <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest ml-1">Clock-in Time</span>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[0.55rem] font-bold text-slate-500 uppercase tracking-widest ml-1">Date</label>
                            <input type="date" name="check_in_date" id="edit-check-in-date" required class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[0.55rem] font-bold text-slate-500 uppercase tracking-widest ml-1">Time</label>
                            <input type="time" name="check_in_time" id="edit-check-in-time" required class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                        </div>
                    </div>
                </div>

                <!-- Reopen Shift Checkbox -->
                <div class="p-4 bg-slate-900 border border-slate-800 rounded-none flex items-center justify-between hover:border-slate-700 transition">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-xs font-bold text-slate-200 uppercase tracking-wide">Clear Clock-Out</span>
                        <span class="text-[0.6rem] font-bold text-slate-500 uppercase tracking-widest">Reopen active shift status</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="clear_check_out" id="edit-clear-check-out" onchange="toggleClockOutInputs(this)" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-400 after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 peer-checked:after:bg-white"></div>
                    </label>
                </div>

                <!-- Check-out section -->
                <div id="edit-checkout-fields-container" class="space-y-3 transition-all duration-300">
                    <span class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-widest ml-1">Clock-out Time</span>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="text-[0.55rem] font-bold text-slate-500 uppercase tracking-widest ml-1">Date</label>
                            <input type="date" name="check_out_date" id="edit-check-out-date" class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                        </div>
                        <div class="space-y-2">
                            <label class="text-[0.55rem] font-bold text-slate-500 uppercase tracking-widest ml-1">Time</label>
                            <input type="time" name="check_out_time" id="edit-check-out-time" class="w-full px-5 py-4 bg-slate-950 border border-slate-800 rounded-none focus:border-indigo-500 outline-none transition-all font-bold text-sm text-slate-200" style="color-scheme: dark;">
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-amber-500/20 border border-amber-500/40 rounded-none">
                    <p class="text-[0.65rem] font-bold text-amber-400 leading-relaxed uppercase tracking-wide">
                        <i class="fas fa-triangle-exclamation mr-1 text-sm"></i> Caution: Modifying timestamps will overwrite the original log parameters in the institutional database.
                    </p>
                </div>

                <div class="flex flex-col gap-2 pt-4">
                    <button type="submit" class="w-full bg-indigo-600 text-white font-extrabold py-4.5 rounded-none hover:bg-indigo-750 hover:shadow-lg hover:shadow-indigo-500/20 transition flex items-center justify-center gap-2 uppercase tracking-widest text-xs active:scale-95">
                        <i class="fas fa-check-double"></i> Save Modifications
                    </button>
                    <button type="button" onclick="document.getElementById('editAttendanceModal').classList.add('hidden')" class="w-full bg-slate-800/60 text-slate-400 font-extrabold py-4 rounded-none hover:bg-slate-800 hover:text-slate-300 transition uppercase tracking-widest text-[0.65rem]">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interactive JS Functionality -->
    <script>
        // 1. Toast Notification System
        function showToast(type, message) {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'fixed top-6 right-6 z-[200] flex flex-col gap-3 max-w-sm w-full pointer-events-none';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto flex items-start gap-3 p-4 rounded-2xl backdrop-blur-xl border shadow-xl transition-all duration-500 ease-out transform translate-x-12 opacity-0';
            
            if (type === 'success') {
                toast.classList.add('bg-slate-900/90', 'border-emerald-500/35', 'text-slate-100');
            } else {
                toast.classList.add('bg-slate-900/90', 'border-rose-500/35', 'text-slate-100');
            }
            
            const icon = type === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-exclamation text-rose-400';
            
            toast.innerHTML = `
                <div class="shrink-0 mt-0.5">
                    <i class="fas ${icon} text-base"></i>
                </div>
                <div class="flex-1">
                    <p class="text-xs font-bold leading-normal">${message}</p>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="shrink-0 text-slate-400 hover:text-slate-200 transition-colors ml-1">
                    <i class="fas fa-times text-[10px]"></i>
                </button>
            `;
            
            container.appendChild(toast);
            
            // Animate in
            setTimeout(() => {
                toast.classList.remove('translate-x-12', 'opacity-0');
            }, 10);
            
            // Auto-dismiss after 4.5 seconds
            setTimeout(() => {
                toast.classList.add('translate-x-12', 'opacity-0');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 4500);
        }

        // 2. Client-Side Tab Switching
        function switchTab(tab, event) {
            if (event) {
                event.preventDefault();
            }
            
            const presentBtn = document.getElementById('tab-btn-present');
            const absentBtn = document.getElementById('tab-btn-absent');
            const presentPanel = document.getElementById('panel-present');
            const absentPanel = document.getElementById('panel-absent');
            
            if (!presentBtn || !absentBtn || !presentPanel || !absentPanel) return;
            
            if (tab === 'present') {
                presentBtn.className = 'tab-btn px-4 py-2 text-xs font-bold transition-all relative text-indigo-600 border-b-2 border-indigo-600';
                absentBtn.className = 'tab-btn px-4 py-2 text-xs font-bold transition-all relative text-slate-500 hover:text-slate-900';
                presentPanel.classList.remove('hidden');
                absentPanel.classList.add('hidden');
            } else {
                absentBtn.className = 'tab-btn px-4 py-2 text-xs font-bold transition-all relative text-rose-600 border-b-2 border-rose-600';
                presentBtn.className = 'tab-btn px-4 py-2 text-xs font-bold transition-all relative text-slate-500 hover:text-slate-900';
                absentPanel.classList.remove('hidden');
                presentPanel.classList.add('hidden');
            }
            
            // Update URL search query without triggering page reload
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
            
            // Re-apply search filter on the switched tab if search has value
            const query = document.getElementById('attendance-search');
            if (query && query.value.trim() !== '') {
                filterAttendanceList();
            }
        }

        // 3. Live Client-Side Search
        function filterAttendanceList() {
            const queryEl = document.getElementById('attendance-search');
            if (!queryEl) return;
            
            const query = queryEl.value.toLowerCase().trim();
            const urlParams = new URLSearchParams(window.location.search);
            const activeTab = urlParams.get('tab') || 'present';
            
            const tbodyId = activeTab === 'present' ? 'present-tbody' : 'absent-tbody';
            const emptyRowId = activeTab === 'present' ? 'present-empty-row' : 'absent-empty-row';
            
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            
            const rows = tbody.querySelectorAll('.search-row');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.getAttribute('data-search-name') || '';
                const role = row.getAttribute('data-search-role') || '';
                if (name.includes(query) || role.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Toggle empty state message
            let emptyRow = document.getElementById(emptyRowId);
            if (visibleCount === 0) {
                if (!emptyRow) {
                    emptyRow = document.createElement('tr');
                    emptyRow.id = emptyRowId;
                    const colCount = activeTab === 'present' ? 5 : 4;
                    emptyRow.innerHTML = `<td colspan="${colCount}" class="py-20 text-center text-slate-400 font-bold uppercase text-[0.65rem] tracking-[0.3em] italic bg-slate-50/30">No matching personnel found</td>`;
                    tbody.appendChild(emptyRow);
                } else {
                    emptyRow.style.display = '';
                    emptyRow.querySelector('td').innerText = 'No matching personnel found';
                }
            } else {
                if (emptyRow) {
                    emptyRow.style.display = 'none';
                }
            }
        }

        // 4. Custom Dropdown Select Component for Manual Override Modal
        function toggleCustomSelect() {
            const dropdown = document.getElementById('custom-select-dropdown');
            if (dropdown) {
                dropdown.classList.toggle('hidden');
                if (!dropdown.classList.contains('hidden')) {
                    const search = document.getElementById('custom-select-search');
                    if (search) {
                        search.value = '';
                        filterCustomSelect();
                        search.focus();
                    }
                }
            }
        }

        // Filter searchable select list items
        function filterCustomSelect() {
            const search = document.getElementById('custom-select-search');
            if (!search) return;
            const query = search.value.toLowerCase().trim();
            const options = document.querySelectorAll('.custom-select-option');
            
            options.forEach(opt => {
                const name = opt.getAttribute('data-search-name') || '';
                if (name.includes(query)) {
                    opt.style.display = 'flex';
                } else {
                    opt.style.display = 'none';
                }
            });
        }

        // Select item in custom dropdown list
        function selectCustomOption(element, id, name) {
            const hiddenInput = document.getElementById('modal-select-user-id');
            const triggerText = document.getElementById('custom-select-selected-text');
            if (hiddenInput && triggerText) {
                hiddenInput.value = id;
                triggerText.innerText = name;
            }
            
            // Update option styles and checkmark states
            const options = document.querySelectorAll('.custom-select-option');
            options.forEach(opt => {
                const check = opt.querySelector('.check-icon');
                if (opt === element) {
                    opt.classList.add('bg-indigo-600/20', 'text-indigo-400');
                    if (check) check.classList.remove('hidden');
                } else {
                    opt.classList.remove('bg-indigo-600/20', 'text-indigo-400');
                    if (check) check.classList.add('hidden');
                }
            });
            
            const dropdown = document.getElementById('custom-select-dropdown');
            if (dropdown) dropdown.classList.add('hidden');
        }

        // Close dropdown when clicking outside trigger boundaries
        document.addEventListener('click', (e) => {
            const container = document.getElementById('custom-select-container');
            if (container && !container.contains(e.target)) {
                const dropdown = document.getElementById('custom-select-dropdown');
                if (dropdown) dropdown.classList.add('hidden');
            }
        });

        // Edit Attendance Modal Controller Functions
        function openEditModal(logId, name, checkInDateTime, checkOutDateTime) {
            const modal = document.getElementById('editAttendanceModal');
            if (!modal) return;
            
            // Set name & ID
            document.getElementById('edit-staff-name').innerText = name;
            document.getElementById('edit-log-id').value = logId;
            
            // Check-in split
            if (checkInDateTime) {
                const parts = checkInDateTime.split(' ');
                document.getElementById('edit-check-in-date').value = parts[0] || '';
                document.getElementById('edit-check-in-time').value = parts[1] ? parts[1].substring(0, 5) : '';
            } else {
                document.getElementById('edit-check-in-date').value = '';
                document.getElementById('edit-check-in-time').value = '';
            }
            
            // Check-out split & checkbox handling
            const clearCheckbox = document.getElementById('edit-clear-check-out');
            const checkoutDateInput = document.getElementById('edit-check-out-date');
            const checkoutTimeInput = document.getElementById('edit-check-out-time');
            
            if (checkOutDateTime && checkOutDateTime.trim() !== '') {
                const parts = checkOutDateTime.split(' ');
                checkoutDateInput.value = parts[0] || '';
                checkoutTimeInput.value = parts[1] ? parts[1].substring(0, 5) : '';
                clearCheckbox.checked = false;
            } else {
                checkoutDateInput.value = '';
                checkoutTimeInput.value = '';
                clearCheckbox.checked = true;
            }
            
            toggleClockOutInputs(clearCheckbox);
            
            modal.classList.remove('hidden');
        }

        function toggleClockOutInputs(checkbox) {
            const dateInput = document.getElementById('edit-check-out-date');
            const timeInput = document.getElementById('edit-check-out-time');
            const container = document.getElementById('edit-checkout-fields-container');
            
            if (checkbox.checked) {
                dateInput.disabled = true;
                timeInput.disabled = true;
                dateInput.required = false;
                timeInput.required = false;
                container.style.opacity = '0.35';
                container.style.pointerEvents = 'none';
            } else {
                dateInput.disabled = false;
                timeInput.disabled = false;
                dateInput.required = true;
                timeInput.required = true;
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
                
                // Pre-populate with check-in date if checkout date is empty
                if (!dateInput.value) {
                    dateInput.value = document.getElementById('edit-check-in-date').value;
                }
                if (!timeInput.value) {
                    const now = new Date();
                    const hrs = String(now.getHours()).padStart(2, '0');
                    const mins = String(now.getMinutes()).padStart(2, '0');
                    timeInput.value = `${hrs}:${mins}`;
                }
            }
        }

        // 5. AJAX Form Submissions and Fragment Synchronizers
        function bindAttendanceForms() {
            document.querySelectorAll('.attendance-action-form').forEach(form => {
                if (form.dataset.ajaxBound) return;
                form.dataset.ajaxBound = "true";
                
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const onsubmitVal = form.getAttribute('onsubmit');
                    if (onsubmitVal && onsubmitVal.includes('confirm')) {
                        const msgMatch = onsubmitVal.match(/confirm\('([^']+)'\)/);
                        if (msgMatch && msgMatch[1]) {
                            if (!confirm(msgMatch[1])) return;
                        }
                    }
                    
                    const submitBtn = form.querySelector('button[type="submit"]');
                    let originalInner = '';
                    if (submitBtn) {
                        originalInner = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = `<i class="fas fa-spinner animate-spin text-[10px] mr-1"></i> Syncing...`;
                    }
                    
                    const formData = new FormData(form);
                    formData.append('ajax', '1');
                    
                    try {
                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const res = await response.json();
                        
                        if (res.status === 'success') {
                            showToast('success', res.message);
                            
                            // Clear and hide Manual Override modal if active
                            const modal = document.getElementById('manualClockModal');
                            if (modal) {
                                modal.classList.add('hidden');
                                const text = document.getElementById('custom-select-selected-text');
                                const hidden = document.getElementById('modal-select-user-id');
                                if (text && hidden) {
                                    text.innerText = 'Select personnel...';
                                    hidden.value = '';
                                }
                                const options = document.querySelectorAll('.custom-select-option');
                                options.forEach(opt => {
                                    opt.classList.remove('bg-indigo-600/20', 'text-indigo-400');
                                    const check = opt.querySelector('.check-icon');
                                    if (check) check.classList.add('hidden');
                                });
                            }
                            
                            // Hide Edit Attendance modal if active
                            const editModal = document.getElementById('editAttendanceModal');
                            if (editModal) {
                                editModal.classList.add('hidden');
                            }
                            
                            // Re-fetch page parts and update DOM
                            await syncPageFragments();
                        } else {
                            showToast('error', res.message);
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalInner;
                            }
                        }
                    } catch (err) {
                        showToast('error', 'A terminal connection failure was encountered during sync.');
                        console.error(err);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalInner;
                        }
                    }
                });
            });
        }

        async function syncPageFragments() {
            try {
                const response = await fetch(window.location.href);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const targets = [
                    '#stats-hud-container',
                    '#tabs-container',
                    '#panel-present',
                    '#panel-absent'
                ];
                
                targets.forEach(selector => {
                    const oldEl = document.querySelector(selector);
                    const newEl = doc.querySelector(selector);
                    if (oldEl && newEl) {
                        oldEl.style.opacity = '0.3';
                        oldEl.style.transition = 'opacity 0.15s ease';
                        setTimeout(() => {
                            oldEl.innerHTML = newEl.innerHTML;
                            oldEl.style.opacity = '1';
                            
                            // Re-bind actions on newly created fragments
                            if (selector === '#panel-present' || selector === '#panel-absent' || selector === '#tabs-container') {
                                bindAttendanceForms();
                                filterAttendanceList();
                            }
                        }, 150);
                    }
                });
            } catch (err) {
                console.error('Fragment synchronization failure:', err);
            }
        }

        // Initialize script logic on load
        document.addEventListener('DOMContentLoaded', () => {
            bindAttendanceForms();
            
            // Target the Manual Clock-in form in the Modal
            const modalForm = document.querySelector('#manualClockModal form');
            if (modalForm) {
                modalForm.classList.add('attendance-action-form');
                bindAttendanceForms();
            }
            
            // Show any PHP session messages as modern floating toasts
            <?php if(isset($_SESSION['success'])): ?>
                showToast('success', "<?= addslashes($_SESSION['success']) ?>");
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                showToast('error', "<?= addslashes($_SESSION['error']) ?>");
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>
</body>
</html>


<?php
// ... end of file
?>
