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
        $row['geofence_status'] = ($dist <= $allowed_radius) ? 'Compliant' : 'Violation';
        
        $check_time = date('H:i:s', strtotime($row['check_in_time']));
        if ($check_time < $early_limit) { $row['punctuality'] = 'Early'; $stats['early']++; }
        elseif ($check_time < $ontime_limit) { $row['punctuality'] = 'On-Time'; $stats['on_time']++; }
        else { $row['punctuality'] = 'Late'; $stats['late']++; }
        
        $attendance[] = $row;
        $stats['present']++;
        if ($row['geofence_status'] === 'Violation') $stats['geofence_violations']++;
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
    <title>Staff Attendance Tracker | Institutional Control</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .forensic-card {
            background: white;
            border-radius: 2rem;
            border: 1px solid #f1f5f9;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .forensic-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.05);
            border-color: #e2e8f0;
        }
        .status-badge {
            font-size: 9px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            padding: 4px 12px;
            border-radius: 99px;
        }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-12 min-h-screen">
        <header class="mb-16 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-8">
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-[10px] font-black uppercase tracking-widest border border-indigo-100 italic">Faculty Oversight</span>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><?= date('l, F jS', strtotime($selected_date)) ?></span>
                </div>
                <h1 class="text-5xl font-black tracking-tighter text-slate-900 leading-tight">Staff Attendance <br><span class="text-indigo-600">Forensic Dashboard</span></h1>
            </div>

            <div class="bg-white p-3 rounded-3xl shadow-sm border border-slate-100 flex items-center gap-4">
                <form method="GET" class="flex items-center gap-3">
                    <i class="fas fa-calendar-day text-slate-300 ml-3"></i>
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-slate-50 border-none rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                </form>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
            <div class="forensic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Total Manifest</p>
                <div class="flex items-end gap-3">
                    <span class="text-5xl font-black text-slate-900"><?= $stats['present'] ?></span>
                    <span class="text-xl font-bold text-slate-300 mb-1">/ <?= $total_staff ?></span>
                </div>
                <div class="mt-6 flex items-center gap-2">
                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-600 rounded-full" style="width: <?= ($total_staff > 0) ? ($stats['present']/$total_staff)*100 : 0 ?>%"></div>
                    </div>
                </div>
            </div>
            
            <div class="forensic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">On-Time Index</p>
                <span class="text-5xl font-black text-emerald-600 leading-none"><?= $stats['on_time'] + $stats['early'] ?></span>
                <p class="text-[9px] font-bold text-emerald-500 uppercase tracking-widest mt-4 flex items-center gap-2">
                    <i class="fas fa-arrow-trend-up"></i> Optimal Performance
                </p>
            </div>

            <div class="forensic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Boundary Breaches</p>
                <span class="text-5xl font-black text-rose-600 leading-none"><?= $stats['geofence_violations'] ?></span>
                <p class="text-[9px] font-bold text-rose-500 uppercase tracking-widest mt-4 flex items-center gap-2">
                    <i class="fas fa-shield-slash"></i> Geofence Alert
                </p>
            </div>

            <div class="forensic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Late Incursions</p>
                <span class="text-5xl font-black text-orange-600 leading-none"><?= $stats['late'] ?></span>
                <p class="text-[9px] font-bold text-orange-400 uppercase tracking-widest mt-4 flex items-center gap-2">
                    <i class="fas fa-clock"></i> Behind Schedule
                </p>
            </div>
        </div>

        <!-- Attendance Manifest -->
        <div class="bg-white rounded-[3.5rem] shadow-2xl shadow-indigo-900/5 border border-slate-100 overflow-hidden">
            <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Staff Presence Manifest</h3>
                <span class="text-[10px] font-bold bg-slate-100 px-4 py-2 rounded-xl text-slate-500 uppercase italic">Dynamic Geo-Verification Active</span>
            </div>
            <table class="w-full">
                <thead class="bg-slate-50/50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <tr>
                        <th class="px-10 py-6 text-left">Professional Identity</th>
                        <th class="px-10 py-6 text-left">Timestamp</th>
                        <th class="px-10 py-6 text-left">Geographic Node</th>
                        <th class="px-10 py-6 text-center">Visualizer</th>
                        <th class="px-10 py-6 text-right">Forensic Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($attendance)): ?>
                        <tr><td colspan="5" class="py-20 text-center text-slate-300 font-bold italic uppercase text-xs tracking-widest">No entries recorded for this date hub</td></tr>
                    <?php endif; ?>
                    <?php foreach ($attendance as $log): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-10 py-8">
                                <div class="flex items-center gap-5">
                                    <div class="w-14 h-14 bg-white rounded-2xl overflow-hidden border border-slate-100 shadow-sm shrink-0">
                                        <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-200"><i class="fas fa-user text-2xl"></i></div><?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-black text-slate-900 text-lg tracking-tight leading-none mb-1 lowercase"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Faculty Member') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-10 py-8">
                                <div class="font-black text-slate-900 leading-none mb-2"><?= date('H:i:s', strtotime($log['check_in_time'])) ?></div>
                                <span class="status-badge <?= $log['punctuality'] === 'Late' ? 'bg-orange-50 text-orange-600' : 'bg-emerald-50 text-emerald-600' ?>">
                                    <?= $log['punctuality'] ?>
                                </span>
                            </td>
                            <td class="px-10 py-8">
                                <div class="flex flex-col gap-2">
                                    <span class="status-badge <?= $log['geofence_status'] === 'Violation' ? 'bg-rose-50 text-rose-600 animate-pulse' : 'bg-indigo-50 text-indigo-600' ?>">
                                        <?= $log['geofence_status'] ?>
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400 italic"><?= $log['distance_m'] ?>m from hub</span>
                                    <?php if(isset($log['accuracy']) && $log['accuracy'] > 0): ?>
                                        <span class="text-[7px] font-black text-slate-300 uppercase leading-none">±<?= round($log['accuracy']) ?>m Accuracy</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-10 py-8 text-center">
                                <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-12 h-12 inline-flex items-center justify-center rounded-2xl bg-white border border-slate-100 text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                    <i class="fas fa-map-location-dot"></i>
                                </a>
                            </td>
                            <td class="px-10 py-8 text-right">
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                    <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition-all shadow-lg">
                                        <i class="fas fa-history"></i> Log History
                                    </a>
                                <?php else: ?>
                                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest italic cursor-not-allowed">Access Restricted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

<?php
// ... end of file
?>
