<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || !in_array($_SESSION['role'], ['facilitator', 'supervisor', 'admin'])) {
    header('Location: ../../login');
    exit;
}

$uid = $_SESSION['user_id'];

// Dashboard URL per role
$dashboard_url = BASE_URL . 'pages/teacher/dashboard';
if ($_SESSION['role'] === 'supervisor') $dashboard_url = BASE_URL . 'pages/supervisor/dashboard';
if ($_SESSION['role'] === 'admin') $dashboard_url = BASE_URL . 'pages/administration/dashboard';

// Punctuality rules from system settings
$early_limit_val  = getSystemSetting($conn, 'attendance_early_limit', '06:30');
$ontime_limit_val = getSystemSetting($conn, 'attendance_ontime_limit', '07:00');
$early_limit  = date('H:i:s', strtotime($early_limit_val));
$ontime_limit = date('H:i:s', strtotime($ontime_limit_val));

// Month filter
$selected_month = $_GET['month'] ?? date('Y-m');
$month_start = $selected_month . '-01';
$month_end   = date('Y-m-t', strtotime($month_start));

// Geofence settings (for compliance check)
$school_lat = floatval(getSystemSetting($conn, 'attendance_lat', '5.5786875'));
$school_lng = floatval(getSystemSetting($conn, 'attendance_lng', '-0.2911875'));
$allowed_radius = intval(getSystemSetting($conn, 'attendance_radius', '300'));

function _my_att_distance($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $r = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
    return $r * 2 * asin(sqrt($a));
}

// Fetch profile
$prof = $conn->query("SELECT full_name, photo_path, job_title FROM staff_profiles WHERE user_id = $uid LIMIT 1")->fetch_assoc();
$display_name = $prof['full_name'] ?? $_SESSION['username'];
$job_title    = $prof['job_title'] ?? ucfirst($_SESSION['role']);
$photo        = $prof['photo_path'] ?? '';

// Fetch ALL history (for lifetime stats)
$all_res = $conn->query("SELECT * FROM staff_attendance WHERE user_id = $uid ORDER BY check_in_time DESC");
$all_history = [];
$stats = ['total' => 0, 'on_time' => 0, 'compliant' => 0, 'this_month' => 0];

if ($all_res) {
    while ($row = $all_res->fetch_assoc()) {
        $t = date('H:i:s', strtotime($row['check_in_time']));
        if ($t < $early_limit)       { $row['p_status'] = 'Early';   $stats['on_time']++; }
        elseif ($t < $ontime_limit)  { $row['p_status'] = 'On-Time'; $stats['on_time']++; }
        else                          { $row['p_status'] = 'Late'; }

        $dist = _my_att_distance($school_lat, $school_lng, $row['latitude'], $row['longitude']);
        $row['distance_m'] = round($dist);
        $row['geofence_ok'] = $dist <= $allowed_radius;
        if ($row['geofence_ok']) $stats['compliant']++;

        // Duration (if checked out)
        $row['duration'] = null;
        if (!empty($row['check_out_time'])) {
            $mins = (strtotime($row['check_out_time']) - strtotime($row['check_in_time'])) / 60;
            $row['duration'] = sprintf('%dh %02dm', floor($mins / 60), $mins % 60);
        }

        if (date('Y-m', strtotime($row['check_in_time'])) === date('Y-m')) $stats['this_month']++;

        $all_history[] = $row;
        $stats['total']++;
    }
}

$punctuality_rate = $stats['total'] > 0 ? round(($stats['on_time'] / $stats['total']) * 100) : 0;

// Filtered history for table (by selected month)
$filtered = array_filter($all_history, fn($r) => date('Y-m', strtotime($r['check_in_time'])) === $selected_month);
$filtered = array_values($filtered);

// Build month options (last 12 months)
$month_options = [];
for ($i = 0; $i < 12; $i++) {
    $m = date('Y-m', strtotime("-$i months"));
    $month_options[] = $m;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance History | SALBA Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .admin-main-content { margin-left: 0 !important; padding: 20px !important; }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="min-h-screen p-4 md:p-10 pt-20 md:pt-24">

        <!-- Breadcrumb -->
        <nav class="mb-10 flex items-center gap-4 text-[10px] font-black uppercase tracking-widest text-slate-400 no-print">
            <a href="<?= $dashboard_url ?>" class="hover:text-indigo-600 transition-colors">Dashboard</a>
            <i class="fas fa-chevron-right text-[7px] text-slate-300"></i>
            <span class="text-indigo-500 italic">My Attendance History</span>
        </nav>

        <!-- Header -->
        <header class="mb-14 flex flex-col md:flex-row justify-between items-start md:items-center gap-8">
            <div class="flex items-center gap-8">
                <div class="w-24 h-24 rounded-[2rem] bg-white border-4 border-slate-200 shadow-sm overflow-hidden flex-shrink-0">
                    <?php if($photo): ?>
                        <img src="../../<?= htmlspecialchars($photo) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-3xl text-slate-300">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 mb-1">Personal Attendance Record</p>
                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight uppercase leading-tight"><?= htmlspecialchars($display_name) ?></h1>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-[9px] font-black uppercase tracking-[0.2em] border border-indigo-100"><?= htmlspecialchars($job_title) ?></span>
                    </div>
                </div>
            </div>
            <div class="flex gap-3 no-print">
                <button onclick="window.print()" class="bg-slate-900 text-white font-black text-[10px] uppercase tracking-widest px-6 py-4 rounded-2xl hover:bg-slate-800 transition-all flex items-center gap-2">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-14">
            <div class="bg-white rounded-2xl border border-indigo-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-28 h-28 bg-indigo-50 rounded-full blur-3xl group-hover:bg-indigo-100 transition-all"></div>
                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-3">Total Days Present</p>
                <div class="text-5xl font-black text-slate-900 tracking-tight"><?= $stats['total'] ?> <span class="text-lg font-bold text-slate-400">DAYS</span></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2">This Month: <span class="text-indigo-600"><?= $stats['this_month'] ?></span></p>
            </div>
            <div class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-28 h-28 bg-emerald-50 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-emerald-600 uppercase tracking-[0.2em] mb-3">Punctuality Rate</p>
                <div class="text-5xl font-black text-slate-900 tracking-tighter"><?= $punctuality_rate ?><span class="text-2xl text-emerald-500">%</span></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2"><?= $stats['on_time'] ?> / <?= $stats['total'] ?> on time or early</p>
            </div>
            <div class="bg-white rounded-2xl border border-sky-100 shadow-sm p-8 relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-28 h-28 bg-sky-50 rounded-full blur-3xl"></div>
                <p class="text-[10px] font-black text-sky-600 uppercase tracking-[0.2em] mb-3">Location Compliance</p>
                <?php $comp_rate = $stats['total'] > 0 ? round(($stats['compliant'] / $stats['total']) * 100) : 0; ?>
                <div class="text-5xl font-black text-slate-900 tracking-tighter"><?= $comp_rate ?><span class="text-2xl text-sky-500">%</span></div>
                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-2"><?= $stats['compliant'] ?> / <?= $stats['total'] ?> within campus radius</p>
            </div>
        </div>

        <!-- Month Filter -->
        <div class="no-print bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-8 flex items-center gap-6 flex-wrap">
            <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Filter by Month</p>
            <form method="GET" class="flex items-center gap-4">
                <select name="month" onchange="this.form.submit()" class="bg-slate-50 border border-slate-100 rounded-xl px-4 py-2 text-xs font-bold text-slate-700 outline-none focus:ring-2 focus:ring-indigo-500">
                    <?php foreach ($month_options as $mo): ?>
                        <option value="<?= $mo ?>" <?= $selected_month === $mo ? 'selected' : '' ?>>
                            <?= date('F Y', strtotime($mo . '-01')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <?= count($filtered) ?> record<?= count($filtered) !== 1 ? 's' : '' ?>
                </span>
            </form>
        </div>

        <!-- Attendance Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">
                    Attendance Log — <?= date('F Y', strtotime($selected_month . '-01')) ?>
                </h3>
                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.3em] italic">Last 50 entries max shown</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[9px] font-black uppercase tracking-widest text-slate-400 bg-slate-50 border-b border-slate-100">
                            <th class="px-8 py-4">Date</th>
                            <th class="px-6 py-4">Clock In</th>
                            <th class="px-6 py-4">Clock Out</th>
                            <th class="px-6 py-4">Duration</th>
                            <th class="px-6 py-4">Punctuality</th>
                            <th class="px-6 py-4">Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered)): ?>
                            <tr>
                                <td colspan="6" class="px-8 py-16 text-center">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center text-slate-200 text-2xl mx-auto mb-4">
                                        <i class="fas fa-calendar-xmark"></i>
                                    </div>
                                    <p class="text-sm font-black text-slate-400 uppercase tracking-wide">No records for <?= date('F Y', strtotime($selected_month . '-01')) ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($filtered as $log): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors border-b border-slate-50">
                                    <td class="px-8 py-5">
                                        <div class="font-black text-slate-800 text-sm leading-tight"><?= date('l', strtotime($log['check_in_time'])) ?></div>
                                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1"><?= date('M j, Y', strtotime($log['check_in_time'])) ?></div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="text-[11px] font-black text-slate-700 uppercase tracking-widest"><?= date('H:i', strtotime($log['check_in_time'])) ?></span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?php if($log['check_out_time']): ?>
                                            <span class="text-[11px] font-black text-slate-700 uppercase tracking-widest"><?= date('H:i', strtotime($log['check_out_time'])) ?></span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-black text-amber-500 uppercase tracking-widest italic">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                            <?= $log['duration'] ?? '—' ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <?php
                                        $p = $log['p_status'];
                                        $pill_class = $p === 'Late' ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600';
                                        $pill_icon = $p === 'Late' ? 'fa-clock' : 'fa-circle-check';
                                        ?>
                                        <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $pill_class ?>">
                                            <i class="fas <?= $pill_icon ?>"></i> <?= $p ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="flex flex-col gap-1">
                                            <span class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest <?= $log['geofence_ok'] ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600' ?>">
                                                <i class="fas <?= $log['geofence_ok'] ? 'fa-hand-shield' : 'fa-triangle-exclamation' ?>"></i>
                                                <?= $log['geofence_ok'] ? 'Compliant' : 'Violation' ?>
                                            </span>
                                            <?php if($log['latitude']): ?>
                                            <span class="text-[8px] text-slate-400 font-bold uppercase italic px-1"><?= $log['distance_m'] ?>m from campus</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-10 text-center no-print">
            <a href="<?= $dashboard_url ?>" class="text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-indigo-600 transition-colors">
                <i class="fas fa-home mr-2"></i> Return to Dashboard
            </a>
        </div>
    </main>
</body>
</html>
