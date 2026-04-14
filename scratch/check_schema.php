<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'salba_acc');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "=== USERS ===\n";
$res = $conn->query("SELECT id, username, staff_id FROM users LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

echo "\n=== STAFF PROFILES ===\n";
$res = $conn->query("SELECT id, user_id, full_name, staff_code FROM staff_profiles LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
