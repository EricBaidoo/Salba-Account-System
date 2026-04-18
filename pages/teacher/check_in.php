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

// Check if already checked in today
$today = date('Y-m-d');
$already = $conn->query("SELECT id FROM staff_attendance WHERE user_id = $uid AND DATE(check_in_time) = '$today'")->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'geocheckin') {
    if ($already) {
        redirect('check_in', 'error', "You have already recorded your attendance for today.");
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
                $notes = isset($_POST['bypass']) ? "Supervisor Manual Attendance Record" : "";
                $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude, accuracy, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param("iddds", $uid, $u_lat, $u_lng, $u_acc, $notes);
                if ($stmt->execute()) {
                    $msg = isset($_POST['bypass']) ? "Manual attendance record successfully authenticated." : "Campus presence verified. Welcome to school!";
                    
                    // AUDIT LOG
                    log_activity($conn, 'Attendance', "Staff check-in recorded. User: " . $_SESSION['username'] . " (Distance: " . round($dist) . "m)", null, $diag);
                    
                    redirect('check_in', 'success', $msg);
                } else {
                    redirect('check_in', 'error', "An error occurred while saving your attendance record.");
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
    <style>
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(circle at 2px 2px, rgba(255,255,255,0.05) 1px, transparent 0);
            background-size: 40px 40px;
            color: #f8fafc;
        }
        .security-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }
        .security-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, #38bdf8, transparent);
        }
        .identity-lens {
            width: 120px;
            height: 120px;
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            margin: 0 auto 2.5rem;
        }
        .identity-lens::after {
            content: '';
            position: absolute;
            inset: -8px;
            border: 1px solid rgba(56, 189, 248, 0.1);
            border-radius: 50%;
            animation: ping 3s cubic-bezier(0, 0, 0.2, 1) infinite;
        }
        .hud-stat {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.1em;
            color: #94a3b8;
        }
        .hud-active { color: #38bdf8; text-shadow: 0 0 8px rgba(56, 189, 248, 0.5); }
    </style>
</head>
<body class="min-h-screen font-sans overflow-x-hidden">

    <!-- Minimalist Security Header -->
    <header class="w-full bg-slate-900/40 backdrop-blur-xl border-b border-white/5 px-6 md:px-12 py-5 fixed top-0 z-50 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>index" class="w-10 h-10 bg-white/5 border border-white/10 rounded-xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/10 transition-all">
                <i class="fas fa-chevron-left text-sm"></i>
            </a>
            <div class="hidden sm:block">
                <span class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em] block leading-none mb-1 text-left">Internal Navigation</span>
                <span class="text-xs font-bold text-slate-300">Staff Portal Hub</span>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>pages/common/profile.php" class="flex items-center gap-3 px-4 py-2 bg-white/5 border border-white/5 rounded-xl hover:bg-white/10 transition-all group">
                <div class="text-right hidden xs:block">
                    <p class="text-[8px] font-black text-slate-500 uppercase tracking-widest leading-none mb-1">Authenticated as</p>
                    <p class="text-[11px] font-bold text-white leading-none"><?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center text-sky-400 border border-sky-500/20 group-hover:scale-105 transition-transform">
                    <i class="fas fa-user-shield text-sm"></i>
                </div>
            </a>
            
            <a href="<?= BASE_URL ?>logout" class="w-10 h-10 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-xl flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-lg shadow-rose-900/10" title="Secure Logout">
                <i class="fas fa-power-off text-sm"></i>
            </a>
        </div>
    </header>

    <main class="min-h-screen flex items-center justify-center p-6 pt-24">
        
        <div class="security-card p-12 md:p-14 rounded-[3rem]">
            
            <div class="mb-10 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-2">Institutional Management</p>
                <h1 class="text-2xl font-bold tracking-tight text-white">Personnel Presence Verification</h1>
            </div>

            <div class="relative z-10 text-center">
                <?php if($already): ?>
                    <!-- Verification Successful -->
                    <div class="identity-lens border-emerald-500/30 bg-emerald-500/10 mb-8">
                        <i class="fas fa-check text-4xl text-emerald-400"></i>
                    </div>
                    <h2 class="text-lg font-bold text-white mb-2">Authentication Successful</h2>
                    <p class="text-xs text-slate-400 leading-relaxed mb-10">Attendance Logged: <?= date('H:i:s') ?><br>System integrity verified.</p>
                    
                    <div class="pt-8 border-t border-slate-700/50">
                        <a href="<?= BASE_URL ?>index" class="text-sky-400 font-bold text-[10px] uppercase tracking-widest hover:text-white transition-colors">Return to Terminal</a>
                    </div>
                <?php else: ?>
                    <!-- Awaiting Authentication -->
                    <div class="identity-lens mb-10">
                        <i class="fas fa-fingerprint text-5xl text-sky-400"></i>
                    </div>
                    
                    <div class="space-y-1 mb-10">
                        <h2 class="text-lg font-bold text-white">Security Check Ready</h2>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest leading-relaxed">Authorized Perimeter: <?= $allowed_radius_meters ?>M</p>
                    </div>

                    <!-- Tactical HUD -->
                    <div class="grid grid-cols-3 gap-2 mb-10 p-4 bg-slate-800/50 rounded-2xl border border-slate-700/50">
                        <div class="text-center">
                            <p class="hud-stat mb-1">GPS</p>
                            <p class="hud-stat hud-active">LOCK</p>
                        </div>
                        <div class="text-center border-x border-slate-700/50">
                            <p class="hud-stat mb-1">SIGNAL</p>
                            <p class="hud-stat hud-active">STABLE</p>
                        </div>
                        <div class="text-center">
                            <p class="hud-stat mb-1">ENC</p>
                            <p class="hud-stat hud-active">AES</p>
                        </div>
                    </div>

                    <div class="mt-12">
                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckin">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            <input type="hidden" name="accuracy" id="accuracy" value="0">
                            
                            <button type="button" onclick="initiateCheckIn()" id="authBtn" class="w-full bg-sky-500 text-white font-black text-[11px] uppercase tracking-[0.2em] py-5 rounded-2xl hover:bg-sky-400 transition-all shadow-[0_0_20px_rgba(14,165,233,0.3)] flex items-center justify-center gap-3">
                                Authenticate & Verify
                            </button>
                        </form>
                        
                        <div id="status-msg" class="mt-8 text-[9px] font-black text-slate-500 uppercase tracking-[0.2em] hidden">
                            <i class="fas fa-satellite fa-spin mr-2 text-sky-400"></i> <span id="status-text">calibrating perimeter sensors...</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Diagnostic Feedback (Perimeter Variance) -->
        <?php if($diagnostic_data): ?>
            <div class="mt-8 bg-rose-500/5 p-8 rounded-[2rem] border border-rose-500/20">
                <div class="flex items-center gap-4 mb-6 text-rose-400">
                    <i class="fas fa-shield-halved text-xl"></i>
                    <h3 class="text-[10px] font-black uppercase tracking-widest">Perimeter Breach Detected</h3>
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
                            <input type="hidden" name="action" value="geocheckin">
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

        <p class="mt-12 text-center text-[8px] font-bold text-slate-600 uppercase tracking-[0.4em]">Integrated Security Audit Layer Active</p>
    </main>

    <script>
        if (!window.isSecureContext) {
            document.getElementById('unsupported-msg').classList.remove('hidden');
            document.getElementById('authBtn').classList.add('opacity-30', 'pointer-events-none');
        }

        function initiateCheckIn() {
            const btn = document.getElementById('authBtn');
            const visual = document.getElementById('authVisual');
            const status = document.getElementById('status-msg');
            const statusText = document.getElementById('status-text');
            
            btn.classList.add('opacity-50', 'pointer-events-none');
            visual.classList.remove('pulse-soft');
            visual.classList.add('bg-indigo-600', 'text-white');
            visual.querySelector('img').classList.remove('opacity-50', 'grayscale');
            visual.querySelector('img').classList.add('brightness-200');
            status.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                statusText.innerText = "Verifying campus location...";
                
                navigator.geolocation.getCurrentPosition(
                    handleSuccess,
                    function(err) {
                        statusText.innerText = "Signal low, retrying verification...";
                        navigator.geolocation.getCurrentPosition(
                            handleSuccess,
                            handleFailure,
                            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
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
