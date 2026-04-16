<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("failed");

echo "--- TABLE STRUCTURES ---\n";
$tables = ['attendance', 'academic_calendar', 'system_settings'];
foreach($tables as $t) {
    echo "\nTABLE: $t\n";
    $res = $conn->query("DESCRIBE `$t`");
    while($row = $res->fetch_assoc()) {
        printf("%-20s %-20s\n", $row['Field'], $row['Type']);
    }
}

echo "\n--- HOLIDAYS SAMPLE ---\n";
$res = $conn->query("SELECT * FROM academic_calendar LIMIT 5");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
