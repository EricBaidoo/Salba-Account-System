<?php
require_once __DIR__ . '/includes/db_connect.php';

$sql = "-- Export of Stationery Data\n\n";

// Export stationery_items
$sql .= "-- TABLE: stationery_items\n";
$r = $conn->query("SELECT * FROM stationery_items");
while ($row = $r->fetch_assoc()) {
    $vals = [];
    foreach ($row as $v) {
        $vals[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
    }
    $sql .= "INSERT IGNORE INTO stationery_items (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(',', $vals) . ");\n";
}
$sql .= "\n";

// Export stationery_assignments
$sql .= "-- TABLE: stationery_assignments\n";
$r = $conn->query("SELECT * FROM stationery_assignments");
while ($row = $r->fetch_assoc()) {
    $vals = [];
    foreach ($row as $v) {
        $vals[] = $v === null ? 'NULL' : "'" . $conn->real_escape_string($v) . "'";
    }
    $sql .= "INSERT IGNORE INTO stationery_assignments (" . implode(', ', array_keys($row)) . ") VALUES (" . implode(',', $vals) . ");\n";
}

file_put_contents('production_stationery_update.sql', $sql);
echo "Exported to production_stationery_update.sql\n";
