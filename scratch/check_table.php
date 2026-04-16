<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SHOW TABLES LIKE 'academic_levels'");
echo ($res->num_rows > 0 ? "EXISTS" : "MISSING");
?>
