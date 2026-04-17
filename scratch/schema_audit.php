<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

function desc($conn, $table) {
    echo "--- SCHEMA: $table ---\n";
    $res = $conn->query("DESCRIBE $table");
    if (!$res) { echo "Error: " . $conn->error . "\n"; return; }
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
}

desc($conn, 'payments');
desc($conn, 'expenses');
desc($conn, 'student_fees');
?>
