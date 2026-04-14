<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SHOW COLUMNS FROM attendance");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
