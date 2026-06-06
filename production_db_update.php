<?php
/**
 * PRODUCTION DATABASE UPDATER
 * -----------------------------------------------------
 * Upload this file to the root of your production server
 * and navigate to it in your browser (e.g. yourdomain.com/production_db_update.php).
 * It will safely add the new academic columns to the weekly_reports table.
 * 
 * IMPORTANT: Delete this file after running it!
 */

// Adjust this path if your production db_connect is located elsewhere
require_once 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>🚀 Production Database Updater</h2>";
echo "<p>Running migration for the <strong>weekly_reports</strong> table...</p>";
echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";

$columns_to_add = [
    'differentiation_strategies' => 'TEXT',
    'excelling_students' =>         'TEXT',
    'tlm_usage' =>                  'TEXT',
    'self_reflection' =>            'TEXT',
    'co_curricular_activities' =>   'TEXT',
    'custom_fields' =>              'TEXT'
];

$tables = ['weekly_reports', 'lesson_plans'];
$all_success = true;

foreach ($tables as $table) {
    echo "<h3>Table: $table</h3>";
    foreach ($columns_to_add as $col => $type) {
        // Check if column already exists
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        
        if ($check && $check->num_rows > 0) {
            echo "<div style='color: #666; margin-bottom: 10px;'>ℹ️ Column <strong>$col</strong> already exists in $table. Skipping.</div>";
        } else {
            // Add the column
            $sql = "ALTER TABLE `$table` ADD `$col` $type";
            if ($conn->query($sql)) {
                echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully added <strong>$col</strong> to $table</div>";
            } else {
                echo "<div style='color: red; margin-bottom: 10px;'>❌ Error adding <strong>$col</strong> to $table: " . $conn->error . "</div>";
                $all_success = false;
            }
        }
    }
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";

if ($all_success) {
    echo "<h3 style='color: green;'>Migration Complete! 🎉</h3>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ CRITICAL: Please delete this file (production_db_update.php) from your server now to maintain security.</p>";
} else {
    echo "<h3 style='color: red;'>Migration finished with errors. Please check the logs above.</h3>";
}

echo "</div>";
?>
