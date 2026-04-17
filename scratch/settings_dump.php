<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- SYSTEM SETTINGS TABLES --- \n";
$res = $conn->query("SELECT * FROM system_settings");
while($row = $res->fetch_assoc()) {
    printf("%-25s | %s\n", $row['setting_key'], $row['setting_value']);
}
?>
