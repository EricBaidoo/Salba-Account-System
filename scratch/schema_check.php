<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
$res = $conn->query("DESCRIBE staff_profiles");
$cols = [];
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo "Columns in staff_profiles: " . implode(', ', $cols) . "\n\n";

$res = $conn->query("DESCRIBE users");
$cols = [];
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo "Columns in users: " . implode(', ', $cols) . "\n";
?>
