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
    <title><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?> History | Personnel Audit</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="font-sans bg-security">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-6 md:p-12 min-h-screen">
        <nav class="mb-10 flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-slate-500">
            <a href="dashboard.php" class="hover:text-sky-400 transition-colors">Administration</a>
            <i class="fas fa-chevron-right text-[7px] text-slate-700"></i>
            <a href="staff_attendance.php" class="hover:text-sky-400 transition-colors">Security Oversight</a>
            <i class="fas fa-chevron-right text-[7px] text-slate-700"></i>
            <span class="text-sky-500 italic">Personnel Audit</span>
        </nav>

        <header class="mb-14 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="flex items-center gap-10">
                <div class="w-32 h-32 rounded-[2.5rem] bg-slate-800 border-4 border-white/5 shadow-2xl overflow-hidden flex-shrink-0 group relative">
                    <?php if($staff['photo_path']): ?><img src="../../<?= htmlspecialchars($staff['photo_path']) ?>" class="w-full h-full object-cover grayscale opacity-80 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-500">
                    <?php else: ?><div class="w-full h-full flex items-center justify-center text-4xl text-slate-700"><i class="fas fa-user-shield"></i></div><?php endif; ?>
                    <div class="absolute inset-0 border border-white/5 pointer-events-none rounded-[2.5rem]"></div>
                </div>
                <div class="text-center md:text-left">
                    <h1 class="text-4xl sm:text-5xl font-black text-white tracking-tighter leading-tight mb-2"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></h1>
                    <div class="flex items-center gap-3 justify-center md:justify-start">
                        <span class="px-3 py-1 bg-sky-500/10 text-sky-400 rounded-lg text-[9px] font-black uppercase tracking-[0.2em] border border-sky-500/20">Authorized Faculty</span>
                        <p class="text-slate-500 text-sm font-bold tracking-tight uppercase tracking-widest"><?= htmlspecialchars($staff['job_title'] ?: 'Academic Personnel') ?></p>
                    </div>
                </div>
            </div>
            
            <button onclick="window.print()" class="bg-slate-800 text-white text-[10px] font-black uppercase tracking-[0.3em] px-8 py-5 rounded-2xl border border-white/5 hover:bg-sky-500 hover:border-sky-400 transition-all flex items-center gap-3 shadow-2xl">
                <i class="fas fa-print"></i> Personnel Audit Log
            </button>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
            <div class="security-card p-10 relative overflow-hidden group">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Total Manifest Records</p>
                <div class="text-6xl font-black text-white tracking-tight group-hover:text-sky-400 transition-colors"><?= $stats['total_present'] ?> <span class="text-xl font-bold text-slate-600">ID</span></div>
                <div class="absolute bottom-0 left-0 h-1 bg-sky-500/20 w-full"></div>
            </div>
            <div class="security-card p-10">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Institutional Punctuality</p>
                <?php $rate = $stats['total_present'] > 0 ? round(($stats['on_time_total'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-emerald-500 tracking-tighter stat-glow"><?= $rate ?><span class="text-3xl text-emerald-200">%</span></div>
            </div>
            <div class="security-card p-10">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Location Compliance</p>
                <?php $comp_rate = $stats['total_present'] > 0 ? round(($stats['compliant'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-sky-400 tracking-tighter stat-glow"><?= $comp_rate ?><span class="text-3xl text-sky-200">%</span></div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-full">
                <div class="px-6 py-4 flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-black text-slate-600 uppercase tracking-[0.4em]">Administrative Transaction Ledger</h3>
                    <div class="text-[8px] font-bold text-slate-500 uppercase tracking-[0.3em] italic">Displaying last 50 audit entries</div>
                </div>

                <div class="security-manifest-wrapper">
                    <table class="security-manifest-table security-manifest-table-lg">
                        <thead>
                            <tr class="text-[9px] font-black uppercase tracking-widest text-slate-600">
                                <th class="px-12 py-4 text-left">Date & Global Time</th>
                                <th class="px-12 py-4 text-left">Timing Audit</th>
                                <th class="px-12 py-4 text-left">Compliance Hub</th>
                                <th class="px-12 py-4 text-center">Reference Node</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $log): ?>
                                <tr>
                                    <td>
                                        <div class="font-bold text-white tracking-tight text-lg mb-1 leading-none"><?= date('l, F j, Y', strtotime($log['check_in_time'])) ?></div>
                                        <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest leading-none"><?= date('H:i:s', strtotime($log['check_in_time'])) ?> UTC IDENTIFIED</div>
                                    </td>
                                    <td>
                                        <span class="security-status-badge <?= $log['p_status'] === 'Late' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' ?>"><?= $log['p_status'] ?></span>
                                    </td>
                                    <td>
                                        <div class="flex flex-col gap-1.5">
                                            <span class="security-status-badge <?= $log['geofence_status'] === 'Violation' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-sky-500/10 text-sky-400 border border-sky-500/20' ?>"><?= $log['geofence_status'] ?></span>
                                            <div class="text-[9px] text-slate-600 font-bold"><?= $log['distance_m'] ?>m from center</div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-800 border border-white/5 text-slate-500 hover:text-white hover:bg-sky-500 transition-all shadow-lg">
                                            <i class="fas fa-satellite"></i>
                                        </a>
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
