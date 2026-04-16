<?php
include 'includes/db_connect.php';

echo "Database Inspection: " . DB_NAME . "\n";

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "Tables in database:\n";
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
}

function describe_table($conn, $table) {
    echo "\nTable Schema: $table\n";
    try {
        $result = $conn->query("DESCRIBE $table");
        if ($result && $result->num_rows > 0) {
            printf("%-20s | %-20s | %-10s | %-10s\n", "Field", "Type", "Null", "Key");
            echo str_repeat("-", 70) . "\n";
            while ($row = $result->fetch_assoc()) {
                printf("%-20s | %-20s | %-10s | %-10s\n", $row['Field'], $row['Type'], $row['Null'], $row['Key']);
            }
        } else {
            echo "Table $table is empty or does not exist.\n";
        }
    } catch (Exception $e) {
        echo "Table $table does not exist.\n";
    }
}

describe_table($conn, 'users');
describe_table($conn, 'staff_profiles');
describe_table($conn, 'staff');
?>
