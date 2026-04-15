<?php
include 'includes/db_connect.php';
function describeTable($conn, $table) {
    echo "--- $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
describeTable($conn, 'staff_profiles');
describeTable($conn, 'users');
