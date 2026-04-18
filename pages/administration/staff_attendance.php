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
        $row['geofence_status'] = ($dist <= $allowed_radius) ? 'Verified' : 'Outside Bounds';
        
        $check_time = date('H:i:s', strtotime($row['check_in_time']));
        if ($check_time < $early_limit) { $row['punctuality'] = 'Early'; $stats['early']++; }
        elseif ($check_time < $ontime_limit) { $row['punctuality'] = 'On-Time'; $stats['on_time']++; }
        else { $row['punctuality'] = 'Late'; $stats['late']++; }
        
        $attendance[] = $row;
        $stats['present']++;
        if ($row['geofence_status'] === 'Outside Bounds') $stats['geofence_violations']++;
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
    <title>Staff Attendance Dashboard | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .academic-card {
            background: white;
            border-radius: 2rem;
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }
        .academic-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
        }
        .status-badge {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
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
                    <span class="px-3 py-1 bg-white text-slate-500 rounded-lg text-[10px] font-black uppercase tracking-widest border border-slate-100 italic">Institutional Oversight</span>
                    <span class="text-[10px] font-black text-slate-300 uppercase tracking-widest"><?= date('l, F jS', strtotime($selected_date)) ?></span>
                </div>
                <h1 class="text-5xl font-black tracking-tighter text-slate-900 leading-tight">Staff Attendance <br><span class="text-indigo-600">Daily Dashboard</span></h1>
            </div>

            <div class="bg-white p-3 rounded-3xl shadow-sm border border-slate-100 flex items-center gap-4">
                <form method="GET" class="flex items-center gap-3">
                    <i class="fas fa-calendar-day text-indigo-300 ml-3"></i>
                    <input type="date" name="date" value="<?= $selected_date ?>" onchange="this.form.submit()" class="bg-slate-50 border-none rounded-xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                </form>
            </div>
        </header>

        <!-- Monitoring stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
            <div class="academic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Daily Presence</p>
                <div class="flex items-end gap-3">
                    <span class="text-5xl font-black text-slate-900"><?= $stats['present'] ?></span>
                    <span class="text-xl font-bold text-slate-300 mb-1">/ <?= $total_staff ?></span>
                </div>
            </div>
            
            <div class="academic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">On-Time Performance</p>
                <span class="text-5xl font-black text-emerald-600 leading-none"><?= $stats['on_time'] + $stats['early'] ?></span>
            </div>

            <div class="academic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Location Accuracy</p>
                <span class="text-5xl font-black text-indigo-600 leading-none"><?= count($attendance) - $stats['geofence_violations'] ?></span>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-4">Within Campus Boundaries</p>
            </div>

            <div class="academic-card p-10">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Late Arrivals</p>
                <span class="text-5xl font-black text-orange-600 leading-none"><?= $stats['late'] ?></span>
            </div>
        </div>

        <!-- Attendance List -->
        <div class="bg-white rounded-[3rem] shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
            <div class="px-10 py-8 border-b border-slate-50 flex justify-between items-center">
                <h3 class="text-xs font-black text-slate-400 uppercase tracking-[0.3em]">Daily Presence Record</h3>
                <span class="text-[10px] font-bold text-slate-300 flex items-center gap-2"><i class="fas fa-location-dot"></i> Distance verification enabled</span>
            </div>
            <table class="w-full">
                <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                    <tr>
                        <th class="px-10 py-6 text-left">Staff Member</th>
                        <th class="px-10 py-6 text-left">Time of Entry</th>
                        <th class="px-10 py-6 text-left">Campus Location</th>
                        <th class="px-10 py-6 text-center">Map View</th>
                        <th class="px-10 py-6 text-right">Records</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if(empty($attendance)): ?>
                        <tr><td colspan="5" class="py-20 text-center text-slate-300 font-bold uppercase text-xs tracking-widest italic">No attendance records found for this date</td></tr>
                    <?php endif; ?>
                    <?php foreach ($attendance as $log): ?>
                        <tr class="hover:bg-slate-50/30 transition-colors">
                            <td class="px-10 py-8">
                                <div class="flex items-center gap-5">
                                    <div class="w-14 h-14 bg-slate-50 rounded-2xl overflow-hidden border border-slate-100 shrink-0">
                                        <?php if($log['photo_path']): ?><img src="../../<?= htmlspecialchars($log['photo_path']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-200"><i class="fas fa-user-circle text-2xl"></i></div><?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="font-black text-slate-900 text-lg tracking-tight leading-none mb-1"><?= htmlspecialchars($log['full_name'] ?: $log['username']) ?></div>
                                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?= htmlspecialchars($log['job_title'] ?: 'Staff Member') ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-10 py-8">
                                <div class="font-black text-slate-900 leading-none mb-2"><?= date('h:i A', strtotime($log['check_in_time'])) ?></div>
                                <span class="status-badge <?= $log['punctuality'] === 'Late' ? 'bg-orange-50 text-orange-600' : 'bg-emerald-50 text-emerald-600' ?>">
                                    <?= $log['punctuality'] ?>
                                </span>
                            </td>
                            <td class="px-10 py-8">
                                <div class="flex flex-col gap-1.5">
                                    <span class="status-badge <?= $log['geofence_status'] === 'Outside Bounds' ? 'bg-rose-50 text-rose-600' : 'bg-indigo-50 text-indigo-600' ?>">
                                        <?= $log['geofence_status'] ?>
                                    </span>
                                    <span class="text-[10px] text-slate-400 italic"><?= $log['distance_m'] ?>m from campus center</span>
                                </div>
                            </td>
                            <td class="px-10 py-8 text-center">
                                <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-12 h-12 inline-flex items-center justify-center rounded-2xl bg-white border border-slate-100 text-slate-400 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                    <i class="fas fa-map-pin"></i>
                                </a>
                            </td>
                            <td class="px-10 py-8 text-right">
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                    <a href="staff_history.php?user_id=<?= $log['user_id'] ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-white border border-slate-200 text-slate-700 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                        <i class="fas fa-folder-open"></i> Full History
                                    </a>
                                <?php else: ?>
                                    <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest italic">Restricted</span>
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
