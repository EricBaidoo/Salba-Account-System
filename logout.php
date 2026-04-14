<?php
/**
 * Logout — SALBA Montessori Management System
 * Root-level logout handler. Safe to call from any depth.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();

// Always redirect to login via absolute path from root
header('Location: includes/login.php');
exit();
