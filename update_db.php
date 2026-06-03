<?php
require 'includes/db_connect.php';
if ($conn->query("ALTER TABLE subjects ADD COLUMN abbreviation VARCHAR(20) DEFAULT NULL")) {
    echo "Success";
} else {
    echo "Error: " . $conn->error;
}
?>
