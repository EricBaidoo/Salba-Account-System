<?php
require 'includes/db_connect.php';
$sql = file_get_contents('sql/create_appraisal_tables.sql');

if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Appraisal tables created successfully.\n";
} else {
    echo "Error creating tables: " . $conn->error . "\n";
}
?>
