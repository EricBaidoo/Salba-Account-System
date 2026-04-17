<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$res = $conn->query("SHOW CREATE TABLE subjects");
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo $conn->error;
}
?>
