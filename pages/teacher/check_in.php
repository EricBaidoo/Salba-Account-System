<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../login');
    exit;
}

// Auto-create staff attendance table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        check_in_time DATETIME NOT NULL,
        latitude DECIMAL(10,8),
        longitude DECIMAL(10,8),
        accuracy DECIMAL(10,2),
        status ENUM('present', 'late', 'absent') DEFAULT 'present',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id, check_in_time)
    ) ENGINE=InnoDB;
");

// Check if 'accuracy' column exists, otherwise add it
$check_col = $conn->query("SHOW COLUMNS FROM staff_attendance LIKE 'accuracy'");
if($check_col->num_rows == 0) {
    $conn->query("ALTER TABLE staff_attendance ADD COLUMN accuracy DECIMAL(10,2) AFTER longitude");
}

// Institutional Hub Coordinates (Calibrated for Ablekuma Campus)
$school_lat = 5.5786875;
$school_lng = -0.2911875;
$allowed_radius_meters = 300; // Calibrated for institutional drift

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

$success = '';
$error = '';
$diagnostic_data = null;
$uid = $_SESSION['user_id'];

// Check if already checked in today
$today = date('Y-m-d');
$already = $conn->query("SELECT id FROM staff_attendance WHERE user_id = $uid AND DATE(check_in_time) = '$today'")->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'geocheckin') {
    if ($already) {
        $error = "You have already clocked in for today.";
    } else {
        $u_lat = floatval($_POST['lat']);
        $u_lng = floatval($_POST['lng']);
        $u_acc = floatval($_POST['accuracy'] ?? 0);
        
        if ($u_lat == 0 && $u_lng == 0) {
            $error = "Institutional Verification Failed: Could not acquire a valid geographic signature. Please ensure location services are enabled.";
        } else {
            $dist = getDistanceMeters($school_lat, $school_lng, $u_lat, $u_lng);
            
            // Log diagnostic info for the error display if needed
            $diagnostic_data = [
                'detected' => "$u_lat, $u_lng",
                'target' => "$school_lat, $school_lng",
                'distance' => round($dist) . "m",
                'accuracy' => round($u_acc) . "m"
            ];

            if ($dist <= $allowed_radius_meters || $_SESSION['role'] === 'admin') {
                $notes = isset($_POST['bypass']) ? "Administrative Manual Override (Geofence Bypass)" : "";
                $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude, accuracy, notes) VALUES (?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param("iddds", $uid, $u_lat, $u_lng, $u_acc, $notes);
                if ($stmt->execute()) {
                    $success = "Institutional presence verified! (Accuracy: " . round($u_acc) . "m, Distance: " . round($dist) . "m)";
                    if(isset($_POST['bypass'])) $success = "Administrative Presence Authenticated via Manual Override.";
                    $already = true;
                } else {
                    $error = "Database error logging institutional record.";
                }
            } else {
                $error = "Geofence Boundary Violation: You are currently outside the authorized campus hub.";
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
    <title>Faculty Verification Hub | Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans">

    <?php include '../../includes/top_nav.php'; ?>

    <main class="admin-main-content p-4 md:p-8 min-h-screen py-20 px-6">
        <div class="max-w-xl mx-auto">
            <!-- Header -->
            <div class="mb-12 text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 bg-indigo-50 text-indigo-700 rounded-full text-[10px] font-black uppercase tracking-widest mb-4 border border-indigo-100 italic">
                    <span class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-ping"></span> Institutional Semesterinal
                </div>
                <h1 class="text-4xl font-black text-slate-900 tracking-tighter lowercase">Presence <span class="text-indigo-600">Verification</span></h1>
            </div>

            <!-- Success/Error Alerts -->
            <?php if ($success): ?>
                <div class="bg-indigo-600 text-white p-8 rounded-[3rem] shadow-2xl mb-8 flex items-center gap-6 border border-indigo-400">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl shrink-0"><i class="fas fa-check"></i></div>
                    <div>
                        <h4 class="text-lg font-black tracking-tight lowercase">Verification Success</h4>
                        <p class="text-sm font-bold text-indigo-100 opacity-80"><?= htmlspecialchars($success) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-600 text-white p-8 rounded-[3rem] shadow-2xl mb-8 flex flex-col gap-6 border border-red-400">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center text-3xl shrink-0"><i class="fas fa-shield-slash"></i></div>
                        <div>
                            <h4 class="text-lg font-black tracking-tight lowercase">Authentication Blocked</h4>
                            <p class="text-sm font-bold text-red-100 opacity-80"><?= $error ?></p>
                        </div>
                    </div>
                    <?php if($diagnostic_data): ?>
                    <div class="bg-black/20 rounded-3xl p-6 border border-white/10 font-mono text-[10px]">
                        <p class="text-white/40 uppercase tracking-widest mb-3 font-black">institutional diagnostic log</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div><span class="text-red-200">Detected:</span> <br> <?= $diagnostic_data['detected'] ?></div>
                            <div><span class="text-red-200">Target:</span> <br> <?= $diagnostic_data['target'] ?></div>
                            <div><span class="text-red-200">Distance:</span> <br> <?= $diagnostic_data['distance'] ?></div>
                            <div><span class="text-red-200">Accuracy:</span> <br> <?= $diagnostic_data['accuracy'] ?></div>
                        </div>
                        <p class="mt-4 text-[9px] text-red-100 italic">* High distance with low accuracy (>1000m) suggests an IP-based location mismatch.</p>
                        
                        <?php if($_SESSION['role'] === 'admin'): ?>
                            <div class="mt-6 pt-6 border-t border-white/10">
                                <form method="POST">
                                    <input type="hidden" name="action" value="geocheckin">
                                    <input type="hidden" name="lat" value="<?= $school_lat ?>">
                                    <input type="hidden" name="lng" value="<?= $school_lng ?>">
                                    <input type="hidden" name="accuracy" value="0">
                                    <input type="hidden" name="bypass" value="1">
                                    <button type="submit" class="w-full py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all">
                                        <i class="fas fa-key mr-2"></i> Administrative Manual Override
                                    </button>
                                </form>
                                <p class="mt-3 text-[8px] text-white/30 text-center uppercase tracking-widest font-bold">This bypass will be flagged in the institutional log</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Main Interactive Card -->
            <div class="bg-white rounded-[4rem] shadow-2xl border border-slate-100 overflow-hidden text-center relative">
                <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/graphy.png')]"></div>
                
                <div class="p-16 relative z-10">
                    <div class="w-44 h-44 bg-indigo-50 text-indigo-700 rounded-[3.5rem] flex items-center justify-center mx-auto mb-10 shadow-inner border-[12px] border-white relative transition-all duration-700 hover:scale-105">
                        <i class="fas fa-fingerprint text-7xl"></i>
                    </div>

                    <?php if($already): ?>
                        <div class="inline-flex items-center gap-4 bg-emerald-50 text-emerald-700 px-10 py-5 rounded-[2.5rem] font-black text-lg border border-emerald-100">
                            <i class="fas fa-shield-check text-2xl"></i> Session Active
                        </div>
                    <?php else: ?>
                        <div id="unsupported-msg" class="hidden mb-8 p-6 bg-orange-50 text-orange-700 rounded-3xl border border-orange-100 text-xs font-bold uppercase tracking-widest leading-relaxed">
                            <i class="fas fa-triangle-exclamation text-2xl mb-2"></i><br>
                            Insecure context detected. Geolocation requires <span class="text-orange-950">HTTPS</span> or <span class="text-orange-950">Localhost</span> access.
                        </div>

                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckin">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            <input type="hidden" name="accuracy" id="accuracy" value="0">
                            
                            <button type="button" onclick="initiateAuthentication()" id="authBtn" class="bg-slate-900 text-white font-black text-2xl px-16 py-7 rounded-[3rem] hover:bg-indigo-700 hover:scale-[1.03] active:scale-95 transition-all duration-500 shadow-2xl flex items-center justify-center gap-4 mx-auto lowercase tracking-tighter">
                                <i class="fas fa-satellite-dish"></i> Authenticate
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div id="status-msg" class="mt-8 text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] hidden">
                        <i class="fas fa-circle-notch fa-spin text-indigo-600 mr-2"></i> <span id="status-text">calibrating signal...</span>
                    </div>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="mt-16 text-center text-[10px] font-black text-slate-300 uppercase tracking-[0.4em]">
                Institutional Boundaries: 250m Radius · Multi-Tier Verification
            </div>
        </div>
    </main>

    <script>
        // Check for secure context
        if (!window.isSecureContext) {
            document.getElementById('unsupported-msg').classList.remove('hidden');
            document.getElementById('authBtn').classList.add('opacity-30', 'pointer-events-none');
        }

        function initiateAuthentication() {
            const btn = document.getElementById('authBtn');
            const status = document.getElementById('status-msg');
            const statusText = document.getElementById('status-text');
            
            btn.classList.add('opacity-50', 'pointer-events-none');
            status.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                statusText.innerText = "Attempting High-Precision Lock (Wait up to 20s)...";
                
                // Tiered Strategy: Try High Accuracy first
                navigator.geolocation.getCurrentPosition(
                    handleSuccess,
                    function(err) {
                        console.warn("High precision failed, falling back to network signal...", err);
                        statusText.innerText = "Fallback: Attempting Standard Verification...";
                        
                        // Fallback to lower accuracy if high accuracy fails or times out
                        navigator.geolocation.getCurrentPosition(
                            handleSuccess,
                            handleFailure,
                            { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                        );
                    },
                    { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
                );
            } else {
                handleFailure({message: "Institutional geolocator unavailable on this terminal."});
            }
        }

        function handleSuccess(position) {
            document.getElementById('lat').value = position.coords.latitude;
            document.getElementById('lng').value = position.coords.longitude;
            document.getElementById('accuracy').value = position.coords.accuracy;
            document.getElementById('checkInForm').submit();
        }

        function handleFailure(error) {
            let msg = "Institutional Verification Failed: ";
            if (error.code === 1) msg += "Location Access Denied. Please enable permissions.";
            else if (error.code === 3) msg += "Calibration Timeout. Signal weak or blocked.";
            else msg += error.message;
            
            alert(msg);
            location.reload();
        }
    </script>
</body>
</html>

<?php
// ... end of file
?>
