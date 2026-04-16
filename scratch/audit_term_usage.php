<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// 1. Find columns named 'term'
$tables_with_term = [];
$res = $conn->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME LIKE '%term%' AND TABLE_SCHEMA = 'Salba_acc'");
while($row = $res->fetch_assoc()) {
    $tables_with_term[] = $row;
}

// 2. Find system settings with 'term' in key
$settings_with_term = [];
$res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%term%'");
while($row = $res->fetch_assoc()) {
    $settings_with_term[] = $row;
}

echo "TABLES WITH TERM COLUMNS:\n";
print_r($tables_with_term);

echo "\nSYSTEM SETTINGS WITH TERM KEYS:\n";
print_r($settings_with_term);

$conn->close();
?>
