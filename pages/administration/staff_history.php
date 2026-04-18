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

// Deletion Handler (Admin Protocol)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_attendance') {
    $log_id = intval($_POST['log_id']);
    $conn->query("DELETE FROM staff_attendance WHERE id = $log_id");
    log_activity($conn, 'Security Audit', "Personnel history record #$log_id purged from ledger.", $_SESSION['user_id']);
    $_SESSION['success'] = "Resource purged successfully.";
    header("Location: staff_history.php?user_id=$user_id");
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
    <title><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?> | Staff Attendance History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="font-sans bg-security">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-6 md:p-12 min-h-screen">
        <nav class="mb-10 flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-slate-400">
            <a href="dashboard.php" class="hover:text-indigo-600 transition-colors">Dashboard</a>
            <i class="fas fa-chevron-right text-[7px] text-slate-300"></i>
            <a href="staff_attendance.php" class="hover:text-indigo-600 transition-colors">Attendance Hub</a>
            <i class="fas fa-chevron-right text-[7px] text-slate-300"></i>
            <span class="text-indigo-500 italic">Attendance History</span>
        </nav>

        <header class="mb-14 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="flex items-center gap-10">
                <div class="w-32 h-32 rounded-[2.5rem] bg-white border-4 border-slate-200 shadow-sm overflow-hidden flex-shrink-0 group relative">
                    <?php if($staff['photo_path']): ?><img src="../../<?= htmlspecialchars($staff['photo_path']) ?>" class="w-full h-full object-cover">
                    <?php else: ?><div class="w-full h-full flex items-center justify-center text-4xl text-slate-300"><i class="fas fa-user-shield"></i></div><?php endif; ?>
                    <div class="absolute inset-0 border border-slate-200 pointer-events-none rounded-[2.5rem]"></div>
                </div>
                <div class="text-center md:text-left">
                    <h1 class="text-4xl sm:text-5xl font-black text-slate-900 tracking-tighter leading-tight mb-2 uppercase"><?= htmlspecialchars($staff['full_name'] ?: $staff['username']) ?></h1>
                    <div class="flex items-center gap-3 justify-center md:justify-start">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-[0.2em] border border-indigo-100">Authorized Faculty</span>
                        <p class="text-slate-500 text-sm font-bold tracking-tight uppercase tracking-widest"><?= htmlspecialchars($staff['job_title'] ?: 'Academic Personnel') ?></p>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-col items-center md:items-end gap-6">
                <?php if(isset($_SESSION['success'])): ?>
                    <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-xl text-[10px] font-black uppercase tracking-widest text-emerald-600">
                        <i class="fas fa-check-circle mr-2"></i> <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <button onclick="window.print()" class="bg-indigo-600 text-white text-[10px] font-black uppercase tracking-[0.3em] px-8 py-5 rounded-2xl border border-indigo-500 hover:bg-indigo-700 transition-all flex items-center gap-3 shadow-sm">
                    <i class="fas fa-print"></i> Download Attendance Report
                </button>
            </div>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
            <div class="security-card card-vivid-indigo p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-3xl group-hover:bg-white/20 transition-all"></div>
                <p class="text-[10px] font-black text-indigo-100 uppercase tracking-[0.2em] mb-4">Total Days Present</p>
                <div class="text-6xl font-black text-white tracking-tight"><?= $stats['total_present'] ?> <span class="text-xl font-bold text-indigo-200">DAYS</span></div>
                <div class="absolute bottom-0 left-0 h-1 bg-white/20 w-full"></div>
            </div>
            <div class="security-card card-vivid-emerald p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-emerald-100 uppercase tracking-[0.2em] mb-4">Punctuality Rate</p>
                <?php $rate = $stats['total_present'] > 0 ? round(($stats['on_time_total'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-white tracking-tighter"><?= $rate ?><span class="text-3xl text-emerald-200">%</span></div>
            </div>
            <div class="security-card card-vivid-sky p-10 relative overflow-hidden group">
                <div class="absolute -right-8 -top-8 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-sky-100 uppercase tracking-[0.2em] mb-4">Location Compliance</p>
                <?php $comp_rate = $stats['total_present'] > 0 ? round(($stats['compliant'] / $stats['total_present']) * 100) : 0; ?>
                <div class="text-6xl font-black text-white tracking-tighter"><?= $comp_rate ?><span class="text-3xl text-sky-200">%</span></div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <div class="min-w-full">
                <div class="px-6 py-4 flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Personal Attendance Log</h3>
                    <div class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.3em] italic">Displaying last 50 entries</div>
                </div>

                <div class="security-manifest-wrapper">
                    <table class="security-manifest-table security-manifest-table-lg">
                        <thead>
                            <tr class="text-[9px] font-black uppercase tracking-widest text-slate-400">
                                <th class="px-12 py-4 text-left">Date & Time</th>
                                <th class="px-12 py-4 text-left">Punctuality</th>
                                <th class="px-12 py-4 text-left">Location Check</th>
                                <th class="px-12 py-4 text-center">Map</th>
                                <th class="px-12 py-4 text-right">Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($history as $log): ?>
                                <tr>
                                    <td class="px-12 py-6">
                                        <div class="font-bold text-slate-900 tracking-tight text-lg mb-1 leading-none"><?= date('l, F j, Y', strtotime($log['check_in_time'])) ?></div>
                                        <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest leading-none"><?= date('H:i:s', strtotime($log['check_in_time'])) ?> UTC IDENTIFIED</div>
                                    </td>
                                    <td class="px-12 py-6">
                                        <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $log['p_status'] === 'Late' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' ?>">
                                            <i class="fas <?= $log['p_status'] === 'Late' ? 'fa-clock' : 'fa-circle-check' ?>"></i> <?= $log['p_status'] ?>
                                        </span>
                                    </td>
                                    <td class="px-12 py-6">
                                        <div class="flex flex-col gap-2">
                                            <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $log['geofence_status'] === 'Violation' ? 'bg-rose-50 text-rose-600' : 'bg-sky-50 text-sky-600' ?>">
                                                <i class="fas <?= $log['geofence_status'] === 'Violation' ? 'fa-triangle-exclamation' : 'fa-hand-shield' ?>"></i> <?= $log['geofence_status'] ?>
                                            </span>
                                            <div class="text-[9px] text-slate-400 font-black uppercase italic px-2"><?= $log['distance_m'] ?>m from center</div>
                                        </div>
                                    </td>
                                    <td class="px-12 py-6 text-center">
                                        <a href="https://www.google.com/maps?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-slate-50 border border-slate-200 text-slate-400 hover:text-white hover:bg-indigo-600 transition-all">
                                            <i class="fas fa-satellite"></i>
                                        </a>
                                    </td>
                                    <td class="text-right">
                                        <form method="POST" onsubmit="return confirm('CRITICAL: Purge this historical record?')" class="inline">
                                            <input type="hidden" name="action" value="delete_attendance">
                                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                            <button type="submit" class="w-10 h-10 inline-flex items-center justify-center rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-lg" title="Purge Record">
                                                <i class="fas fa-trash-can"></i>
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
    </main>
</body>
</html>

<?php
// ... end of file
?>
