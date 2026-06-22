<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Database Upgrade Log</h2>";

function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

function safeDropColumn($conn, $table, $column) {
    if (!tableExists($conn, $table)) {
        echo "<div style='color: orange; margin-bottom: 5px;'>⚠️ Table <strong>$table</strong> does not exist. Skipping dropping column <strong>$column</strong>.</div>";
        return;
    }
    if (columnExists($conn, $table, $column)) {
        if ($conn->query("ALTER TABLE `$table` DROP COLUMN `$column`")) {
            echo "<div style='color: green; margin-bottom: 5px;'>✅ Successfully dropped column <strong>$column</strong> from table <strong>$table</strong>.</div>";
        } else {
            echo "<div style='color: red; margin-bottom: 5px;'>❌ Error dropping column <strong>$column</strong> from <strong>$table</strong>: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color: #777; margin-bottom: 5px;'>ℹ️ Column <strong>$column</strong> does not exist in <strong>$table</strong>. Skipping drop.</div>";
    }
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

// 1. Safe Truncates
// Disable foreign key checks temporarily to allow truncating referenced tables
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$tables_to_truncate = ['payroll_records', 'payroll_runs', 'staff_salary_structures'];
foreach ($tables_to_truncate as $table) {
    if (tableExists($conn, $table)) {
        if ($conn->query("TRUNCATE TABLE `$table`")) {
            echo "<div style='color: green; margin-bottom: 5px;'>✅ Truncated table <strong>$table</strong>.</div>";
        } else {
            echo "<div style='color: red; margin-bottom: 5px;'>❌ Error truncating table <strong>$table</strong>: " . $conn->error . "</div>";
        }
    } else {
        echo "<div style='color: orange; margin-bottom: 5px;'>⚠️ Table <strong>$table</strong> does not exist. Skipping truncate.</div>";
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// 2. Alter staff_salary_structures
echo "<h3>Altering staff_salary_structures:</h3>";
safeDropColumn($conn, 'staff_salary_structures', 'allowances');
safeDropColumn($conn, 'staff_salary_structures', 'deductions');
safeDropColumn($conn, 'staff_salary_structures', 'deduction_reason');
safeDropColumn($conn, 'staff_salary_structures', 'income_tax');
safeAddColumn($conn, 'staff_salary_structures', 'custom_allowances', 'TEXT DEFAULT NULL', 'base_salary');
safeAddColumn($conn, 'staff_salary_structures', 'custom_deductions', 'TEXT DEFAULT NULL', 'custom_allowances');

// 3. Alter payroll_records
echo "<h3>Altering payroll_records:</h3>";
safeDropColumn($conn, 'payroll_records', 'allowances');
safeDropColumn($conn, 'payroll_records', 'deductions');
safeDropColumn($conn, 'payroll_records', 'deduction_reason');
safeDropColumn($conn, 'payroll_records', 'income_tax');
safeAddColumn($conn, 'payroll_records', 'custom_allowances', 'TEXT DEFAULT NULL', 'base_salary');
safeAddColumn($conn, 'payroll_records', 'custom_deductions', 'TEXT DEFAULT NULL', 'custom_allowances');
safeAddColumn($conn, 'payroll_records', 'global_taxes', 'TEXT DEFAULT NULL', 'custom_deductions');

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Upgrade script completed execution.</h3>";
echo "</div>";
