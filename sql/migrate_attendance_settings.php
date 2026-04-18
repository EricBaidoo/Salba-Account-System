<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';

// Access Control
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die("Administrative privilege required.");
}

echo "<h2>Initializing Attendance & Geolocation Settings</h2>";

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
            echo "<p>Added setting: <b>$key</b> = " . $data['value'] . "</p>";
            $added++;
        }
    } else {
        echo "<p>Setting <b>$key</b> already exists. Skipping.</p>";
    }
}

if ($added > 0) {
    log_activity($conn, 'System', "Initialized $added geolocation settings in system_settings.");
}

echo "<hr><p>Migration Complete. <a href='../pages/administration/system_settings.php'>Go to Settings</a></p>";
?>
