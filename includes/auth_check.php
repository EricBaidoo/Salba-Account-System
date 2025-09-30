<?php
// Usage: include 'auth_check.php' at the top of any protected page
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
?>