<?php
include 'includes/db_connect.php';

$res = $conn->query("ALTER TABLE staff_profiles MODIFY staff_type VARCHAR(100) DEFAULT 'teaching'");
if($res) echo "staff_type changed to VARCHAR.\n";

$res2 = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($res2 && $res2->num_rows > 0) {
    // Make sure supervisor is in users role ENUM if applicable
    $r = $res2->fetch_assoc();
    if(strpos($r['Type'], 'supervisor') === false) {
        // We might want to expand the ENUM
        echo "Supervisor not in users role ENUM. Current: " . $r['Type'] . "\n";
    }
}
