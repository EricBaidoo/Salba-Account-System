<?php

$localHost = 'localhost';
$localUser = 'root';
$localPass = 'root';
$localDB   = 'Salba_acc';
$stagingDB = 'online_staging';

$connLoc = new mysqli($localHost, $localUser, $localPass, $localDB);
$connStg = new mysqli($localHost, $localUser, $localPass, $stagingDB);

if ($connLoc->connect_error) die("Local connection failed: " . $connLoc->connect_error);
if ($connStg->connect_error) die("Staging connection failed: " . $connStg->connect_error);

// Disable foreign key checks for the sync
$connLoc->query("SET FOREIGN_KEY_CHECKS=0");

$tablesRes = $connStg->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = [];
while ($row = $tablesRes->fetch_row()) {
    $tables[] = $row[0];
}

$queries = [];

foreach ($tables as $table) {
    // Check if table exists in local DB
    $checkLocal = $connLoc->query("SHOW TABLES LIKE '$table'");
    if ($checkLocal->num_rows === 0) {
        echo "Table '$table' does not exist in local DB. Skipping.\n";
        continue;
    }

    // Get columns for staging table
    $stgColsRes = $connStg->query("SHOW COLUMNS FROM `$table`");
    $stgCols = [];
    while ($col = $stgColsRes->fetch_assoc()) {
        $stgCols[] = $col['Field'];
    }

    // Get columns for local table
    $locColsRes = $connLoc->query("SHOW COLUMNS FROM `$table`");
    $locCols = [];
    while ($col = $locColsRes->fetch_assoc()) {
        $locCols[] = $col['Field'];
    }

    // Find intersection (columns present in both)
    $commonCols = array_intersect($stgCols, $locCols);

    if (empty($commonCols)) {
        echo "No common columns for '$table'. Skipping.\n";
        continue;
    }

    $colString = implode('`, `', $commonCols);
    $colString = "`" . $colString . "`";

    $query = "INSERT IGNORE INTO `$localDB`.`$table` ($colString) SELECT $colString FROM `$stagingDB`.`$table`";
    $queries[] = $query;
}

echo "The following synchronization queries will be executed:\n\n";
foreach ($queries as $q) {
    echo $q . ";\n";
}

// Actually run the queries
echo "\nExecuting queries...\n";
foreach ($queries as $q) {
    if (!$connLoc->query($q)) {
        echo "Error on {$q}: " . $connLoc->error . "\n";
    } else {
        echo "Success: " . substr($q, 0, 50) . "...\n";
    }
}

$connLoc->query("SET FOREIGN_KEY_CHECKS=1");

echo "\nSynchronization Complete.\n";
