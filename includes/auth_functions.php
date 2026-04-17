<?php
// 1. Session Security Hardening (ONLY if session hasn't started yet)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1); // Prevent JS from reading cookies
    ini_set('session.use_only_cookies', 1); // Prevent session ID in URL
    ini_set('session.cookie_samesite', 'Lax'); // Basic CSRF protection for cookies
    session_start();
}

/**
 * XSS Protection Shorthand - Escape HTML output
 */
if (!function_exists('h')) {
    function h($text) {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }
}

/**
 * CSRF Protection - Generate Token
 */
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/**
 * CSRF Protection - Verify Token
 */
if (!function_exists('verify_csrf')) {
    function verify_csrf($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

include 'db_connect.php';

if (!function_exists('login')) {
    function login($username, $password) {
        global $conn;
        $stmt = $conn->prepare("SELECT id, password, role, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                if (!$row['is_active']) return 'account_disabled';
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $row['role'];
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
            header('Location: ' . BASE_URL . 'index?error=unauthorized_access');
            exit;
        }
    }
}

if (!function_exists('require_finance_access')) {
    function require_finance_access() {
        require_role(['admin', 'bursar', 'supervisor']);
    }
}

if (!function_exists('require_finance_write')) {
    function require_finance_write() {
        require_role(['admin', 'bursar']);
    }
}

/**
 * FLASH MESSAGES & REDIRECTION (PRG Pattern)
 */

if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        if (!isset($_SESSION['flash_messages'])) {
            $_SESSION['flash_messages'] = [];
        }
        $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('get_flash')) {
    function get_flash() {
        $messages = $_SESSION['flash_messages'] ?? [];
        unset($_SESSION['flash_messages']);
        return $messages;
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $flashType = null, $flashMessage = null) {
        if ($flashType && $flashMessage) {
            set_flash($flashType, $flashMessage);
        }
        header("Location: $url");
        exit;
    }
}

/**
 * GLOBAL AUDIT LOGGING
 * Logs user actions with optional state change tracking (old vs new)
 */
if (!function_exists('log_activity')) {
    function log_activity($conn, $action, $description, $old = null, $new = null) {
        $uid = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        
        // Handle array/object payloads
        $old_str = (is_array($old) || is_object($old)) ? json_encode($old) : $old;
        $new_str = (is_array($new) || is_object($new)) ? json_encode($new) : $new;

        $stmt = $conn->prepare("INSERT INTO system_audit_logs (user_id, action, description, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssss", $uid, $action, $description, $old_str, $new_str, $ip, $ua);
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * PROFILE DATA RETRIEVAL
 * Fetches combined user and staff profile data
 */
if (!function_exists('get_user_profile_data')) {
    function get_user_profile_data($conn, $uid) {
        $stmt = $conn->prepare("
            SELECT u.username, u.role, u.is_active, u.created_at as account_created,
                   sp.* 
            FROM users u
            LEFT JOIN staff_profiles sp ON u.id = sp.user_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

/**
 * SECURE PASSWORD UPDATE
 */
if (!function_exists('update_user_password')) {
    function update_user_password($conn, $uid, $current_password, $new_password) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        
        if (!$res || !password_verify($current_password, $res['password'])) {
            return ['success' => false, 'message' => 'Current password verification failed.'];
        }

        // Hash and update
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->bind_param("si", $hashed, $uid);
        if ($upd->execute()) {
            log_activity($conn, 'Security', 'User updated their account password.');
            return ['success' => true];
        }
        return ['success' => false, 'message' => 'Database error during password update.'];
    }
}
?>