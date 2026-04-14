<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$res = $conn->query("SELECT * FROM teacher_allocations LIMIT 10");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
}
?>
