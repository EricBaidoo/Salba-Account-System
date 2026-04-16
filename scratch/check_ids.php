<?php
include 'includes/db_connect.php';
define('LOCAL_DB', $_SERVER['SERVER_NAME'] === 'localhost' || empty($_SERVER['SERVER_NAME']));

if (LOCAL_DB) {
    echo "Inspecting Local DB Structure:\n";
    $tables = ['users', 'staff_profiles'];
    foreach ($tables as $table) {
        $res = $conn->query("DESCRIBE $table");
        echo "\nTable: $table\n";
        while ($row = $res->fetch_assoc()) {
            printf("%-20s | %-20s\n", $row['Field'], $row['Type']);
        }
    }
}
?>
