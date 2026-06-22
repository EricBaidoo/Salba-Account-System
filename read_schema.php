<?php
require 'includes/db_connect.php';

$tables = $conn->query("SHOW TABLES");
while ($t = $tables->fetch_array()) {
    $tableName = $t[0];
    if (strpos($tableName, 'invoice') !== false || strpos($tableName, 'bill') !== false || strpos($tableName, 'fee') !== false || strpos($tableName, 'student') !== false) {
        echo "Table: $tableName\n";
        $cols = $conn->query("SHOW COLUMNS FROM `$tableName`");
        while ($c = $cols->fetch_assoc()) {
            echo "  - " . $c['Field'] . " (" . $c['Type'] . ")\n";
        }
    }
}
