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
} else {
    // Hosted environment (Hostinger)
    define('DB_HOST', 'localhost');
    define('DB_USER', 'u420775839_Salba_admin1');
    define('DB_PASS', 'Eric0056@2024');
    define('DB_NAME', 'u420775839_Salba_acc1');
}
