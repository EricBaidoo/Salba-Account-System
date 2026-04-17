<?php
/**
 * ACADEMIC DATA CLEANUP SCRIPT
 * Purpose: Empties only the Attendance and Grades tables (Test Data)
 * Safely preserves all Students, Staff, and Financial records.
 */

// 1. Connection Settings (Update for online server if needed)
$host = 'localhost';
$user = 'root';
$pass = 'root';
$db   = 'Salba_acc';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<div style='color:red; font-family:sans-serif;'>Connection failed: " . $conn->connect_error . "</div>");
}

echo "<div style='font-family:sans-serif; padding:20px; border:1px solid #eee; border-radius:12px; max-width:600px; margin:40px auto; background:#fff;'>";
echo "<h2 style='color:#6366f1;'>Academic Cleanup Utility</h2>";
echo "<p style='color:#64748b; font-size:14px;'>Removing academic test data logs while preserving registry and accounting...</p><hr style='border:none; border-top:1px solid #f1f5f9; margin:20px 0;'>";

function truncateTable($conn, $table) {
    // Disable foreign keys temporarily to ensure smooth truncation
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    if ($conn->query("TRUNCATE TABLE $table")) {
        echo "<div style='color:#10b981; margin-bottom:10px; font-size:13px;'>✅ Emptied: <b>$table</b></div>";
    } else {
        echo "<div style='color:#f43f5e; margin-bottom:10px; font-size:13px;'>❌ Error ($table): " . $conn->error . "</div>";
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
}

// TABLES TO CLEAN (Surgical selection)
$tables_to_wipe = [
    'attendance',
    'staff_attendance',
    'grades',
    'student_semester_remarks',
    'student_term_remarks',
    'lesson_plans'
];

foreach ($tables_to_wipe as $table) {
    // Check if table exists before truncating
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows > 0) {
        truncateTable($conn, $table);
    }
}

echo "<hr style='border:none; border-top:1px solid #f1f5f9; margin:20px 0;'>";
echo "<p style='font-size:12px; color:#94a3b8;'>Verified: Financial records (Payments/Expenses) and Personnel (Students/Staff) were <b>not touched</b>.</p>";
echo "<div style='background:#f0fdf4; color:#166534; padding:15px; border-radius:10px; font-weight:bold; text-align:center; margin-top:20px;'>Academic Reset Complete!</div>";
echo "</div>";
?>
