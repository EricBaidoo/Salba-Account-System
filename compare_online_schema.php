<?php
// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px; line-height: 1.6;'>";
echo "<h2>Database Schema Comparison Log</h2>";

$json_path = 'scratch/local_schema.json';
if (!file_exists($json_path)) {
    die("<div style='color: red;'>❌ Error: local_schema.json not found on server!</div>");
}

$local_schema = json_decode(file_get_contents($json_path), true);
if (!$local_schema) {
    die("<div style='color: red;'>❌ Error parsing local_schema.json!</div>");
}

// Get online tables
$tables_res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$online_tables = [];
while ($row = $tables_res->fetch_row()) {
    $online_tables[] = $row[0];
}

$missing_tables = [];
$missing_columns = [];
$mismatched_columns = [];
$sql_fixes = [];

foreach ($local_schema as $table => $table_meta) {
    if (!in_array($table, $online_tables)) {
        $missing_tables[] = $table;
        continue;
    }
    
    // Get online columns
    $cols_res = $conn->query("SHOW COLUMNS FROM `$table`");
    $online_cols = [];
    while ($col = $cols_res->fetch_assoc()) {
        $online_cols[$col['Field']] = [
            'type' => $col['Type'],
            'null' => $col['Null'],
            'key' => $col['Key'],
            'default' => $col['Default'],
            'extra' => $col['Extra']
        ];
    }
    
    // Compare columns
    foreach ($table_meta['columns'] as $col_name => $col_meta) {
        if (!isset($online_cols[$col_name])) {
            $missing_columns[] = [
                'table' => $table,
                'column' => $col_name,
                'meta' => $col_meta
            ];
            
            // Generate ALTER query
            $null_part = $col_meta['null'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default_part = '';
            if ($col_meta['default'] !== null) {
                if (strtoupper($col_meta['default']) === 'CURRENT_TIMESTAMP') {
                    $default_part = 'DEFAULT CURRENT_TIMESTAMP';
                } else {
                    $default_part = "DEFAULT '" . $conn->real_escape_string($col_meta['default']) . "'";
                }
            } elseif ($col_meta['null'] === 'YES') {
                $default_part = 'DEFAULT NULL';
            }
            $extra_part = !empty($col_meta['extra']) ? $col_meta['extra'] : '';
            
            $sql_fixes[] = "ALTER TABLE `$table` ADD COLUMN `$col_name` {$col_meta['type']} $null_part $default_part $extra_part";
            continue;
        }
        
        // Compare types (case insensitive)
        $local_type = strtolower($col_meta['type']);
        $online_type = strtolower($online_cols[$col_name]['type']);
        
        // Normalize int lengths (e.g. int vs int(11) in some systems)
        $local_type_norm = preg_replace('/\(.*\)/', '', $local_type);
        $online_type_norm = preg_replace('/\(.*\)/', '', $online_type);
        
        if ($local_type_norm !== $online_type_norm) {
            $mismatched_columns[] = [
                'table' => $table,
                'column' => $col_name,
                'local' => $local_type,
                'online' => $online_type
            ];
        }
    }
}

// Output results
if (empty($missing_tables) && empty($missing_columns) && empty($mismatched_columns)) {
    echo "<div style='color: green; font-weight: bold; margin-bottom: 20px;'>✅ No missing tables, missing columns, or type mismatches found! Your online database schema is fully aligned with local.</div>";
} else {
    if (!empty($missing_tables)) {
        echo "<h3>⚠️ Missing Tables Online:</h3>";
        echo "<ul>";
        foreach ($missing_tables as $t) {
            echo "<li style='color: red;'>$t</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($missing_columns)) {
        echo "<h3>⚠️ Missing Columns Online:</h3>";
        echo "<ul>";
        foreach ($missing_columns as $c) {
            echo "<li>Table <strong>{$c['table']}</strong>: missing column <strong style='color: red;'>{$c['column']}</strong> ({$c['meta']['type']})</li>";
        }
        echo "</ul>";
    }
    
    if (!empty($mismatched_columns)) {
        echo "<h3>ℹ️ Column Type Mismatches:</h3>";
        echo "<ul>";
        foreach ($mismatched_columns as $m) {
            echo "<li>Table <strong>{$m['table']}</strong>, Column <strong>{$m['column']}</strong>: Local is <code>{$m['local']}</code> but Online is <code>{$m['online']}</code></li>";
        }
        echo "</ul>";
    }
    
    if (!empty($sql_fixes)) {
        echo "<h3>🛠️ Suggested Fix Queries:</h3>";
        echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace;'>";
        foreach ($sql_fixes as $sql) {
            echo htmlspecialchars($sql) . ";\n";
        }
        echo "</pre>";
        
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='apply_fixes'>";
        echo "<button type='submit' style='background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: bold;'>Apply All Column Fixes</button>";
        echo "</form>";
    }
}

// Handle apply fixes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_fixes') {
    echo "<hr>";
    echo "<h3>Applying fixes...</h3>";
    $success = true;
    foreach ($sql_fixes as $sql) {
        if ($conn->query($sql)) {
            echo "<div style='color: green; margin-bottom: 5px;'>✅ Executed: " . htmlspecialchars($sql) . "</div>";
        } else {
            echo "<div style='color: red; margin-bottom: 5px;'>❌ Error on query: " . htmlspecialchars($sql) . "<br>Error: " . $conn->error . "</div>";
            $success = false;
        }
    }
    if ($success) {
        echo "<h4 style='color: green;'>All fixes applied successfully! Reload the page to verify.</h4>";
    }
}

echo "</div>";
?>
