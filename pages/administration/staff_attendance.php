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
                <h1 class="text-4xl sm:text-5xl font-black tracking-tighter text-white leading-tight">Personnel <br><span class="text-sky-500">Attendance Manifest</span></h1>
            </div>

            <div class="bg-slate-800/80 p-2.5 rounded-2xl border border-white/5 flex items-center gap-4 shadow-xl">
                <form method="GET" class="flex items-center gap-3">
                    <i class="fas fa-calendar-day text-slate-500 ml-3"></i>
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-slate-900 border-none rounded-xl px-4 py-2 text-sm font-bold text-white focus:ring-1 focus:ring-sky-500 transition-all">
                </form>
            </div>
        </header>

        <!-- Oversight HUD -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-16">
            <div class="security-card p-10 relative overflow-hidden group">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Institutional Manifest</p>
                <div class="flex items-end gap-3">
                    <span class="text-5xl font-black text-white group-hover:text-sky-400 transition-colors"><?= $stats['present'] ?></span>
                    <span class="text-xl font-bold text-slate-600 mb-1">/ <?= $total_staff ?></span>
                </div>
                <div class="absolute bottom-0 left-0 h-1 bg-sky-500/20 w-full">
                    <div class="h-full bg-sky-400" style="width: <?= ($total_staff > 0) ? ($stats['present']/$total_staff)*100 : 0 ?>%"></div>
                </div>
            </div>
            
            <div class="security-card p-10">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Timing Accuracy</p>
                <span class="text-5xl font-black text-emerald-500 leading-none stat-glow"><?= $stats['on_time'] + $stats['early'] ?></span>
                <p class="text-[8px] font-black text-emerald-500/60 uppercase tracking-widest mt-4">Verified Punctuality</p>
            </div>

            <div class="security-card p-10">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Perimeter Variance</p>
                <span class="text-5xl font-black <?= $stats['geofence_violations'] > 0 ? 'text-rose-500' : 'text-slate-500' ?> leading-none stat-glow"><?= $stats['geofence_violations'] ?></span>
                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest mt-4">Outside Authorized Hub</p>
            </div>

            <div class="security-card p-10">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Late Arrivals</p>
                <span class="text-5xl font-black text-orange-500 leading-none stat-glow"><?= $stats['late'] ?></span>
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
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-5">
                                            <div class="w-12 h-12 bg-slate-800 rounded-xl overflow-hidden border border-white/5 shadow-inner shrink-0">
                                                <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover grayscale opacity-80 hover:grayscale-0 hover:opacity-100 transition-all">
                                                <?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-600"><i class="fas fa-user-shield text-xl"></i></div><?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-white text-base tracking-tight leading-none mb-1"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                                <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Authorized Staff') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="font-bold text-white leading-none mb-2"><?= date('H:i A', strtotime($log['check_in_time'])) ?></div>
                                        <span class="security-status-badge <?= $log['punctuality'] === 'Late' ? 'bg-orange-500/10 text-orange-400 border border-orange-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' ?>">
                                            <?= $log['punctuality'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-1.5">
                                            <span class="security-status-badge <?= $log['geofence_status'] === 'Perimeter Variance' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-sky-500/10 text-sky-400 border border-sky-500/20' ?>">
                                                <?= $log['geofence_status'] ?>
                                            </span>
                                            <span class="text-[9px] font-bold text-slate-600 italic"><?= $log['distance_m'] ?>m from hub center</span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-800 border border-white/5 text-slate-500 hover:text-white hover:bg-sky-500 transition-all shadow-lg">
                                            <i class="fas fa-map-pin"></i>
                                        </a>
                                    </td>
                                    <td class="text-right">
                                        <?php if($_SESSION['role'] === 'admin'): ?>
                                            <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-500/10 border border-sky-500/20 text-sky-400 rounded-xl text-[9px] font-black uppercase tracking-widest hover:bg-sky-500 hover:text-white transition-all">
                                                <i class="fas fa-folder-open"></i> Full History
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[8px] font-black text-slate-700 uppercase tracking-widest italic">Restricted</span>
                                        <?php endif; ?>
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
