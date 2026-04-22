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
    static $settings_cache = [];
    
    // Check cache first
    if (isset($settings_cache[$key])) {
        return $settings_cache[$key];
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $stmt->close();
        $settings_cache[$key] = $row['setting_value']; // Store in cache
        return $row['setting_value'];
    }
    
    $stmt->close();
    $settings_cache[$key] = $default;
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
 * Get the current active semester for the entire system
 * @param mysqli $conn Database connection
 * @return string Current semester (e.g., "First Semester", "Second Semester", "Third Semester")
 */
if (!function_exists('getCurrentSemester')) {
function getCurrentSemester($conn) {
    return getSystemSetting($conn, 'current_semester', '');
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
 * Get list of available semesters from dictionary
 * @param mysqli $conn Database connection
 * @return array List of semesters
 */
if (!function_exists('getAvailableSemesters')) {
function getAvailableSemesters($conn = null) {
    if (!$conn) {
        return ['First Semester', 'Second Semester', 'Trimester'];
    }
    $semesters = [];
    $res = $conn->query("SELECT semester_name FROM academic_semester_dictionary WHERE is_active = 1 ORDER BY display_order ASC");
    if($res) {
        while($row = $res->fetch_assoc()) $semesters[] = $row['semester_name'];
    }
    // Fallback if dictionary empty
    if (empty($semesters)) return ['First Semester', 'Second Semester', 'Trimester'];
    return $semesters;
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
/**
 * Calculate total instructional days in a semester
 * Logic: 
 * 1. If explicit start/end dates exist, count weekdays minus holidays in that range.
 * 2. Fallback: (Weeks * 5) - (Total holidays recorded)
 */
if (!function_exists('getInstructionalDaysCount')) {
function getInstructionalDaysCount($conn, $semester = null, $year = null, $uptoDate = null) {
    if (!$semester) $semester = getCurrentSemester($conn);
    if (!$year) $year = getAcademicYear($conn);
    
    $startDateStr = getSystemSetting($conn, 'semester_start_date');
    $endDateStr = getSystemSetting($conn, 'semester_end_date');
    
    if ($uptoDate) {
        $realEnd = min(strtotime($endDateStr), strtotime($uptoDate));
    } else {
        $realEnd = strtotime($endDateStr);
    }
    
    // Fetch Holidays/Breaks
    $holidays = [];
    $h_res = $conn->query("SELECT event_date FROM academic_calendar WHERE event_type IN ('holiday', 'break', 'mid-term')");
    if ($h_res) {
        while($row = $h_res->fetch_assoc()) $holidays[] = $row['event_date'];
    }

    if ($startDateStr && $endDateStr) {
        $count = 0;
        $current = strtotime($startDateStr);
        
        while ($current <= $realEnd) {
            $dayOfWeek = date('N', $current);
            $dateStr = date('Y-m-d', $current);
            
            if ($dayOfWeek < 6 && !in_array($dateStr, $holidays)) {
                $count++;
            }
            $current = strtotime("+1 day", $current);
        }
        return $count;
    }
    
    // Fallback logic
    $weeks = intval(getSystemSetting($conn, 'weeks_per_semester', 12));
    $total_possible = $weeks * 5;
    
    $holidays_count = 0;
    $res = $conn->query("SELECT event_date FROM academic_calendar WHERE event_type IN ('holiday', 'break', 'mid-term')");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            $dayOfWeek = date('N', strtotime($row['event_date']));
            if ($dayOfWeek < 6) { 
                $holidays_count++;
            }
        }
    }
    
    return max(0, $total_possible - $holidays_count);
}
}
/**
 * Determine the Week Number for any given date based on Semester Start
 * Returns 1 for the first week, 2 for the second, etc.
 */
if (!function_exists('getWeekNumberForDate')) {
function getWeekNumberForDate($conn, $date) {
    if (!$date) return 1;
    $start = getSystemSetting($conn, 'semester_start_date');
    if (!$start) return 1;

    $startDate = new DateTime($start);
    $targetDate = new DateTime($date);
    
    // Normalize to the start of the week (Monday) for the semester start
    // If start date is a Tuesday, Week 1 still starts at that Monday context-wise
    $startDate->modify('monday this week');
    $targetDate->modify('monday this week');
    
    $interval = $startDate->diff($targetDate);
    $days = $interval->days;
    if ($interval->invert) {
        // Target date is before semester start
        return 1;
    }
    
    $weeks = floor($days / 7) + 1;
    return (int)$weeks;
}
}
/**
 * Get the system logo path
 * @param mysqli $conn Database connection
 * @return string Path to the logo relative to root
 */
if (!function_exists('getSystemLogo')) {
function getSystemLogo($conn) {
    $logo = getSystemSetting($conn, 'system_logo', 'assets/img/salba_logo.jpg');
    return !empty($logo) ? $logo : 'assets/img/salba_logo.jpg';
}
}
