<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');

echo "--- GRADES / MARKS TABLE ---\n";
$res = $conn->query("SHOW TABLES LIKE '%grade%'");
while($r = $res->fetch_array()) echo $r[0] . "\n";
$res = $conn->query("SHOW TABLES LIKE '%mark%'");
while($r = $res->fetch_array()) echo $r[0] . "\n";

echo "--- EXAM_MARKS SCHEMA ---\n";
$res = $conn->query("SHOW COLUMNS FROM exam_marks");
if($res) { while($row = $res->fetch_assoc()) echo $row['Field'] ." - " . $row['Type']. "\n"; }

echo "--- GRADES SCHEMA ---\n";
$res = $conn->query("SHOW COLUMNS FROM grades");
if($res) { while($row = $res->fetch_assoc()) echo $row['Field'] ." - " . $row['Type']. "\n"; }
?>
