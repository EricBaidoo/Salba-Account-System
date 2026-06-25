<?php
/**
 * Table Data Audit — Online DB
 * Upload to your server root and visit in browser, then DELETE it.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== TABLE ROW COUNTS ===" . PHP_EOL;
echo "Database: " . DB_NAME . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

$res = $conn->query("
    SELECT TABLE_NAME, TABLE_ROWS, 
           ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 1) AS size_kb
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_ROWS DESC, TABLE_NAME
");

$has_data = [];
$empty    = [];

while ($row = $res->fetch_assoc()) {
    $name = $row['TABLE_NAME'];
    // information_schema TABLE_ROWS is an estimate; do exact count for small tables
    $exact = (int)$conn->query("SELECT COUNT(*) as c FROM `$name`")->fetch_assoc()['c'];
    $kb    = $row['size_kb'];
    if ($exact > 0) $has_data[] = sprintf("  %-45s %6d rows  (%s KB)", $name, $exact, $kb);
    else            $empty[]    = sprintf("  %-45s %6d rows", $name, 0);
}

echo "── TABLES WITH DATA (" . count($has_data) . ") ──────────────────────────────" . PHP_EOL;
foreach ($has_data as $line) echo $line . PHP_EOL;

echo PHP_EOL . "── EMPTY TABLES (" . count($empty) . ") ───────────────────────────────────" . PHP_EOL;
foreach ($empty as $line) echo $line . PHP_EOL;

echo PHP_EOL . "⚠  DELETE THIS FILE from the server after viewing." . PHP_EOL;
