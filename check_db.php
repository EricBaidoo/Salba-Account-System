<?php
include 'includes/db_connect.php';

$res = $conn->query("SELECT * FROM lesson_plans ORDER BY id DESC LIMIT 2");
if (!$res) die("Error: " . $conn->error);

echo "Latest 2 lesson plans:\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
