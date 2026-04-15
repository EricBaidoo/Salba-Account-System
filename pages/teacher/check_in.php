<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || ($_SESSION['role'] !== 'facilitator' && $_SESSION['role'] !== 'admin')) {
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

// Hardcoded School Coordinates (Salba Montessori / Accra reference)
$school_lat = 5.556020;
$school_lng = -0.196900;
$allowed_radius_meters = 200; // 200 meters geofence

function getDistanceMeters($lat1, $lon1, $lat2, $lon2) {
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
                $error = "Geofence Violation: You are " . round($dist) . " meters away from the campus. You must be within " . $allowed_radius_meters . "m to check in.";
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
    <title>Teacher GPS Check-In - Salba</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/top_nav.php'; ?>

    <main class=" min-h-screen relative">
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30 shadow-sm">
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                <i class="fas fa-location-dot text-red-500"></i> Daily GPS Check-In
            </h1>
            <p class="text-gray-500 mt-2 text-sm">
                Authenticate your physical presence on the Salba Montessori premises.
            </p>
        </div>

        <div class="p-8 max-w-3xl mx-auto mt-10">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                    <span class="font-bold"><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-ban text-red-500 text-xl"></i>
                    <span class="font-bold"><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden text-center relative">
                <!-- Map Background Design -->
                <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: radial-gradient(#000 1px, transparent 1px); background-size: 20px 20px;"></div>
                
                <div class="p-12 relative z-10">
                    <div class="w-32 h-32 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner border-[6px] border-white relative">
                        <i class="fas fa-map-marker-alt text-5xl"></i>
                        <div class="absolute inset-0 border-4 border-red-500 rounded-full animate-ping opacity-20 hidden" id="ping-anim"></div>
                    </div>

                    <h2 class="text-2xl font-extrabold text-gray-900 mb-2">Campus Geofence Locked</h2>
                    <p class="text-gray-500 mb-10 max-w-md mx-auto">Click below to activate your device GPS. The system will calculate your distance to ensuring you are within 200m of the school gates.</p>

                    <?php if($already): ?>
                        <div class="inline-block bg-green-100 text-green-800 px-8 py-4 rounded-full font-bold text-lg shadow-sm border border-green-200">
                            <i class="fas fa-check double mr-2"></i> Clocked In for Today
                        </div>
                    <?php else: ?>
                        <form id="checkInForm" method="POST">
                            <input type="hidden" name="action" value="geocheckin">
                            <input type="hidden" name="lat" id="lat" value="0">
                            <input type="hidden" name="lng" id="lng" value="0">
                            
                            <button type="button" onclick="acquireGPS()" class="bg-gradient-to-r from-red-600 to-red-700 text-white font-bold text-xl px-12 py-5 rounded-full hover:shadow-lg hover:scale-105 transition-all transform shadow flex items-center justify-center gap-3 mx-auto">
                                <i class="fas fa-satellite-dish"></i> Activate GPS & Clock In
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <div id="status-msg" class="mt-6 text-sm font-bold text-gray-400 uppercase tracking-widest hidden">
                        <i class="fas fa-circle-notch fa-spin text-red-500 mr-2"></i> Acquiring satellite lock...
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function acquireGPS() {
            const btn = document.querySelector('button[type="button"]');
            const status = document.getElementById('status-msg');
            const anim = document.getElementById('ping-anim');
            
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            status.classList.remove('hidden');
            anim.classList.remove('hidden');
            
            if ("geolocation" in navigator) {
                // High accuracy required for geofencing
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('lat').value = position.coords.latitude;
                        document.getElementById('lng').value = position.coords.longitude;
                        document.getElementById('checkInForm').submit();
                    },
                    function(error) {
                        alert("Geolocation failed: " + error.message + ". Please enable browser permissions.");
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                        status.classList.add('hidden');
                        anim.classList.add('hidden');
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                );
            } else {
                alert("Geolocation is not supported by your browser.");
            }
        }
    </script>
</body>
</html>

