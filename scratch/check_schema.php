<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("DESCRIBE classes");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
