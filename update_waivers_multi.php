<?php
require 'includes/db_connect.php';

// First, we need to find the foreign key name
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$fk_res = $conn->query("
    SELECT CONSTRAINT_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = '$db_name' 
      AND TABLE_NAME = 'scholarships' 
      AND COLUMN_NAME = 'applies_to_fee_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fk_res && $fk_res->num_rows > 0) {
    $fk_name = $fk_res->fetch_assoc()['CONSTRAINT_NAME'];
    $conn->query("ALTER TABLE scholarships DROP FOREIGN KEY `$fk_name`");
}

// Change the column
$conn->query("ALTER TABLE scholarships CHANGE applies_to_fee_id applies_to_fees VARCHAR(255) NULL DEFAULT '[]'");
if ($conn->error) echo "Error: " . $conn->error . "<br>";
else echo "Database updated successfully to support multiple fees.";
