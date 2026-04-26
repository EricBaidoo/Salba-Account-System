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
            $conn->query("DELETE FROM staff_attendance WHERE id = $log_id");
            log_activity($conn, 'Security Audit', "Personnel attendance record #$log_id purged from manifest.", $_SESSION['user_id']);
            $_SESSION['success'] = "Resource purged successfully.";
        }
        
        if ($action === 'manual_clockin') {
            $target_user_id = intval($_POST['user_id']);
            $manual_date = $_POST['check_in_date'];
            $manual_time = $_POST['check_in_time'];
            $combined_time = $manual_date . ' ' . $manual_time . ':00';
            
            // Check for active shift (clocked in but not clocked out)
            $active_check = $conn->query("SELECT id FROM staff_attendance WHERE user_id = $target_user_id AND check_out_time IS NULL LIMIT 1");
            if ($active_check->num_rows > 0) {
                $_SESSION['error'] = "Personnel already has an active clock-in. Please clock them out before creating a new entry.";
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
$sql = "SELECT sa.*, u.username, u.role, sp.full_name, sp.job_title, sp.photo_path 
        FROM staff_attendance sa
        JOIN users u ON sa.user_id = u.id
        LEFT JOIN staff_profiles sp ON sa.user_id = sp.user_id
        WHERE DATE(sa.check_in_time) = '$selected_date'
        ORDER BY sa.check_in_time DESC";
$logs_res = $conn->query($sql);

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

$total_staff = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('facilitator', 'supervisor', 'teacher')")->fetch_row()[0];
$stats['total'] = $total_staff;
$stats['absent'] = max(0, $total_staff - $stats['present']);

// Fetch All Staff for Manual Entry
$all_staff_res = $conn->query("SELECT u.id, u.username, sp.full_name FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.role IN ('facilitator', 'supervisor', 'teacher', 'admin') ORDER BY sp.full_name ASC, u.username ASC");
$all_staff = [];
while($as = $all_staff_res->fetch_assoc()) $all_staff[] = $as;

// --- Performance Summary Logic ---
$active_view = $_GET['view'] ?? 'daily';
$sum_month = $_GET['s_month'] ?? '';
$sum_week = $_GET['s_week'] ?? ''; // Format: Week X (Start Date)

$sem_start_str = getSystemSetting($conn, 'semester_start_date', date('Y-m-01'));
$sem_start = new DateTime($sem_start_str);
$weeks_total = intval(getSystemSetting($conn, 'weeks_per_term', 12));

$range_start = null;
$range_end = null;

if ($sum_month) {
    $range_start = date('Y-m-01', strtotime($sum_month));
    $range_end = date('Y-m-t', strtotime($sum_month));
} elseif ($sum_week) {
    // Parse "Week X (YYYY-MM-DD)"
    if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', $sum_week, $matches)) {
        $range_start = $matches[1];
        $w_start = new DateTime($range_start);
        $w_end = clone $w_start;
        $w_end->modify('+6 days');
        $range_end = $w_end->format('Y-m-d');
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
    $h_res = $conn->query("SELECT event_date FROM academic_calendar WHERE event_date BETWEEN '$range_start' AND '$range_end'");
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
    $staff_perf_res = $conn->query("
        SELECT u.id, u.username, sp.full_name, sp.job_title,
               COUNT(sa.id) as presence_count
        FROM users u
        LEFT JOIN staff_profiles sp ON u.id = sp.user_id
        LEFT JOIN staff_attendance sa ON u.id = sa.user_id AND sa.check_in_time BETWEEN '$range_start 00:00:00' AND '$range_end 23:59:59'
        WHERE u.role IN ('facilitator', 'supervisor', 'teacher', 'admin')
        GROUP BY u.id
        ORDER BY sp.full_name ASC, u.username ASC
    ");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content <?= $_SESSION['role'] === 'admin' ? 'lg:ml-72' : '' ?> p-6 md:p-12 min-h-screen">
        <header class="mb-14 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-10">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[0.625rem] font-black uppercase tracking-[0.2em] border border-indigo-100">Attendance Monitoring</span>
                    <span class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest"><?= date('l, F jS', strtotime($selected_date)) ?></span>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-[0.625rem] font-black uppercase tracking-widest text-emerald-700">
                        <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-[0.625rem] font-black uppercase tracking-widest text-rose-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <h1 class="text-4xl sm:text-5xl font-black tracking-tighter text-slate-900 leading-tight">Staff <br><span class="text-indigo-600">Attendance Hub</span></h1>
            </div>
 
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-2 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm mr-4">
                    <a href="?view=daily&date=<?= $selected_date ?>" class="px-6 py-3 rounded-xl text-[0.625rem] font-black uppercase tracking-widest transition-all <?= $active_view === 'daily' ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-400 hover:text-slate-900' ?>">Daily Manifest</a>
                    <a href="?view=historical&date=<?= $selected_date ?>" class="px-6 py-3 rounded-xl text-[0.625rem] font-black uppercase tracking-widest transition-all <?= $active_view === 'historical' ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-400 hover:text-slate-900' ?>">Historical Audit</a>
                </div>

                <div class="bg-white p-2.5 rounded-2xl border border-slate-200 flex items-center gap-4 shadow-sm">
                    <form method="GET" class="flex items-center gap-3">
                        <input type="hidden" name="view" value="<?= $active_view ?>">
                        <i class="fas fa-calendar-day text-slate-400 ml-3"></i>
                        <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-slate-50 border-none rounded-xl px-4 py-2 text-sm font-bold text-slate-700 focus:ring-1 focus:ring-indigo-500 transition-all">
                    </form>
                </div>
                <button onclick="document.getElementById('manualClockModal').classList.remove('hidden')" class="bg-slate-900 text-white px-8 py-4 rounded-2xl font-black text-[0.625rem] uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-xl shadow-slate-200 flex items-center gap-3 active:scale-95">
                    <i class="fas fa-hand-pointer"></i> Manual Clock-in
                </button>
            </div>
        </header>

        <?php if($active_view === 'daily'): ?>
        <!-- Modern Light HUD -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            <div class="bg-white rounded-2xl border border-indigo-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-indigo-50 rounded-full blur-3xl group-hover:bg-indigo-100 transition-all"></div>
                <p class="text-[0.625rem] font-black text-indigo-600 uppercase tracking-[0.2em] mb-4">Staff Present</p>
                <div class="flex items-end gap-3">
                    <span class="text-6xl font-black text-slate-900"><?= $stats['present'] ?></span>
                    <span class="text-xl font-bold text-slate-400 mb-1">/ <?= $total_staff ?></span>
                </div>
                <div class="mt-6 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full bg-indigo-500" style="width: <?= ($total_staff > 0) ? ($stats['present']/$total_staff)*100 : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-emerald-50 rounded-full blur-3xl"></div>
                <p class="text-[0.625rem] font-black text-emerald-600 uppercase tracking-[0.2em] mb-4">On-Time Attendance</p>
                <span class="text-6xl font-black text-slate-900 leading-none"><?= $stats['on_time'] + $stats['early'] ?></span>
                <p class="text-[0.5rem] font-black text-emerald-500 uppercase tracking-widest mt-4">Verified Punctuality</p>
            </div>
 
            <div class="bg-white rounded-2xl border border-rose-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-rose-50 rounded-full blur-3xl"></div>
                <p class="text-[0.625rem] font-black text-rose-600 uppercase tracking-[0.2em] mb-4">Location Status</p>
                <span class="text-6xl font-black text-slate-900"><?= $stats['geofence_violations'] ?></span>
                <p class="text-[0.5rem] font-black text-rose-500 uppercase tracking-widest mt-4">Outside Boundary</p>
            </div>
 
            <div class="bg-white rounded-2xl border border-orange-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-orange-50 rounded-full blur-3xl"></div>
                <p class="text-[0.625rem] font-black text-orange-600 uppercase tracking-[0.2em] mb-4">Late Arrivals</p>
                <span class="text-6xl font-black text-slate-900"><?= $stats['late'] ?></span>
                <p class="text-[0.5rem] font-black text-orange-500 uppercase tracking-widest mt-4">School Policy Note</p>
            </div>
        </div>

        <!-- Audit Manifest -->
        <div class="px-6 py-4 flex justify-between items-center mb-4">
            <h3 class="text-[0.625rem] font-black text-slate-400 uppercase tracking-[0.4em]">Daily Attendance Log</h3>
            <div class="flex items-center gap-2 text-[0.5625rem] font-bold text-indigo-500 uppercase tracking-widest bg-indigo-50 px-4 py-2 rounded-full border border-indigo-100">
                <i class="fas fa-check-double text-[0.5rem]"></i> System Sync Active
            </div>
        </div>
        
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[62.5rem] text-left border-collapse">
                    <thead class="text-[0.5625rem] font-black uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="px-6 py-4 text-left">Staff Member</th>
                            <th class="px-6 py-4 text-left">Time Logged</th>
                            <th class="px-6 py-4 text-left">Location Status</th>
                            <th class="px-6 py-4 text-center">Map</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                        <tbody>
                            <?php if(empty($attendance)): ?>
                                <tr><td colspan="5" class="py-24 text-center text-slate-600 font-black uppercase text-[0.625rem] tracking-[0.5em] italic">No personnel identified for this cycle</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendance as $log): ?>
                                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-6">
                                        <div class="flex items-center gap-5">
                                            <div class="w-12 h-12 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 shrink-0">
                                                <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover grayscale-0 opacity-100">
                                                <?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-400"><i class="fas fa-user-shield text-xl"></i></div><?php endif; ?>
                                            </div>
                                            <div class="max-w-[11.25rem]">
                                                <div class="text-slate-900 font-bold text-sm tracking-tight leading-tight mb-1 uppercase"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                                <p class="text-[0.5rem] font-black text-indigo-500 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Authorized Staff') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-[0.5625rem] font-black uppercase tracking-widest text-slate-400 w-8">IN:</span>
                                            <div class="font-black text-slate-900 text-base leading-none"><?= date('H:i A', strtotime($log['check_in_time'])) ?></div>
                                        </div>
                                        <div class="flex items-center gap-2 mb-3">
                                            <span class="text-[0.5625rem] font-black uppercase tracking-widest text-slate-400 w-8">OUT:</span>
                                            <?php if($log['check_out_time']): ?>
                                                <div class="font-black text-slate-700 text-base leading-none"><?= date('H:i A', strtotime($log['check_out_time'])) ?></div>
                                            <?php else: ?>
                                                <div class="flex flex-col gap-2">
                                                    <div class="font-bold text-amber-500 text-xs leading-none animate-pulse uppercase tracking-widest"><i class="fas fa-spinner fa-spin mr-1"></i> Active Shift</div>
                                                    <form method="POST" onsubmit="return confirm('Clock out this personnel now?')">
                                                        <input type="hidden" name="action" value="manual_clockout">
                                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                        <button type="submit" class="text-[0.5rem] font-black uppercase tracking-widest text-indigo-600 hover:text-indigo-800 transition-colors flex items-center gap-1">
                                                            <i class="fas fa-sign-out-alt"></i> Clock-out Now
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-3"><?= date('M j, Y', strtotime($log['check_in_time'])) ?></div>
                                        <span class="px-3 py-1 rounded-lg text-[0.5625rem] font-black uppercase tracking-widest <?= $log['punctuality'] === 'Late' ? 'bg-orange-50 text-orange-600' : ($log['punctuality'] === 'Early' ? 'bg-sky-50 text-sky-600' : 'bg-emerald-50 text-emerald-600') ?>">
                                            <i class="fas <?= $log['punctuality'] === 'Late' ? 'fa-clock' : 'fa-circle-check' ?>"></i> <?= $log['punctuality'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-6">
                                        <div class="flex flex-col gap-2">
                                            <span class="px-3 py-1 rounded-lg text-[0.5625rem] font-black uppercase tracking-widest <?= $log['geofence_status'] === 'Perimeter Variance' ? 'bg-rose-50 text-rose-600' : 'bg-sky-50 text-sky-600' ?>">
                                                <i class="fas <?= $log['geofence_status'] === 'Perimeter Variance' ? 'fa-triangle-exclamation' : 'fa-hand-shield' ?>"></i> <?= $log['geofence_status'] ?>
                                            </span>
                                            <span class="text-[0.5625rem] font-black text-slate-400 uppercase italic px-2"><?= $log['distance_m'] ?>m from hub center</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-6 text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-white hover:bg-indigo-500 transition-all">
                                            <i class="fas fa-map-pin"></i>
                                        </a>
                                    </td>
                                    <td class="px-6 py-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if($_SESSION['role'] === 'admin'): ?>
                                                <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-xl text-[0.5625rem] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all" title="Detailed Audit Log">
                                                    <i class="fas fa-folder-open"></i> Full History
                                                </a>
                                            <?php endif; ?>

                                            <form method="POST" onsubmit="return confirm('CRITICAL: Purge this record from institutional manifest? This action is immutable.')" class="inline">
                                                <input type="hidden" name="action" value="delete_attendance">
                                                <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                                <button type="submit" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-lg" title="Purge Record">
                                                    <i class="fas fa-trash-can"></i>
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
        <?php endif; ?>

        <?php if($active_view === 'historical'): ?>
        <!-- Performance Summary Ledger -->
        <div>
            <div class="px-6 py-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8">
                <div>
                    <h3 class="text-[0.625rem] font-black text-indigo-500 uppercase tracking-[0.4em] mb-2">Personnel Performance Ledger</h3>
                    <p class="text-xs font-bold text-slate-400">Total presence vs school working days in selected period</p>
                </div>
                
                <form method="GET" class="flex flex-wrap items-center gap-4 bg-white p-3 rounded-2xl border border-slate-200 shadow-sm">
                    <input type="hidden" name="view" value="historical">
                    <input type="hidden" name="date" value="<?= $selected_date ?>">
                    
                    <div class="flex items-center gap-2 px-3 border-r border-slate-100">
                        <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Month</label>
                        <input type="month" name="s_month" value="<?= $sum_month ?>" class="bg-slate-50 border-none rounded-lg px-3 py-1.5 text-[0.6875rem] font-bold text-slate-700 outline-none">
                    </div>
                    
                    <div class="flex items-center gap-2 px-3 border-r border-slate-100">
                        <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Semester Week</label>
                        <select name="s_week" class="bg-slate-50 border-none rounded-lg px-3 py-1.5 text-[0.6875rem] font-bold text-slate-700 outline-none appearance-none cursor-pointer">
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

                    <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl text-[0.625rem] font-black uppercase tracking-widest hover:bg-slate-900 transition-all">
                        Generate Report
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-[2.5rem] border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[62.5rem] text-left border-collapse">
                        <thead class="text-[0.5625rem] font-black uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-8 py-5">Personnel</th>
                                <th class="px-8 py-5">Role</th>
                                <th class="px-8 py-5 text-center">Days Present</th>
                                <th class="px-8 py-5 text-center">Working Days</th>
                                <th class="px-8 py-5 text-center">Attendance %</th>
                                <th class="px-8 py-5 text-right">Audit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach($perf_summary as $row): 
                                $percent = $row['school_days'] > 0 ? round(($row['presence_count'] / $row['school_days']) * 100) : 0;
                                $color = $percent >= 90 ? 'emerald' : ($percent >= 75 ? 'sky' : ($percent >= 50 ? 'orange' : 'rose'));
                            ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-8 py-6">
                                        <div class="font-black text-slate-900 text-base tracking-tight uppercase"><?= htmlspecialchars($row['full_name'] ?: $row['username']) ?></div>
                                    </td>
                                    <td class="px-8 py-6">
                                        <span class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($row['job_title'] ?: 'Authorized Staff') ?></span>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <div class="text-2xl font-black text-slate-900 leading-none"><?= $row['presence_count'] ?></div>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <div class="text-2xl font-black text-slate-300 leading-none"><?= $row['school_days'] ?></div>
                                    </td>
                                    <td class="px-8 py-6 text-center">
                                        <div class="inline-flex flex-col items-center">
                                            <div class="text-3xl font-black text-<?= $color ?>-500 leading-none mb-1"><?= $percent ?><span class="text-xs">%</span></div>
                                            <div class="h-1 w-12 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-<?= $color ?>-500" style="width: <?= $percent ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-6 text-right">
                                        <a href="staff_history.php?user_id=<?= $row['id'] ?>" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-white hover:bg-indigo-600 transition-all">
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
    <div id="manualClockModal" class="hidden fixed inset-0 bg-slate-900/80 backdrop-blur-md z-[100] flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
            <div class="p-8 bg-gradient-to-br from-slate-800 to-slate-900 text-white relative overflow-hidden">
                <div class="absolute right-0 top-0 w-32 h-32 bg-white/5 rounded-full -mr-10 -mt-10 blur-2xl"></div>
                <div class="flex justify-between items-center relative z-10">
                    <div>
                        <h3 class="text-xl font-black uppercase tracking-tighter">Manual Override</h3>
                        <p class="text-slate-400 text-[0.625rem] mt-1 font-bold tracking-widest uppercase">Personnel Security Protocol</p>
                    </div>
                    <button onclick="document.getElementById('manualClockModal').classList.add('hidden')" class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-xl flex items-center justify-center transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <form method="POST" class="p-8 space-y-6">
                <input type="hidden" name="action" value="manual_clockin">
                
                <div class="space-y-2">
                    <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest ml-1">Staff Member</label>
                    <select name="user_id" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:border-indigo-500 outline-none transition-all font-bold text-sm appearance-none">
                        <option value="">Select personnel...</option>
                        <?php foreach($all_staff as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name'] ?: $s['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest ml-1">Log Date</label>
                        <input type="date" name="check_in_date" value="<?= date('Y-m-d') ?>" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:border-indigo-500 outline-none transition-all font-bold text-sm">
                    </div>
                    <div class="space-y-2">
                        <label class="text-[0.625rem] font-black text-slate-400 uppercase tracking-widest ml-1">Log Time</label>
                        <input type="time" name="check_in_time" value="<?= date('H:i') ?>" required class="w-full px-6 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:border-indigo-500 outline-none transition-all font-bold text-sm">
                    </div>
                </div>

                <div class="p-4 bg-amber-50 border border-amber-100 rounded-2xl mb-2">
                    <p class="text-[0.625rem] font-bold text-amber-700 leading-relaxed uppercase tracking-tight">
                        <i class="fas fa-shield-halved mr-1"></i> NOTE: Manual entry will bypass geofencing and mark the location as "Verified Hub" in audit logs.
                    </p>
                </div>

                <div class="flex flex-col gap-3 pt-4">
                    <button type="submit" class="w-full bg-slate-900 text-white font-black py-5 rounded-2xl hover:bg-indigo-600 transition flex items-center justify-center gap-3 uppercase tracking-widest text-xs shadow-xl shadow-slate-200 active:scale-95">
                        <i class="fas fa-check-double"></i> Authorize Entry
                    </button>
                    <button type="button" onclick="document.getElementById('manualClockModal').classList.add('hidden')" class="w-full bg-slate-50 text-slate-400 font-black py-5 rounded-2xl hover:bg-slate-100 transition uppercase tracking-widest text-xs">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>


<?php
// ... end of file
?>
