<?php
// Usage: include 'auth_check.php' at the top of any protected page
session_start();
include_once __DIR__ . '/auth_functions.php'; // Ensure functions are available
if (!is_logged_in()) {
    // Determine path back to login based on depth? 
    // Actually, most pages use a relative path.
    // auth_check.php is usually included as '.../includes/auth_check.php'
    // So if the page is at /pages/finance/foo.php, relative is ../../includes/login.php
    // To be safe, let's just make sure is_logged_in() is used.
    
    // Fallback if not logged in
    if (!isset($_SESSION['user_id'])) {
        if (!defined('BASE_URL')) {
            include_once __DIR__ . '/config.php';
        }
        $redirect = defined('BASE_URL') ? BASE_URL . 'login' : '/ACCOUNTING/login';
        header('Location: ' . $redirect);
        exit();
    }
}
?>