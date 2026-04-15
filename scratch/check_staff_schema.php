<?php
include __DIR__ . '/../includes/db_connect.php';
$res = $conn->query("DESCRIBE staff_profiles");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
