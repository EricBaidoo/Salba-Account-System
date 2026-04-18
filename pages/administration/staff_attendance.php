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

// Deletion Handler (Admin Protocol)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attendance') {
    if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor') {
        $_SESSION['error'] = "Institutional Violation: Unauthorized purge attempt detected.";
    } else {
        $log_id = intval($_POST['log_id']);
        $conn->query("DELETE FROM staff_attendance WHERE id = $log_id");
        log_activity($conn, 'Security Audit', "Personnel attendance record #$log_id purged from manifest.", $_SESSION['user_id']);
        $_SESSION['success'] = "Resource purged successfully.";
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

$total_staff = $conn->query("SELECT COUNT(*) FROM users WHERE role IN ('facilitator', 'supervisor')")->fetch_row()[0];
$stats['total'] = $total_staff;
$stats['absent'] = max(0, $total_staff - $stats['present']);
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
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="font-sans bg-security">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-6 md:p-12 min-h-screen">
        <header class="mb-14 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-10">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-3 py-1 bg-sky-500/10 text-sky-400 rounded-lg text-[10px] font-black uppercase tracking-[0.2em] border border-sky-500/20">Security Oversight</span>
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><?= date('l, F jS', strtotime($selected_date)) ?></span>
                </div>
                
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-emerald-700">
                        <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-[10px] font-black uppercase tracking-widest text-rose-700">
                        <i class="fas fa-exclamation-triangle mr-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                <h1 class="text-4xl sm:text-5xl font-black tracking-tighter text-slate-900 leading-tight">Personnel <br><span class="text-indigo-600">Attendance Manifest</span></h1>
            </div>
 
            <div class="bg-white p-2.5 rounded-2xl border border-slate-200 flex items-center gap-4 shadow-sm">
                <form method="GET" class="flex items-center gap-3">
                    <i class="fas fa-calendar-day text-slate-400 ml-3"></i>
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-slate-50 border-none rounded-xl px-4 py-2 text-sm font-bold text-slate-700 focus:ring-1 focus:ring-indigo-500 transition-all">
                </form>
            </div>
        </header>

        <!-- Modern Light HUD -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            <div class="security-card card-vivid-indigo p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all"></div>
                <p class="text-[10px] font-black text-indigo-100 uppercase tracking-[0.2em] mb-4">Institutional Presence</p>
                <div class="flex items-end gap-3">
                    <span class="text-6xl font-black text-white"><?= $stats['present'] ?></span>
                    <span class="text-xl font-bold text-indigo-200 mb-1">/ <?= $total_staff ?></span>
                </div>
                <div class="mt-6 h-1 w-full bg-indigo-900/20 rounded-full overflow-hidden">
                    <div class="h-full bg-white" style="width: <?= ($total_staff > 0) ? ($stats['present']/$total_staff)*100 : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="security-card card-vivid-emerald p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/5 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-[0.2em] mb-4">Verified On-Time</p>
                <span class="text-6xl font-black text-white leading-none"><?= $stats['on_time'] + $stats['early'] ?></span>
                <p class="text-[8px] font-black text-emerald-100 uppercase tracking-widest mt-4">Punctuality Verified</p>
            </div>
 
            <div class="security-card card-vivid-rose p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/5 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-rose-100 uppercase tracking-[0.2em] mb-4">Perimeter Variance</p>
                <span class="text-6xl font-black text-white"><?= $stats['geofence_violations'] ?></span>
                <p class="text-[8px] font-black text-rose-100 uppercase tracking-widest mt-4">Breach Detections</p>
            </div>
 
            <div class="security-card card-vivid-orange p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/5 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-orange-100 uppercase tracking-[0.2em] mb-4">Late Identifications</p>
                <span class="text-6xl font-black text-white"><?= $stats['late'] ?></span>
                <p class="text-[8px] font-black text-orange-100 uppercase tracking-widest mt-4">Policy Discrepancy</p>
            </div>
        </div>

        <!-- Audit Manifest -->
        <div class="overflow-x-auto">
            <div class="min-w-full">
                <div class="px-6 py-4 flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-black text-slate-600 uppercase tracking-[0.4em]">Integrated Audit Manifest</h3>
                    <div class="flex items-center gap-2 text-[9px] font-bold text-emerald-400 uppercase tracking-widest bg-emerald-500/5 px-4 py-2 rounded-full border border-emerald-500/10">
                        <i class="fas fa-lock text-[8px]"></i> End-to-End Encryption Active
                    </div>
                </div>
                
                <div class="security-manifest-wrapper">
                    <table class="security-manifest-table">
                        <thead class="text-[9px] font-black uppercase tracking-widest text-slate-600">
                            <tr>
                                <th class="px-12 py-4 text-left">Identity</th>
                                <th class="px-12 py-4 text-left">Timestamp</th>
                                <th class="px-12 py-4 text-left">Verification Node</th>
                                <th class="px-12 py-4 text-center">HUD</th>
                                <th class="px-12 py-4 text-right">Audit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($attendance)): ?>
                                <tr><td colspan="5" class="py-24 text-center text-slate-600 font-black uppercase text-[10px] tracking-[0.5em] italic">No personnel identified for this cycle</td></tr>
                            <?php endif; ?>
                            <?php foreach ($attendance as $log): ?>
                                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors">
                                    <td class="px-12 py-6">
                                        <div class="flex items-center gap-5">
                                            <div class="w-12 h-12 bg-slate-100 rounded-xl overflow-hidden border border-slate-200 shrink-0">
                                                <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover grayscale-0 opacity-100">
                                                <?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-400"><i class="fas fa-user-shield text-xl"></i></div><?php endif; ?>
                                            </div>
                                            <div class="max-w-[180px]">
                                                <div class="text-slate-900 font-bold text-sm tracking-tight leading-tight mb-1 uppercase"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                                <p class="text-[8px] font-black text-indigo-500 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Authorized Staff') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-12 py-6">
                                        <div class="font-black text-slate-900 text-base leading-none mb-1"><?= date('H:i A', strtotime($log['check_in_time'])) ?></div>
                                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-3"><?= date('M j, Y', strtotime($log['check_in_time'])) ?></div>
                                        <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $log['punctuality'] === 'Late' ? 'bg-orange-50 text-orange-600' : ($log['punctuality'] === 'Early' ? 'bg-sky-50 text-sky-600' : 'bg-emerald-50 text-emerald-600') ?>">
                                            <i class="fas <?= $log['punctuality'] === 'Late' ? 'fa-clock' : 'fa-circle-check' ?>"></i> <?= $log['punctuality'] ?>
                                        </span>
                                    </td>
                                    <td class="px-12 py-6">
                                        <div class="flex flex-col gap-2">
                                            <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $log['geofence_status'] === 'Perimeter Variance' ? 'bg-rose-50 text-rose-600' : 'bg-sky-50 text-sky-600' ?>">
                                                <i class="fas <?= $log['geofence_status'] === 'Perimeter Variance' ? 'fa-triangle-exclamation' : 'fa-hand-shield' ?>"></i> <?= $log['geofence_status'] ?>
                                            </span>
                                            <span class="text-[9px] font-black text-slate-400 uppercase italic px-2"><?= $log['distance_m'] ?>m from hub center</span>
                                        </div>
                                    </td>
                                    <td class="px-12 py-6 text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-white hover:bg-indigo-500 transition-all">
                                            <i class="fas fa-map-pin"></i>
                                        </a>
                                    </td>
                                    <td class="px-12 py-6 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if($_SESSION['role'] === 'admin'): ?>
                                                <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all" title="Detailed Audit Log">
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
    </main>
</body>
</html>


<?php
// ... end of file
?>
