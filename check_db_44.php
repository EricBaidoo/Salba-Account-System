<?php
include 'includes/db_connect.php';

$res = $conn->query("SELECT * FROM lesson_plans WHERE id = 44");
if (!$res) die("Error: " . $conn->error);

echo "Draft 44 Data:\n";
print_r($res->fetch_assoc());
