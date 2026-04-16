<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../../includes/login.php');
    exit;
}

// Auto-create staff attendance table
$conn->query("
    CREATE TABLE IF NOT EXISTS staff_attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        check_in_time DATETIME NOT NULL,
        latitude DECIMAL(10,8),
        longitude DECIMAL(10,8),
        status ENUM('present', 'late', 'absent') DEFAULT 'present',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id, check_in_time)
    ) ENGINE=InnoDB;
");

// Hardcoded School Coordinates (Plus Code JPF5+4HV - Ablekuma Fan-Milk)
// Calibrated based on user's real-time detection feedback
$school_lat = 5.5786875;
$school_lng = -0.2911875;
$allowed_radius_meters = 250; // Widened to 250m as requested to cover 4-story building drift

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) return 999999;
    $earth_radius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * asin(sqrt($a));
    return $earth_radius * $c;
}

$success = '';
$error = '';
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
        
        if ($u_lat == 0 && $u_lng == 0) {
            $error = "GPS Error: Could not determine your location. Please ensure location services are enabled.";
        } else {
            $dist = getDistanceMeters($school_lat, $school_lng, $u_lat, $u_lng);
            if ($dist <= $allowed_radius_meters || $_SESSION['role'] === 'admin') { // Admin bypass
                // Clock them in
                $stmt = $conn->prepare("INSERT INTO staff_attendance (user_id, check_in_time, latitude, longitude) VALUES (?, NOW(), ?, ?)");
                $stmt->bind_param("idd", $uid, $u_lat, $u_lng);
                if ($stmt->execute()) {
                    $success = "Successfully verified on campus and clocked in! (Distance: " . round($dist) . "m)";
                    $already = true;
                } else {
                    $error = "Database error logging attendance.";
                }
            } else {
                $error = "Geofence Violation: You are " . round($dist) . " meters away from the campus hub. <br><br> <div class='text-xs bg-black/10 p-2 rounded'><strong>Detected:</strong> $u_lat, $u_lng <br> <strong>Target:</strong> $school_lat, $school_lng</div><br>You must be within " . $allowed_radius_meters . "m to check in.";
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
    <title>Institutional Attendance Hub - Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class=" min-h-screen relative">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30 shadow-sm flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-black text-gray-900 flex items-center gap-3 lowercase tracking-tighter">
                    <i class="fas fa-school text-indigo-700"></i> institutional attendance
                </h1>
                <p class="text-gray-500 mt-1 text-[10px] font-black uppercase tracking-widest leading-none">
                    Faculty & Staff Verification Hub · Salba Montessori
                </p>
            </div>
            <div class="hidden md:block text-right">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Administrative Status</p>
                <p class="text-xs font-bold text-indigo-600 flex items-center gap-2 justify-end">
                    <span class="w-2 h-2 bg-indigo-500 rounded-full animate-pulse"></span> Terminal Connected
                </p>
            </div>
        </div>

        <div class="p-8 max-w-2xl mx-auto mt-12">
            <?php if ($success): ?>
                <div class="bg-indigo-900 text-white px-6 py-4 rounded-2xl mb-8 flex items-center gap-4 shadow-2xl border border-indigo-700 animate-bounce">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h4 class="font-black uppercase tracking-widest text-sm text-indigo-100">Verification Success</h4>
                        <p class="text-xs text-indigo-200 font-bold"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-slate-900 text-white px-6 py-5 rounded-2xl mb-8 flex flex-col gap-4 shadow-2xl border border-slate-700">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-2xl">
                            <i class="fas fa-shield-slash text-red-400"></i>
                        </div>
                        <div>
                            <h4 class="font-black uppercase tracking-widest text-sm text-slate-100">Authentication Failed</h4>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-[0.2em] mt-1">Institutional Boundaries Not Met</p>
                        </div>
                    </div>
                    <div class="bg-black/20 p-4 rounded-xl text-xs font-mono border border-white/5 text-slate-300">
                        <?php echo $error; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-[3.5rem] shadow-2xl border border-gray-100 overflow-hidden text-center relative group">
                <div class="absolute inset-0 opacity-[0.05] pointer-events-none bg-[url('https://www.transparenttextures.com/patterns/graphy.png')]"></div>
                
                <div class="p-16 relative z-10">
                    <div class="w-40 h-40 bg-indigo-50 text-indigo-700 rounded-[3rem] flex items-center justify-center mx-auto mb-10 shadow-inner border-[10px] border-white relative transition-all duration-700 group-hover:shadow-indigo-100 group-hover:shadow-2xl">
                        <i class="fas fa-fingerprint text-6xl"></i>
                    </div>

                    <h2 class="text-4xl font-black text-gray-900 mb-4 lowercase tracking-tighter">Faculty Verification</h2>
                    <p class="text-gray-400 font-bold text-xs mb-12 max-w-sm mx-auto uppercase tracking-widest leading-relaxed">Official Institutional Presence Authentication. Please establish your session within school boundaries.</p>

                    <?php if($already): ?>
                        <div class="inline-flex items-center gap-4 bg-indigo-50 text-indigo-700 px-10 py-5 rounded-[2.5rem] font-black text-lg shadow-sm border border-indigo-100">
                            <i class="fas fa-shield-check text-2xl"></i> 
                            <span class="lowercase tracking-tighter">Session Authenticated</span>
                        </div>
                    <?php else: ?>
                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckin">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            
                            <button type="button" onclick="acquireGPS()" class="group relative bg-indigo-700 text-white font-black text-2xl px-16 py-7 rounded-[3rem] hover:bg-black hover:scale-[1.02] active:scale-95 transition-all duration-500 shadow-2xl flex items-center justify-center gap-4 mx-auto overflow-hidden text-center">
                                <span class="relative z-10 flex items-center gap-4">
                                    <i class="fas fa-satellite-dish"></i> 
                                    <span class="lowercase tracking-tighter">Authenticate Now</span>
                                </span>
                                <div class="absolute inset-0 bg-gradient-to-r from-indigo-800 to-indigo-900 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div id="status-msg" class="mt-10 text-[10px] font-black text-gray-400 uppercase tracking-[0.4em] hidden">
                        <i class="fas fa-circle-notch fa-spin text-indigo-700 mr-2"></i> establishing secure link...
                    </div>
                </div>
            </div>
            
            <div class="mt-16 text-center">
                <p class="text-[10px] text-gray-300 font-black uppercase tracking-[0.5em] mb-4">Institutional Presence Verification Node</p>
                <div class="inline-flex items-center gap-2 bg-white px-6 py-3 rounded-full shadow-sm border border-gray-100 text-[10px] font-bold text-gray-400">
                    <i class="fas fa-network-wired text-indigo-500"></i>
                    <span>Authorized Entry Zone: 250 Meters</span>
                </div>
            </div>
        </div>
    </main>

    <script>
        function acquireGPS() {
            const btn = document.querySelector('button[type="button"]');
            const status = document.getElementById('status-msg');
            
            btn.classList.add('opacity-50', 'pointer-events-none');
            status.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('lat').value = position.coords.latitude;
                        document.getElementById('lng').value = position.coords.longitude;
                        document.getElementById('checkInForm').submit();
                    },
                    function(error) {
                        alert("Institutional Verification Failed: " + error.message + ". Please enable location services in your browser settings.");
                        btn.classList.remove('opacity-50', 'pointer-events-none');
                        status.classList.add('hidden');
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
            } else {
                alert("This device does not support institutional presence verification.");
            }
        }
    </script>
</body>
</html>
