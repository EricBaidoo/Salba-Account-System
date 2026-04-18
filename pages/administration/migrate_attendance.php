<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

// Access Control - Must be Logged in as Admin on the live site
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die("<h2>Access Denied</h2><p>Please log in as an <b>Administrator</b> first to run this migration.</p><p><a href='../../login'>Login here</a></p>");
}

echo "<html><head><title>System Migration</title><style>body{font-family:sans-serif;padding:40px;line-height:1.6;background:#f8fafc;color:#1e293b;} .card{background:white;padding:30px;border-radius:15px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1);max-width:600px;margin:0 auto;} h2{color:#4f46e5;margin-top:0;} .success{color:#10b981;font-weight:bold;} .info{color:#64748b;font-size:0.9em;}</p></style></head><body>";
echo "<div class='card'>";
echo "<h2>Initializing Geolocation Security</h2>";

$settings = [
    'attendance_lat' => ['value' => '5.5786875', 'description' => 'Institutional Latitude (Ablekuma Hub)'],
    'attendance_lng' => ['value' => '-0.2911875', 'description' => 'Institutional Longitude (Ablekuma Hub)'],
    'attendance_radius' => ['value' => '300', 'description' => 'Verification Radius (Meters)']
];

$added = 0;
foreach ($settings as $key => $data) {
    // Check if exists
    $stmt = $conn->prepare("SELECT id FROM system_settings WHERE meta_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $stmt_ins = $conn->prepare("INSERT INTO system_settings (meta_key, meta_value, description, updated_by) VALUES (?, ?, ?, 'System Migration')");
        $stmt_ins->bind_param("sss", $key, $data['value'], $data['description']);
        if ($stmt_ins->execute()) {
            echo "<p class='success'>✅ Added setting: <b>$key</b> = " . $data['value'] . "</p>";
            $added++;
        }
    } else {
        echo "<p class='info'>ℹ️ Setting <b>$key</b> already exists. Skipping.</p>";
    }
}

if ($added > 0) {
    log_activity($conn, 'System', "Initialized $added geolocation settings in system_settings.");
}

echo "<hr><p><b>Migration Complete.</b> You can now manage these settings in the Admin Dashboard.</p>";
echo "<p><a href='system_settings' style='color:#4f46e5;font-weight:bold;'>Go to System Settings →</a></p>";
echo "</div></body></html>";
?>
