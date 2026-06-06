<?php
/**
 * PRODUCTION DATABASE UPDATER (SMS MODULE)
 * -----------------------------------------------------
 * Upload this file to the root of your production server
 * and navigate to it in your browser.
 * 
 * IMPORTANT: Delete this file after running it!
 */

require_once 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>🚀 SMS Gateway Database Updater</h2>";
echo "<p>Running migration for the <strong>Communication Module</strong>...</p>";
echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";

$queries = [
    "sms_providers table" => "CREATE TABLE IF NOT EXISTS `sms_providers` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `engine_type` VARCHAR(50) NOT NULL,
        `endpoint_url` VARCHAR(255),
        `balance_endpoint_url` VARCHAR(255),
        `http_method` VARCHAR(10),
        `payload_type` VARCHAR(20),
        `auth_header` TEXT,
        `param_recipient` VARCHAR(50),
        `param_message` VARCHAR(50),
        `param_sender` VARCHAR(50),
        `api_key` VARCHAR(255),
        `active_sender_id` VARCHAR(50),
        `success_keyword` VARCHAR(100),
        `is_active` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "sms_templates table" => "CREATE TABLE IF NOT EXISTS `sms_templates` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `template_name` VARCHAR(150) NOT NULL,
        `sender_id` VARCHAR(15) NULL,
        `message_body` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "sms_logs table" => "CREATE TABLE IF NOT EXISTS `sms_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `recipient_phone` VARCHAR(50) NOT NULL,
        `message_body` TEXT NOT NULL,
        `sender_id` VARCHAR(50) NULL,
        `provider` VARCHAR(50) NULL,
        `status` VARCHAR(50) NULL,
        `api_response` TEXT,
        `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "parents table" => "CREATE TABLE IF NOT EXISTS `parents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(50) NULL,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `relationship` VARCHAR(50) NULL,
        `phone` VARCHAR(100) NULL,
        `email` VARCHAR(100) NULL,
        `address` TEXT NULL,
        `is_primary` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    )",
    "student_parents table" => "CREATE TABLE IF NOT EXISTS `student_parents` (
        `student_id` INT NOT NULL,
        `parent_id` INT NOT NULL,
        `relationship` VARCHAR(50) NULL,
        `is_primary` TINYINT(1) DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`student_id`, `parent_id`),
        FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`parent_id`) REFERENCES `parents`(`id`) ON DELETE CASCADE
    )"
];

$all_success = true;

foreach ($queries as $name => $sql) {
    if ($conn->query($sql)) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully created/verified <strong>$name</strong></div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error creating <strong>$name</strong>: " . $conn->error . "</div>";
        $all_success = false;
    }
}

// Check and Add 'title' column to parents table
$check = $conn->query("SHOW COLUMNS FROM `parents` LIKE 'title'");
if ($check && $check->num_rows > 0) {
    echo "<div style='color: #666; margin-bottom: 10px;'>ℹ️ Column <strong>title</strong> already exists in parents. Skipping.</div>";
} else {
    $sql = "ALTER TABLE `parents` ADD COLUMN `title` VARCHAR(50) NULL AFTER `id`";
    if ($conn->query($sql)) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully added <strong>title</strong> column to parents table.</div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error adding <strong>title</strong> column: " . $conn->error . "</div>";
        $all_success = false;
    }
}

// Ensure 'phone' column can hold multiple comma-separated numbers safely (increase length to 100)
$sql = "ALTER TABLE `parents` MODIFY `phone` VARCHAR(100) NULL";
if ($conn->query($sql)) {
    echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully verified <strong>phone</strong> column length in parents table.</div>";
} else {
    echo "<div style='color: red; margin-bottom: 10px;'>❌ Error modifying <strong>phone</strong> column: " . $conn->error . "</div>";
    $all_success = false;
}

// Make student fields optional
$alter_students_queries = [
    "ALTER TABLE `students` MODIFY `date_of_birth` DATE NULL",
    "ALTER TABLE `students` MODIFY `parent_contact` VARCHAR(50) NULL",
    "ALTER TABLE `students` MODIFY `address` TEXT NULL",
    "ALTER TABLE `students` MODIFY `place_of_birth` VARCHAR(100) NULL",
    "ALTER TABLE `students` MODIFY `emergency_contact` VARCHAR(50) NULL",
    "ALTER TABLE `students` MODIFY `photo_path` VARCHAR(255) NULL",
    "ALTER TABLE `students` MODIFY `landmark` VARCHAR(100) NULL",
    "ALTER TABLE `students` MODIFY `date_admitted` DATE NULL",
    "ALTER TABLE `students` MODIFY `previous_school` VARCHAR(150) NULL"
];

foreach ($alter_students_queries as $sql) {
    if (!$conn->query($sql)) {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error making student field optional: " . $conn->error . "</div>";
        $all_success = false;
    }
}
if ($all_success) {
    echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully made student fields optional in the students table.</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";

if ($all_success) {
    echo "<h3 style='color: green;'>SMS Migration Complete! 🎉</h3>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ CRITICAL: Please delete this file (production_db_update.php) from your server now to maintain security.</p>";
} else {
    echo "<h3 style='color: red;'>Migration finished with errors. Please check the logs above.</h3>";
}

echo "</div>";
?>
