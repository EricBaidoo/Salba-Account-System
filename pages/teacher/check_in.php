<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

// Institutional Geofence Calibration (Pulled from System Settings)
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

// Check if already checked in today
$today = date('Y-m-d');
$already = $conn->query("SELECT id FROM staff_attendance WHERE user_id = $uid AND DATE(check_in_time) = '$today'")->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'geocheckin') {
    if ($already) {
        redirect('check_in', 'error', "You have already clocked in for today.");
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
            redirect('check_in', 'error', "Institutional Verification Failed: Could not acquire a valid geographic signature.");
        } else {
            $dist = getDistanceMeters($school_lat, $school_lng, $u_lat, $u_lng);
            $diag['distance'] = round($dist) . "m";
            $_SESSION['last_diagnostic'] = $diag;

            if ($dist <= $allowed_radius_meters || $_SESSION['role'] === 'admin') {
                $notes = isset($_POST['bypass']) ? "Administrative Manual Override (Geofence Bypass)" : "";
                $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude, accuracy, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param("iddds", $uid, $u_lat, $u_lng, $u_acc, $notes);
                if ($stmt->execute()) {
                    $msg = isset($_POST['bypass']) ? "Administrative Presence Authenticated via Manual Override." : "Presence verified! Distance: " . round($dist) . "m";
                    
                    // AUDIT LOG
                    log_activity($conn, 'Attendance', "Staff check-in verified. User: " . $_SESSION['username'] . " (Distance: " . round($dist) . "m, Accuracy: " . round($u_acc) . "m)", null, $diag);
                    
                    redirect('check_in', 'success', $msg);
                } else {
                    redirect('check_in', 'error', "Database error logging institutional record.");
                }
            } else {
                // SECURITY AUDIT LOG for violation
                log_activity($conn, 'Security Alert', "Geofence Violation: Staff attempted check-in from " . round($dist) . "m away.", null, $diag);
                
                redirect('check_in', 'error', "Geofence Boundary Violation: You are currently outside the authorized campus hub.");
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
    <title>Personnel Verification | Institutional Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .pulse-ring {
            position: relative;
        }
        .pulse-ring::before {
            content: '';
            position: absolute;
            inset: -15px;
            border: 2px solid currentColor;
            border-radius: inherit;
            opacity: 0;
            animation: pulse-out 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse-out {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.3); opacity: 0; }
        }
        .biometric-gradient {
            background: radial-gradient(circle at center, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
        }
    </style>
</head>
<body class="bg-[#f1f5f9] text-slate-900 min-h-screen">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="max-w-4xl mx-auto px-4 py-20">
        <div class="flex flex-col items-center mb-16 text-center">
            <span class="px-5 py-2 bg-slate-900 text-white rounded-full text-[10px] font-black uppercase tracking-[0.3em] mb-6 shadow-xl shadow-slate-900/10">
                Institutional Hub Presence
            </span>
            <h1 class="text-5xl font-black tracking-tighter text-slate-900 leading-tight">Digital Identity <br><span class="text-indigo-600">Verification Center</span></h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
            
            <!-- Left: Authentication Core -->
            <div class="lg:col-span-7">
                <div class="glass-panel p-12 rounded-[4rem] shadow-2xl relative overflow-hidden group">
                    <div class="absolute inset-0 biometric-gradient opacity-0 group-hover:opacity-100 transition-opacity duration-1000"></div>
                    
                    <div class="relative z-10 text-center">
                        <div class="mb-12">
                            <?php if($already): ?>
                                <div class="w-40 h-40 bg-emerald-50 text-emerald-600 rounded-[3.5rem] flex items-center justify-center mx-auto mb-8 shadow-inner border-8 border-white pulse-ring">
                                    <i class="fas fa-check-double text-6xl"></i>
                                </div>
                                <h2 class="text-2xl font-black tracking-tight text-slate-900 mb-2">Presence Verified</h2>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Your session is active for today</p>
                            <?php else: ?>
                                <div id="authVisual" class="w-40 h-40 bg-indigo-50 text-indigo-700 rounded-[3.5rem] flex items-center justify-center mx-auto mb-8 shadow-inner border-8 border-white transition-all duration-700">
                                    <i class="fas fa-fingerprint text-6xl"></i>
                                </div>
                                <h2 class="text-2xl font-black tracking-tight text-slate-900 mb-2">Awaiting Signature</h2>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Synchronize your GPS heartbeat</p>
                                
                                <div class="mt-12">
                                    <div id="unsupported-msg" class="hidden mb-6 p-6 bg-rose-50 text-rose-700 rounded-3xl border border-rose-100 text-[10px] font-black uppercase tracking-widest leading-relaxed">
                                        <i class="fas fa-shield-slash text-2xl mb-2"></i><br>
                                        Encryption failure: Geolocation requires HTTPS access.
                                    </div>

                                    <form id="checkInForm" method="POST">
                                        <input type="hidden" name="action" value="geocheckin">
                                        <input type="hidden" name="lat" id="lat" value="0">
                                        <input type="hidden" name="lng" id="lng" value="0">
                                        <input type="hidden" name="accuracy" id="accuracy" value="0">
                                        
                                        <button type="button" onclick="initiateAuthentication()" id="authBtn" class="w-full bg-slate-900 text-white font-black text-xl py-6 rounded-[2.5rem] hover:bg-indigo-600 hover:scale-[1.02] active:scale-95 transition-all duration-500 shadow-2xl flex items-center justify-center gap-4">
                                            <i class="fas fa-satellite-dish"></i> Start Authentication
                                        </button>
                                    </form>
                                    
                                    <div id="status-msg" class="mt-8 text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] hidden">
                                        <i class="fas fa-circle-notch fa-spin text-indigo-600 mr-2"></i> <span id="status-text">calibrating signal...</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <p class="mt-10 text-center text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">Institutional boundary: <?= $allowed_radius_meters ?> meters</p>
            </div>

            <!-- Right: Diagnostics & Map Link -->
            <div class="lg:col-span-5 space-y-8">
                
                <?php if($diagnostic_data): ?>
                    <div class="glass-panel p-8 rounded-[3rem] border-rose-200 border shadow-xl bg-rose-50/30">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-inner">
                                <i class="fas fa-triangle-exclamation"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-black text-slate-900 uppercase tracking-tight leading-none">Access Refused</h3>
                                <p class="text-[9px] text-rose-600 font-bold uppercase tracking-widest mt-1">Institutional Breach</p>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="p-4 bg-white/50 rounded-2xl border border-rose-100">
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Target vs Detected</p>
                                <p class="text-[11px] font-bold text-slate-700"><?= $diagnostic_data['distance'] ?> offset from campus hub</p>
                            </div>
                            <div class="p-4 bg-white/50 rounded-2xl border border-rose-100">
                                <p class="text-[8px] font-black text-slate-400 uppercase tracking-widest mb-1">Signal Precision</p>
                                <p class="text-[11px] font-bold text-slate-700">±<?= $diagnostic_data['accuracy'] ?> vertical drift</p>
                            </div>
                        </div>

                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="mt-8">
                                <form method="POST">
                                    <input type="hidden" name="action" value="geocheckin">
                                    <input type="hidden" name="lat" value="<?= $school_lat ?>">
                                    <input type="hidden" name="lng" value="<?= $school_lng ?>">
                                    <input type="hidden" name="accuracy" value="0">
                                    <input type="hidden" name="bypass" value="1">
                                    <button type="submit" class="w-full py-4 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-black transition-all shadow-xl">
                                        <i class="fas fa-key mr-2"></i> Administrative Bypass
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-panel p-10 rounded-[3rem] shadow-sm">
                        <div class="flex items-center gap-4 mb-8">
                            <i class="fas fa-shield-halved text-emerald-500 text-2xl"></i>
                            <h3 class="text-xs font-black text-slate-900 uppercase tracking-widest leading-none">System integrity</h3>
                        </div>
                        <p class="text-xs font-bold text-slate-400 leading-relaxed uppercase tracking-wider">Verification parameters are centrally managed by institutional administration. Ensure you have enabled high-precision GPS on your terminal before authentication.</p>
                        
                        <div class="mt-10 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Active Hub Status</span>
                            </div>
                            <p class="text-[10px] font-bold text-slate-400 leading-relaxed italic">Signal strength: verified<br>Clock drift: stabilized<br>Identity: secured</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        if (!window.isSecureContext) {
            document.getElementById('unsupported-msg').classList.remove('hidden');
            document.getElementById('authBtn').classList.add('opacity-30', 'pointer-events-none');
        }

        function initiateAuthentication() {
            const btn = document.getElementById('authBtn');
            const visual = document.getElementById('authVisual');
            const status = document.getElementById('status-msg');
            const statusText = document.getElementById('status-text');
            
            btn.classList.add('opacity-50', 'pointer-events-none');
            visual.classList.add('pulse-ring', 'bg-indigo-600', 'text-white');
            status.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                statusText.innerText = "Analyzing geographic signature...";
                
                navigator.geolocation.getCurrentPosition(
                    handleSuccess,
                    function(err) {
                        statusText.innerText = "Fallback: Attempting standard verification...";
                        navigator.geolocation.getCurrentPosition(
                            handleSuccess,
                            handleFailure,
                            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                        );
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            } else {
                handleFailure({message: "Institutional geolocator unavailable."});
            }
        }

        function handleSuccess(position) {
            document.getElementById('lat').value = position.coords.latitude;
            document.getElementById('lng').value = position.coords.longitude;
            document.getElementById('accuracy').value = position.coords.accuracy;
            document.getElementById('checkInForm').submit();
        }

        function handleFailure(error) {
            alert("Verification Failed: " + (error.code === 1 ? "Permission denied" : error.message));
            location.reload();
        }
    </script>
</body>
</html>

<?php
// ... end of file
?>
