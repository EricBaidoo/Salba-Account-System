<?php
// Database connection for Salba Montessori Accounting System
$servername = "localhost";
$username = "root"; // Change if not using default XAMPP user
$password = "";    // Change if you set a password for root
$dbname = "Salba_acc";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>