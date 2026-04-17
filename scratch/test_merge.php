<?php
$conn = new mysqli('localhost', 'root', 'root', 'smis_merge');
if ($conn->connect_error) die("Connection failed");

$res = $conn->query("SHOW TABLES");
echo "Total tables: " . $res->num_rows . "\n";

$res = $conn->query("SELECT SUM(amount) FROM payments WHERE semester = 'Second Semester'");
$total_payments = $res->fetch_row()[0];
echo "Total Payments for Second Semester: " . number_format($total_payments, 2) . "\n";

$res = $conn->query("SELECT SUM(amount) FROM expenses WHERE semester = 'Second Semester'");
$total_expenses = $res->fetch_row()[0];
echo "Total Expenses for Second Semester: " . number_format($total_expenses, 2) . "\n";
?>
