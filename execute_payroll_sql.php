<?php
require 'includes/db_connect.php';

$sql = file_get_contents('sql/create_payroll_tables.sql');
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Payroll tables created successfully.";
} else {
    echo "Error creating tables: " . $conn->error;
}
