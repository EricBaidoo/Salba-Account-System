<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- DEEP TERMINOLOGY AUDIT ---\n\n";

// 1. Check all tables for columns named 'term' or 'semester'
$tablesRes = $conn->query("SHOW TABLES");
while ($tRow = $tablesRes->fetch_array()) {
    $table = $tRow[0];
    $colsRes = $conn->query("DESCRIBE `$table`");
    while ($cRow = $colsRes->fetch_assoc()) {
        $col = $cRow['Field'];
        if (stripos($col, 'term') !== false || stripos($col, 'semester') !== false) {
            echo "TABLE: $table | COLUMN: $col\n";
        }
    }
}

echo "\n--- SETTINGS KEYS WITH 'TERM' ---\n";
$settingsRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%term%' OR setting_key LIKE '%semester%'");
while ($sRow = $settingsRes->fetch_assoc()) {
    echo "KEY: " . $sRow['setting_key'] . " | VALUE: " . substr($sRow['setting_value'], 0, 50) . "...\n";
}

echo "\n--- DISTINCT VALUES IN KEY TABLES ---\n";
$tablesToAudit = ['payments', 'expenses', 'student_fees', 'term_invoices'];
foreach ($tablesToAudit as $t) {
    echo "\nTable: $t\n";
    // Check if 'semester' or 'term' columns exist first
    $cols = [];
    $res = $conn->query("DESCRIBE `$t`");
    while($r = $res->fetch_assoc()) $cols[] = $r['Field'];

    foreach (['semester', 'term'] as $c) {
        if (in_array($c, $cols)) {
            echo "  Distinct values in '$c':\n";
            $vRes = $conn->query("SELECT DISTINCT `$c` as v, COUNT(*) as cnt FROM `$t` GROUP BY `$c` ");
            while($vRow = $vRes->fetch_assoc()) {
                echo "    - [" . ($vRow['v']??'NULL') . "]: (" . $vRow['cnt'] . " items)\n";
            }
        }
    }
}
?>
