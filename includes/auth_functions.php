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

if (!function_exists('has_role')) {
    function has_role($roles) {
        if (!isset($_SESSION['user_id'])) return false;
        $user_role = $_SESSION['role'] ?? 'staff';
        if (is_array($roles)) return in_array($user_role, $roles);
        return $user_role === $roles;
    }
}

if (!function_exists('require_role')) {
    function require_role($roles) {
        if (!has_role($roles)) {
            // Dynamic path calculation to reach the root index.php
            // We look at the current script's depth relative to the root /ACCOUNTING/
            $script_path = $_SERVER['SCRIPT_NAME'];
            $parts = explode('/', trim($script_path, '/'));
            // Find 'ACCOUNTING' (or find 'pages' and go up 1)
            $pages_idx = array_search('pages', $parts);
            if ($pages_idx !== false) {
                $depth = count($parts) - $pages_idx;
                $back = str_repeat('../', $depth);
            } else {
                $back = './';
            }
            header('Location: ' . $back . 'index.php?error=unauthorized_access');
            exit;
        }
    }
}

if (!function_exists('require_finance_access')) {
    function require_finance_access() {
        require_role(['admin', 'bursar', 'academic_supervisor']);
    }
}

if (!function_exists('require_finance_write')) {
    function require_finance_write() {
        require_role(['admin', 'bursar']);
    }
}
?>