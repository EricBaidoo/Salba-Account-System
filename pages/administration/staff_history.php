<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

// STRICT LOCK: Personnel History is reserved for Administrative Oversight ONLY
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    // Redirect non-admins (Supervisors/Facilitators) back to the daily hub if they attempt direct access
    header('Location: staff_attendance.php');
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if (!$user_id) {
    echo "User ID missing.";
    exit;
}

// Fetch Dynamic Punctuality Rules
$early_limit_val = getSystemSetting($conn, 'attendance_early_limit', '06:30');
$ontime_limit_val = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
$early_limit = date('H:i:s', strtotime($early_limit_val));
$ontime_limit = date('H:i:s', strtotime($ontime_limit_val));

// Geofence Calibration
$school_lat = 5.5786875; $school_lng = -0.2911875;
$allowed_radius = 300;

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

// Fetch Staff Profile
$prof_res = $conn->query("SELECT u.username, u.role, sp.* FROM users u LEFT JOIN staff_profiles sp ON u.id = sp.user_id WHERE u.id = $user_id LIMIT 1");
$staff = $prof_res->fetch_assoc();

if (!$staff) {
    echo "Staff record not found.";
    exit;
}

// Fetch Full Attendance History
$logs_res = $conn->query("SELECT * FROM staff_attendance WHERE user_id = $user_id ORDER BY check_in_time DESC LIMIT 50");

$history = [];
$stats = ['total_present' => 0, 'on_time_total' => 0, 'compliant' => 0];

if ($logs_res) {
    while ($row = $logs_res->fetch_assoc()) {
        $dist = getDistanceMeters($school_lat, $school_lng, $row['latitude'], $row['longitude']);
        $row['distance_m'] = round($dist);
        $row['geofence_status'] = ($dist <= $allowed_radius) ? 'Compliant' : 'Violation';
        
        $t = date('H:i:s', strtotime($row['check_in_time']));
        if ($t < $early_limit) { $row['p_status'] = 'Early'; $stats['on_time_total']++; }
        elseif ($t < $ontime_limit) { $row['p_status'] = 'On-Time'; $stats['on_time_total']++; }
        else { $row['p_status'] = 'Late'; }
        
        $history[] = $row;
        $stats['total_present']++;
        if ($row['geofence_status'] === 'Compliant') $stats['compliant']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?> History | Administrative Audit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] text-indigo-950 font-sans">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 p-10 min-h-screen">
        <nav class="mb-8 flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-slate-400">
            <a href="dashboard.php" class="hover:text-indigo-700">Administration</a>
            <i class="fas fa-chevron-right text-[8px]"></i>
            <a href="staff_attendance.php" class="hover:text-indigo-700 text-indigo-700 italic">Personnel Audit Hub</a>
        </nav>

        <header class="mb-12 flex flex-col md:flex-row justify-between items-end gap-6">
            <div class="flex items-center gap-8">
                <div class="w-32 h-32 rounded-[2.5rem] bg-indigo-50 border-[6px] border-white shadow-xl overflow-hidden flex-shrink-0">
                    <?php if($staff['photo_path']): ?><img src="../../<?= htmlspecialchars($staff['photo_path']) ?>" class="w-full h-full object-cover">
                    <?php else: ?><div class="w-full h-full flex items-center justify-center text-4xl text-indigo-200"><i class="fas fa-user-shield"></i></div><?php endif; ?>
                </div>
                <div>
                    <h1 class="text-5xl font-black text-slate-900 tracking-tight leading-tight"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></h1>
                    <p class="text-slate-400 text-lg font-medium tracking-tight"><?= htmlspecialchars($staff['job_title'] ?: 'Faculty Member at Salba Montessori') ?></p>
                </div>
            </div>
            
            <button onclick="window.print()" class="bg-indigo-700 text-white text-[10px] font-black uppercase tracking-widest px-8 py-4 rounded-2xl flex items-center gap-2 shadow-lg">
                <i class="fas fa-file-pdf"></i> Generate Personnel Audit
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
            <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-100 italic">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em] mb-4">Personnel Presence</p>
                <div class="text-6xl font-black text-slate-900 tracking-tight"><?= $stats['total_present'] ?> <span class="text-xl font-bold text-slate-300">Days</span></div>
            </div>
            <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em] mb-4">Institutional Punctuality</p>
                <?php $rate = $stats['total_present'] > 0 ? round(($stats['on_time_total'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-indigo-700 tracking-tighter"><?= $rate ?><span class="text-3xl text-indigo-200">%</span></div>
            </div>
            <div class="bg-white p-10 rounded-[3rem] shadow-sm border border-slate-100">
                <p class="text-[10px] font-black text-slate-300 uppercase tracking-[0.3em] mb-4">Location Compliance</p>
                <?php $comp_rate = $stats['total_present'] > 0 ? round(($stats['compliant'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-slate-900 tracking-tighter"><?= $comp_rate ?><span class="text-3xl text-slate-200">%</span></div>
            </div>
        </div>

        <div class="bg-white rounded-[4rem] shadow-2xl border border-slate-50 overflow-hidden">
            <div class="px-12 py-10 bg-slate-50/50 border-b border-slate-100">
                <h3 class="text-2xl font-black text-slate-900 tracking-tight">Administrative Transaction Ledger</h3>
            </div>

            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 border-b border-slate-100">
                        <th class="px-12 py-6 text-left">Date & Time</th>
                        <th class="px-12 py-6 text-left">Punctuality Audit</th>
                        <th class="px-12 py-6 text-left">Compliance</th>
                        <th class="px-12 py-6 text-center">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($history as $log): ?>
                        <tr class="hover:bg-slate-50/50 transition-all">
                            <td class="px-12 py-8">
                                <div class="font-black text-slate-900 tracking-tight text-lg mb-1"><?= date('l, F j, Y', strtotime($log['check_in_time'])) ?></div>
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none"><?= date('h:i:s A', strtotime($log['check_in_time'])) ?></div>
                            </td>
                            <td class="px-12 py-8">
                                <span class="text-[10px] font-black uppercase tracking-widest <?= $log['p_status'] === 'Late' ? 'text-red-500' : 'text-emerald-600' ?>"><?= $log['p_status'] ?></span>
                            </td>
                            <td class="px-12 py-8">
                                <span class="text-[10px] font-black uppercase tracking-widest <?= $log['geofence_status'] === 'Violation' ? 'text-red-600 font-black italic' : 'text-indigo-600' ?>"><?= $log['geofence_status'] ?></span>
                                <div class="text-[9px] text-slate-300 font-bold"><?= $log['distance_m'] ?>m from hub</div>
                            </td>
                            <td class="px-12 py-8 text-center">
                                <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-2xl bg-slate-100 text-slate-500 hover:bg-slate-900 hover:text-white transition-all shadow-inner"><i class="fas fa-map-location-dot"></i></a>
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
