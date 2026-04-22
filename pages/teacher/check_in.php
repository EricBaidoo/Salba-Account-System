<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

// Institutional Geography (Managed in Admin Settings)
$school_lat = floatval(getSystemSetting($conn, 'attendance_lat', '5.5786875'));
$school_lng = floatval(getSystemSetting($conn, 'attendance_lng', '-0.2911875'));
$allowed_radius_meters = intval(getSystemSetting($conn, 'attendance_radius', '300'));

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

$diagnostic_data = $_SESSION['last_diagnostic'] ?? null;
unset($_SESSION['last_diagnostic']);
$uid = $_SESSION['user_id'];

// Check attendance state for today
$today = date('Y-m-d');
$attendance_record = null;
$q = $conn->query("SELECT id, check_in_time, check_out_time FROM staff_attendance WHERE user_id = $uid AND DATE(check_in_time) = '$today' ORDER BY id DESC LIMIT 1");
if ($q->num_rows > 0) {
    $attendance_record = $q->fetch_assoc();
}
$already_checked_in = $attendance_record !== null;
$already_checked_out = $attendance_record && !empty($attendance_record['check_out_time']);

// Dashboard URL based on role
$dashboard_url = BASE_URL . 'pages/teacher/dashboard';
if ($_SESSION['role'] === 'supervisor') $dashboard_url = BASE_URL . 'pages/supervisor/dashboard';
if ($_SESSION['role'] === 'admin') $dashboard_url = BASE_URL . 'pages/administration/dashboard';

// Read flash messages (redirect() stores in flash_messages, not $_SESSION['success'] directly)
$flash_messages = get_flash();
$flash_success = null;
$flash_error = null;
foreach ($flash_messages as $fm) {
    if ($fm['type'] === 'success' && !$flash_success) $flash_success = $fm['message'];
    if ($fm['type'] === 'error' && !$flash_error) $flash_error = $fm['message'];
}
// "Just clocked in" = checked in, not out, and a success flash was just set
$just_checked_in = $already_checked_in && !$already_checked_out && $flash_success !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['geocheckin', 'geocheckout'])) {
    $is_checkout = $_POST['action'] === 'geocheckout';
    
    if (!$is_checkout && $already_checked_in) {
        redirect('check_in.php', 'error', "You have already recorded your check-in for today.");
    } elseif ($is_checkout && (!$already_checked_in || $already_checked_out)) {
        redirect('check_in.php', 'error', "Invalid check-out request.");
    } else {
        $u_lat = floatval($_POST['lat']);
        $u_lng = floatval($_POST['lng']);
        $u_acc = floatval($_POST['accuracy'] ?? 0);
        
        $diag = [
            'detected' => "$u_lat, $u_lng",
            'target' => "$school_lat, $school_lng",
            'distance' => 0,
            'accuracy' => round($u_acc) . "m"
        ];

        if ($u_lat == 0 && $u_lng == 0) {
            redirect('check_in', 'error', "Campus Location Error: Please ensure location services are enabled on your device.");
        } else {
            $dist = getDistanceMeters($school_lat, $school_lng, $u_lat, $u_lng);
            $diag['distance'] = round($dist) . "m";
            $_SESSION['last_diagnostic'] = $diag;

            if ($dist <= $allowed_radius_meters || $_SESSION['role'] === 'admin') {
                // Bypass is strictly admin-only (server-side enforced)
                $is_bypass = isset($_POST['bypass']) && $_SESSION['role'] === 'admin';
                $notes = $is_bypass ? "Admin Manual Attendance Override" : "";
                
                // Accuracy check — reject unreliable GPS fixes (unless admin bypass)
                $max_acceptable_accuracy = 150; // meters
                if (!$is_bypass && $u_acc > $max_acceptable_accuracy && $u_acc > 0) {
                    log_activity($conn, 'Attendance Warning', "Low accuracy GPS fix rejected for " . $_SESSION['username'] . " (accuracy: {$u_acc}m)", null, $diag);
                    redirect('check_in.php', 'error', "GPS accuracy too low (±" . round($u_acc) . "m). Please move to an open area and try again.");
                }
                
                if ($is_checkout) {
                    $stmt = $conn->prepare("UPDATE staff_attendance SET check_out_time = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $attendance_record['id']);
                } else {
                    $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude, accuracy, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
                    $stmt->bind_param("iddds", $uid, $u_lat, $u_lng, $u_acc, $notes);
                }

                if ($stmt->execute()) {
                    $msg = $is_bypass ? "Manual attendance record successfully authenticated." : ($is_checkout ? "Departure recorded. Have a great day!" : "Campus presence verified. Welcome to school!");
                    
                    // AUDIT LOG
                    $action_name = $is_checkout ? "staff check-out" : "Staff check-in";
                    log_activity($conn, 'Attendance', "$action_name recorded. User: " . $_SESSION['username'] . " (Distance: " . round($dist) . "m)", null, $diag);
                    
                    redirect('check_in.php', 'success', $msg);
                } else {
                    redirect('check_in.php', 'error', "An error occurred while saving your attendance record.");
                }
            } else {
                // SECURITY AUDIT LOG for violation
                log_activity($conn, 'Security Alert', "Geofence Violation: Staff attempted check-in from " . round($dist) . "m away.", null, $diag);
                
                redirect('check_in', 'error', "Location Verification Failed: You appear to be outside the campus boundaries.");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance Hub | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="min-h-screen font-sans overflow-x-hidden bg-security">

    <!-- Clean Light Header -->
    <header class="w-full bg-white border-b border-slate-100 shadow-sm px-6 md:px-12 py-4 fixed top-0 z-50 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= $dashboard_url ?>" class="w-10 h-10 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-all">
                <i class="fas fa-chevron-left text-sm"></i>
            </a>
            <div class="hidden sm:block">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] block leading-none mb-1 text-left">Salba Montessori</span>
                <span class="text-xs font-black text-slate-700">Staff Attendance Portal</span>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>pages/common/profile.php" class="flex items-center gap-3 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition-all group">
                <div class="text-right hidden xs:block">
                    <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">Logged in as</p>
                    <p class="text-[11px] font-black text-slate-800 leading-none"><?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 border border-indigo-100">
                    <i class="fas fa-user-shield text-sm"></i>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>logout" class="w-10 h-10 bg-rose-50 text-rose-500 border border-rose-100 rounded-xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all" title="Logout">
                <i class="fas fa-power-off text-sm"></i>
            </a>
        </div>
    </header>

    <main class="min-h-screen flex items-center justify-center p-3 sm:p-6 pt-24 pb-12">
        
        <div class="w-full max-w-md">
            
            <div class="security-card security-card-ribbon p-6 sm:p-12 md:p-14 rounded-[2.5rem] sm:rounded-[3rem]">
                
                <div class="mb-10 text-center px-2">
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-slate-400 mb-2">Institutional Management</p>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-800 leading-tight uppercase">Staff Clock-in Portal</h1>
                </div>
                
                <?php if($flash_success && !$just_checked_in): ?>
                <div class="mb-8 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-[10px] font-black uppercase tracking-widest text-emerald-700 text-center">
                    <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($flash_success) ?>
                </div>
            <?php endif; ?>
            
            <?php if($flash_error): ?>
                <div class="mb-8 p-4 bg-rose-50 border border-rose-200 rounded-2xl text-[10px] font-black uppercase tracking-widest text-rose-700 text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($flash_error) ?>
                </div>
            <?php endif; ?>

            <div class="relative z-10 text-center">
                <?php if($already_checked_in && $already_checked_out): ?>
                    <!-- Verification Successful (Full Day) -->
                    <div class="identity-lens pill-emerald mb-8 scale-110 !border-white/20">
                        <i class="fas fa-check-double text-5xl text-white drop-shadow-md"></i>
                    </div>
                    <h2 class="text-2xl font-black text-slate-800 mb-2 uppercase tracking-tight">Shift Complete</h2>
                    <div class="flex flex-col gap-2 mb-10">
                        <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest leading-relaxed bg-emerald-50 py-2 rounded-lg border border-emerald-100">Check In: <?= date('H:i', strtotime($attendance_record['check_in_time'])) ?></p>
                        <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-widest leading-relaxed bg-emerald-50 py-2 rounded-lg border border-emerald-100">Check Out: <?= date('H:i', strtotime($attendance_record['check_out_time'])) ?></p>
                    </div>
                    
                    <div class="pt-8 border-t border-slate-700/50">
                        <a href="<?= BASE_URL ?>index" class="text-sky-400 font-bold text-[10px] uppercase tracking-widest hover:text-white transition-colors">Return to Terminal</a>
                    </div>
                <?php elseif($just_checked_in): ?>
                    <!-- ✅ Post Clock-In Success State -->
                    <div class="identity-lens mb-8 border-emerald-500 bg-emerald-500/10" style="animation: pulse 2s infinite;">
                        <i class="fas fa-circle-check text-5xl text-emerald-500"></i>
                    </div>

                    <div class="space-y-1 mb-6">
                        <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Attendance Recorded!</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest leading-relaxed">Campus presence verified &amp; logged</p>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-100 rounded-2xl p-5 mb-10 text-center">
                        <p class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mb-1">Check-In Time</p>
                        <p class="text-2xl font-black text-emerald-700"><?= date('H:i', strtotime($attendance_record['check_in_time'])) ?></p>
                        <p class="text-[9px] font-bold text-emerald-400 uppercase tracking-widest mt-1"><?= date('l, F j, Y', strtotime($attendance_record['check_in_time'])) ?></p>
                    </div>

                    <div class="space-y-3">
                        <a href="<?= $dashboard_url ?>" class="w-full bg-gradient-to-r from-emerald-600 to-emerald-500 text-white font-black text-[11px] uppercase tracking-[0.2em] py-5 rounded-2xl hover:brightness-110 active:scale-[0.98] transition-all shadow-md flex items-center justify-center gap-3">
                            <i class="fas fa-home"></i> Return to Dashboard
                        </a>
                        <a href="check_in.php" class="block text-center text-[9px] font-black text-slate-400 uppercase tracking-widest hover:text-amber-500 transition-colors py-2">
                            <i class="fas fa-sign-out-alt mr-1"></i> Record Departure Now &rarr;
                        </a>
                    </div>

                <?php elseif($already_checked_in && !$already_checked_out): ?>
                    <!-- Waiting for Check out -->
                    <div id="authVisual" class="identity-lens mb-10 border-amber-500 bg-amber-500/10">
                        <i class="fas fa-person-walking-arrow-right text-5xl text-amber-500 transition-all"></i>
                    </div>
                    
                    <div class="space-y-1 mb-10">
                        <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Ready to Clock Out</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest leading-relaxed">Checked in at <?= date('H:i', strtotime($attendance_record['check_in_time'])) ?> &middot; <?= $allowed_radius_meters ?>M Radius</p>
                    </div>

                    <!-- High-Visibility HUD -->
                    <div class="grid grid-cols-3 gap-2 mb-10">
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">GPS</p>
                            <span class="status-pill-solid pill-emerald !text-[8px] !py-1 !px-3">LOCK</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">SIGNAL</p>
                            <span class="status-pill-solid pill-sky !text-[8px] !py-1 !px-3">ACTIVE</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">AUTH</p>
                            <span class="status-pill-solid pill-amber !text-[8px] !py-1 !px-3">AES-256</span>
                        </div>
                    </div>

                    <div class="mt-12">
                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckout">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            <input type="hidden" name="accuracy" id="accuracy" value="0">
                            
                            <button type="button" onclick="initiateCheckIn()" id="authBtn" class="w-full bg-gradient-to-r from-amber-600 to-amber-500 text-white font-black text-[11px] uppercase tracking-[0.2em] py-5 rounded-2xl hover:brightness-110 active:scale-[0.98] transition-all shadow-md flex items-center justify-center gap-3">
                                <i class="fas fa-sign-out-alt"></i> Record Departure
                            </button>
                        </form>
                        
                        <div id="status-msg" class="mt-6 text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] hidden">
                            <i class="fas fa-satellite-dish fa-spin mr-2 text-amber-500"></i> <span id="status-text">verifying location status...</span>
                        </div>

                        <a href="<?= $dashboard_url ?>" class="block text-center mt-5 text-[9px] font-black text-slate-400 uppercase tracking-widest hover:text-sky-400 transition-colors py-2">
                            <i class="fas fa-home mr-1"></i> Return to Dashboard
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Awaiting Authentication -->
                    <div id="authVisual" class="identity-lens mb-10">
                        <i class="fas fa-fingerprint text-5xl text-sky-400 transition-all"></i>
                    </div>
                    
                    <div class="space-y-1 mb-10">
                        <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Ready to Clock In</h2>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest leading-relaxed">Authorized Area: <?= $allowed_radius_meters ?>M Radius</p>
                    </div>

                    <!-- High-Visibility HUD -->
                    <div class="grid grid-cols-3 gap-2 mb-10">
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">GPS</p>
                            <span class="status-pill-solid pill-emerald !text-[8px] !py-1 !px-3">LOCK</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">SIGNAL</p>
                            <span class="status-pill-solid pill-sky !text-[8px] !py-1 !px-3">ACTIVE</span>
                        </div>
                        <div class="p-4 bg-white/5 rounded-2xl border border-white/5 backdrop-blur-sm">
                            <p class="hud-stat mb-2">AUTH</p>
                            <span class="status-pill-solid pill-indigo !text-[8px] !py-1 !px-3">AES-256</span>
                        </div>
                    </div>

                    <div class="mt-12">
                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckin">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            <input type="hidden" name="accuracy" id="accuracy" value="0">
                            
                            <button type="button" onclick="initiateCheckIn()" id="authBtn" class="w-full bg-gradient-to-r from-indigo-600 to-indigo-500 text-white font-black text-[11px] uppercase tracking-[0.2em] py-5 rounded-2xl hover:brightness-110 active:scale-[0.98] transition-all shadow-md flex items-center justify-center gap-3">
                                <i class="fas fa-clock"></i> Record My Attendance
                            </button>
                        </form>
                        
                        <div id="status-msg" class="mt-8 text-[9px] font-black text-slate-400 uppercase tracking-[0.2em] hidden">
                            <i class="fas fa-satellite-dish fa-spin mr-2 text-indigo-500"></i> <span id="status-text">verifying location status...</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Diagnostic Feedback (Perimeter Audit) -->
        <?php if($diagnostic_data): 
            $dist_val = intval($diagnostic_data['distance']);
            $is_breach = $dist_val > $allowed_radius_meters;
            $theme_color = $is_breach ? 'rose' : 'sky';
            $audit_icon = $is_breach ? 'fa-shield-halved' : 'fa-circle-check';
            $audit_title = $is_breach ? 'Perimeter Breach Detected' : 'Verification Precision Audit';
        ?>
            <div class="mt-8 bg-<?= $theme_color ?>-500/5 p-8 rounded-[2rem] border border-<?= $theme_color ?>-500/20">
                <div class="flex items-center gap-4 mb-6 text-<?= $theme_color ?>-400">
                    <i class="fas <?= $audit_icon ?> text-xl"></i>
                    <h3 class="text-[10px] font-black uppercase tracking-widest"><?= $audit_title ?></h3>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-900/50 rounded-xl border border-slate-700/50">
                        <p class="text-[8px] font-black text-slate-600 uppercase tracking-widest mb-1">Variance</p>
                        <p class="text-xs font-bold text-white"><?= $diagnostic_data['distance'] ?></p>
                    </div>
                    <div class="p-4 bg-slate-900/50 rounded-xl border border-slate-700/50">
                        <p class="text-[8px] font-black text-slate-600 uppercase tracking-widest mb-1">Confidence</p>
                        <p class="text-xs font-bold text-white">±<?= $diagnostic_data['accuracy'] ?></p>
                    </div>
                </div>

                <?php if($_SESSION['role'] === 'admin'): ?>
                    <div class="mt-6 pt-6 border-t border-slate-700/50 text-center">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= ($already_checked_in && !$already_checked_out) ? 'geocheckout' : 'geocheckin' ?>">
                            <input type="hidden" name="lat" value="<?= $school_lat ?>">
                            <input type="hidden" name="lng" value="<?= $school_lng ?>">
                            <input type="hidden" name="accuracy" value="0">
                            <input type="hidden" name="bypass" value="1">
                            <button type="submit" class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-sky-400 transition-colors">
                                Admin Manual Override
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="mt-12 text-center text-[7px] sm:text-[8px] font-bold text-slate-400 uppercase tracking-[0.2em] sm:tracking-[0.4em] px-4">Standard School Attendance Protocol Active</p>
    </main>

    <script>
        if (!window.isSecureContext) {
            document.getElementById('authBtn').classList.add('opacity-30', 'pointer-events-none');
        }

        function initiateCheckIn() {
            const btn = document.getElementById('authBtn');
            const visual = document.getElementById('authVisual');
            const status = document.getElementById('status-msg');
            const statusText = document.getElementById('status-text');
            
            btn.classList.add('opacity-50', 'pointer-events-none');
            visual.classList.add('border-indigo-500', 'bg-indigo-500/10');
            visual.querySelector('i').classList.replace('text-sky-400', 'text-indigo-400');
            status.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                statusText.innerText = "Verifying campus location...";
                
                navigator.geolocation.getCurrentPosition(
                    handleSuccess,
                    function(err) {
                        statusText.innerText = "Signal low, retrying (no cache)...";
                        navigator.geolocation.getCurrentPosition(
                            handleSuccess,
                            handleFailure,
                            { enableHighAccuracy: false, timeout: 15000, maximumAge: 0 }
                        );
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            } else {
                handleFailure({message: "Your device does not support location verification."});
            }
        }

        function handleSuccess(position) {
            document.getElementById('lat').value = position.coords.latitude;
            document.getElementById('lng').value = position.coords.longitude;
            document.getElementById('accuracy').value = position.coords.accuracy;
            document.getElementById('checkInForm').submit();
        }

        function handleFailure(error) {
            alert("Verification Failed: " + (error.code === 1 ? "Location permission denied." : "Could not verify location. Please try again."));
            location.reload();
        }
    </script>
</body>
</html>

<?php
// ... end of file
?>
