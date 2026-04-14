<?php
/**
 * System Settings Helper Functions
 * Centralized settings management for the entire accounting system
 */

/**
 * Get a system setting value
 * @param mysqli $conn Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
if (!function_exists('getSystemSetting')) {
function getSystemSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        return $row['setting_value'];
    }
    
    $stmt->close();
    return $default;
}
}

/**
 * Set a system setting value
 * @param mysqli $conn Database connection
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param string $updated_by Username of who updated it
 * @return bool Success status
 */
if (!function_exists('setSystemSetting')) {
function setSystemSetting($conn, $key, $value, $updated_by = 'System') {
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->bind_param("sssss", $key, $value, $updated_by, $value, $updated_by);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
}

/**
 * Get the current active term for the entire system
 * @param mysqli $conn Database connection
 * @return string Current term (e.g., "First Term", "Second Term", "Third Term")
 */
if (!function_exists('getCurrentTerm')) {
function getCurrentTerm($conn) {
    return getSystemSetting($conn, 'current_term', '');
}
}

/**
 * Get the current academic year
 * @param mysqli $conn Database connection
 * @return string Academic year (e.g., "2024/2025")
 */
if (!function_exists('getAcademicYear')) {
function getAcademicYear($conn) {
    return getSystemSetting($conn, 'academic_year', '');
}
}

/**
 * Get all system settings
 * @param mysqli $conn Database connection
 * @return array Associative array of all settings
 */
if (!function_exists('getAllSettings')) {
function getAllSettings($conn) {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value, description, updated_at, updated_by FROM system_settings ORDER BY setting_key");
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row;
    }
    
    return $settings;
}
}

/**
 * Get list of available terms
 * @return array List of terms
 */
if (!function_exists('getAvailableTerms')) {
function getAvailableTerms() {
    return ['First Term', 'Second Term', 'Third Term'];
}
}

/**
 * Format an academic year string for display using system preference.
 * Stored value should be canonical YYYY/YYYY. Display can be 'full' or 'short'.
 * Examples: full => 2025/2026, short => 2025/26
 */
if (!function_exists('formatAcademicYearDisplay')) {
function formatAcademicYearDisplay($conn, $academic_year) {
    if (!$academic_year) {
        $academic_year = getAcademicYear($conn);
    }
    $fmt = getSystemSetting($conn, 'academic_year_format', 'full');
    $parts = explode('/', $academic_year);
    if (count($parts) !== 2) {
        return $academic_year; // fallback
    }
    $startY = intval($parts[0]);
    $endRaw = $parts[1];
    $endY = intval($endRaw);
    if ($fmt === 'short') {
        return $startY . '/' . substr((string)$endY, -2);
    }
    return $startY . '/' . $endY;
}
}
