<?php
include 'C:\xampp\htdocs\ACCOUNTING\includes\db_connect.php';

$res = $conn->query("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'staff'");

if ($res) {
    echo "SUCCESS: role column changed to VARCHAR(50).";
} else {
    echo "ERROR: " . $conn->error;
}
?>
