<?php
// Authentication functions for login system
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

if (!function_exists('login')) {
    function login($username, $password) {
        global $conn;
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('logout')) {
    function logout() {
        session_unset();
        session_destroy();
    }
}