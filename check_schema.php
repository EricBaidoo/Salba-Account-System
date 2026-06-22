<?php
require 'includes/db_connect.php';
$result = $conn->query("DESCRIBE staff_profiles");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
