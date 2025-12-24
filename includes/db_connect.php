<?php
// Database connection for Salba Montessori Accounting System
$servername = "localhost";
$username = "u420775839_Salba_admin1"; // Change if not using default XAMPP user
$password = "Eric0056@2024";    // Change if you set a password for root
$dbname = "u420775839_Salba_acc1";

// Create connection

$conn = new mysqli($servername, $username, $password, $dbname);

// Set charset and collation for all operations
$conn->set_charset('utf8mb4');
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");
$conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
$conn->query("SET character_set_results = 'utf8mb4'");
$conn->query("SET character_set_client = 'utf8mb4'");
$conn->query("SET character_set_connection = 'utf8mb4'");
$conn->query("SET character_set_database = 'utf8mb4'");
$conn->query("SET character_set_server = 'utf8mb4'");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}