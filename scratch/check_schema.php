<?php
$c = new mysqli('localhost', 'root', 'root', 'Salba_acc');
echo "--- ALL DATABASE TABLES ---\n";
$res = $c->query("SHOW TABLES");
while($row = $res->fetch_array()) echo $row[0] . "\n";

echo "\n--- PAYMENTS TABLE SCHEMA ---\n";
$res = $c->query("DESCRIBE payments");
while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";

echo "\n--- STUDENT FEES TABLE SCHEMA ---\n";
$res = $c->query("DESCRIBE student_fees");
while($row = $res->fetch_assoc()) echo $row['Field'] . " (" . $row['Type'] . ")\n";
?>
