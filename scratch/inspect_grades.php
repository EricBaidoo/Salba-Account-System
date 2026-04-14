<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SHOW COLUMNS FROM grades");
if($res) { while($row = $res->fetch_assoc()) echo $row['Field'] ." - " . $row['Type']. "\n"; }
?>
