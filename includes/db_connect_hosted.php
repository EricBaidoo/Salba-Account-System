<?php
// Database connection for Salba Montessori Accounting System
// TEMPORARY: Using hosted database due to local MySQL corruption

$servername = "localhost"; // Your Hostinger MySQL host
$username = "u420775839_salba_admin";
$password = "Eric0056@2024";
$dbname = "u420775839_salba_acc";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to handle special characters properly
$conn->set_charset("utf8mb4");
?>