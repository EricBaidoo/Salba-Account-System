<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("SELECT class_name, COUNT(*) as count FROM class_subjects GROUP BY class_name");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
