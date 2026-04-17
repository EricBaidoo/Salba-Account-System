<?php
/**
 * Database Configuration — SALBA Montessori Management System
 * Keep this file outside the web root in production.
 */

if (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_ADDR'] === '127.0.0.1'
) {
    // Local XAMPP environment
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
    define('DB_NAME', 'Salba_acc');
    define('BASE_URL', '/ACCOUNTING/');
} else {
    
    // Hosted environment 
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u420775839_smis_admin');
    define('DB_PASS', 'Eric0056@2024');
    define('DB_NAME', 'u420775839_smis');
    define('BASE_URL', '/'); // Set to '/' if hosted on root domain
}

