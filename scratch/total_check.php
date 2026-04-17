<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

$res = $conn->query("SELECT SUM(amount) as s FROM payments");
echo "GRAND TOTAL PAYMENTS: " . number_format($res->fetch_assoc()['s'], 2) . "\n";
?>
