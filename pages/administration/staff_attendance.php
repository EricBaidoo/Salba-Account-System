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

// Geofence Calibration
$school_lat = 5.5786875;
$school_lng = -0.2911875;
$allowed_radius = 300;

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
    <title>Staff Attendance Tracker | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] text-indigo-950 font-sans">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 p-10 min-h-screen">
        <header class="mb-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] bg-indigo-100 text-indigo-700 px-3 py-1 rounded">Personnel Oversight Hub</span>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($_SESSION['role']) ?> View Mode</span>
                </div>
                <h1 class="text-4xl font-black tracking-tight text-slate-900">Staff Attendance <span class="text-indigo-600">Tracking Dashboard</span></h1>
            </div>

            <div class="flex items-center gap-4 bg-white p-2 rounded-2xl shadow-sm border border-slate-100">
                <form method="GET" class="flex items-center gap-2 text-sm font-bold">
                    <label for="date" class="px-2 text-slate-400 italic">filter by date</label>
                    <input type="date" name="date" id="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="border-none bg-slate-50 rounded-xl px-4 py-2 focus:ring-0">
                </form>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="text-4xl font-black text-slate-900 leading-none"><?= $stats['present'] ?> <span class="text-sm font-bold text-slate-300">/ <?= $total_staff ?></span></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">Faculty Present</p>
            </div>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="text-4xl font-black text-emerald-600 leading-none"><?= $stats['on_time'] + $stats['early'] ?></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">On-Time / Early</p>
            </div>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="text-4xl font-black text-red-600 leading-none"><?= $stats['geofence_violations'] ?></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">Geofence Violations</p>
            </div>
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-slate-100">
                <div class="text-4xl font-black text-orange-600 leading-none"><?= $stats['late'] ?></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-2">Late Arrivals</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-[3rem] shadow-xl border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead class="bg-slate-50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <tr>
                        <th class="px-10 py-6 text-left">Staff Member</th>
                        <th class="px-10 py-6 text-left">Punctuality</th>
                        <th class="px-10 py-6 text-left">Location Status</th>
                        <th class="px-10 py-6 text-center">Map</th>
                        <th class="px-10 py-6 text-center">Personnel Record</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($attendance as $log): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-10 py-6 flex items-center gap-4">
                                <div class="w-12 h-12 bg-indigo-50 rounded-2xl overflow-hidden border border-indigo-100 shadow-inner">
                                    <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?><div class="w-full h-full flex items-center justify-center text-indigo-200"><i class="fas fa-user"></i></div><?php endif; ?>
                                </div>
                                <div class="font-black text-slate-900 lowercase tracking-tighter text-lg leading-tight"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                            </td>
                            <td class="px-10 py-6">
                                <div class="font-black text-slate-900 leading-none mb-1"><?= date('h:i A', strtotime($log['check_in_time'])) ?></div>
                                <span class="text-[9px] font-black uppercase tracking-widest <?= $log['punctuality'] === 'Late' ? 'text-red-500' : 'text-emerald-600' ?>"><?= $log['punctuality'] ?></span>
                            </td>
                            <td class="px-10 py-6">
                                <span class="text-[10px] font-black uppercase tracking-widest <?= $log['geofence_status'] === 'Violation' ? 'text-red-600 animate-pulse' : 'text-indigo-600' ?>"><?= $log['geofence_status'] ?></span>
                                <div class="text-[8px] text-slate-400 font-bold italic"><?= $log['distance_m'] ?>m from campus</div>
                                <?php if(isset($log['accuracy']) && $log['accuracy'] > 0): ?>
                                    <div class="text-[7px] text-slate-300 font-black uppercase tracking-widest mt-1">±<?= round($log['accuracy']) ?>m Accuracy</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-10 py-6 text-center">
                                <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-100 text-slate-500 hover:bg-indigo-700 hover:text-white transition-all"><i class="fas fa-map-location-dot"></i></a>
                            </td>
                            <td class="px-10 py-6 text-center">
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                    <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-50 text-indigo-700 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-700 hover:text-white transition-all">
                                        <i class="fas fa-history"></i> Track History
                                    </a>
                                <?php else: ?>
                                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest italic cursor-not-allowed" title="Administrative Privilege Required">Historical Lock</span>
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
