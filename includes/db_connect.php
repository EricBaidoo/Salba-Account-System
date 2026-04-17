<?php
/**
 * Database Connection — SALBA Montessori Management System
 */



require_once __DIR__ . '/config.php';

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset and collation for all operations
$conn->set_charset('utf8mb4');
$conn->query("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
$conn->query("SET collation_connection = 'utf8mb4_unicode_ci'");
$conn->query("SET character_set_results = 'utf8mb4'");
$conn->query("SET character_set_client = 'utf8mb4'");
$conn->query("SET character_set_connection = 'utf8mb4'");