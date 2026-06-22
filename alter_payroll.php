<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Payroll Database Alter Log</h2>";

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function safeAddColumn($conn, $table, $column, $definition, $afterColumn = '') {
    if (!tableExists($conn, $table)) {
        echo "<div style='color: red; margin-bottom: 5px;'>❌ Table <strong>$table</strong> does not exist. Cannot add column <strong>$column</strong>.</div>";
        return;
    }
    if (!columnExists($conn, $table, $column)) {
        $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $definition";
        if (!empty($afterColumn) && columnExists($conn, $table, $afterColumn)) {
            $sql .= " AFTER `$afterColumn`";
        }
        if ($conn->query($sql)) {
            echo "<div style='color: green; margin-bottom: 5px;'>✅ Successfully added column <strong>$column</strong> to table <strong>$table</strong>.</div>";
        } else {
            echo "<div style='color: red; margin-bottom: 5px;'>❌ Error adding column <strong>$column</strong> to <strong>$table</strong>: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color: #777; margin-bottom: 5px;'>ℹ️ Column <strong>$column</strong> already exists in <strong>$table</strong>. Skipping add.</div>";
    }
}

safeAddColumn($conn, 'staff_salary_structures', 'deduction_reason', 'VARCHAR(255) DEFAULT NULL', 'deductions');
safeAddColumn($conn, 'payroll_records', 'deduction_reason', 'VARCHAR(255) DEFAULT NULL', 'deductions');

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Alter script completed execution.</h3>";
echo "</div>";
