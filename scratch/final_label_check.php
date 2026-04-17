<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';
$labels = [];
$tables = ['payments', 'expenses', 'student_fees'];
foreach ($tables as $t) {
    $res = $conn->query("SELECT DISTINCT semester FROM `$t` ");
    while($row = $res->fetch_assoc()) $labels[] = $row['semester']??'NULL';
}
echo "ALL LABELS: " . implode(", ", array_unique($labels)) . "\n";
?>
