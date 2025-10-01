<?php
/**
 * Salba Montessori Accounting System - Setup Script
 * This script automatically creates the complete database structure
 * Run this ONCE after uploading files to Hostinger
 */

// Security check - remove this file after setup
if (file_exists('setup_complete.txt')) {
    die('<h1>Setup Already Complete</h1><p>The setup has already been run. Delete "setup_complete.txt" to run again.</p>');
}

include 'includes/db_connect.php';

echo '<html><head><title>Salba Montessori Setup</title></head><body>';
echo '<h1>🚀 Salba Montessori Accounting System Setup</h1>';
echo '<p>Creating database structure...</p>';

// SQL commands to create the complete database structure
$setup_queries = [
    // Create classes table
    "CREATE TABLE IF NOT EXISTS `classes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(50) DEFAULT NULL,
        `Level` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create expense_categories table
    "CREATE TABLE IF NOT EXISTS `expense_categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create expenses table
    "CREATE TABLE IF NOT EXISTS `expenses` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_id` int(11) DEFAULT NULL,
        `category` varchar(100) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `expense_date` date NOT NULL,
        `description` text DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create fees table
    "CREATE TABLE IF NOT EXISTS `fees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `amount` decimal(10,2) DEFAULT NULL,
        `fee_type` enum('fixed','class_based','category') DEFAULT 'fixed',
        `description` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create students table
    "CREATE TABLE IF NOT EXISTS `students` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `first_name` varchar(100) DEFAULT NULL,
        `last_name` varchar(100) DEFAULT NULL,
        `class` varchar(100) DEFAULT NULL,
        `date_of_birth` date DEFAULT NULL,
        `parent_contact` varchar(20) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `status` enum('active','inactive') NOT NULL DEFAULT 'active',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create users table
    "CREATE TABLE IF NOT EXISTS `users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `role` enum('admin','staff') DEFAULT 'admin',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create student_fees table (depends on students and fees)
    "CREATE TABLE IF NOT EXISTS `student_fees` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) NOT NULL,
        `fee_id` int(11) NOT NULL,
        `amount` decimal(10,2) DEFAULT 0.00,
        `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
        `term` varchar(50) DEFAULT NULL,
        `notes` text DEFAULT NULL,
        `assigned_date` datetime DEFAULT current_timestamp(),
        `due_date` date DEFAULT NULL,
        `status` enum('pending','due','paid','overdue','cancelled') DEFAULT 'pending',
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `fee_id` (`fee_id`),
        KEY `idx_student_fees_status` (`status`),
        KEY `idx_student_fees_due_date` (`due_date`),
        KEY `idx_student_fees_assigned_date` (`assigned_date`),
        CONSTRAINT `student_fees_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
        CONSTRAINT `student_fees_ibfk_2` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create payments table (depends on students and fees)
    "CREATE TABLE IF NOT EXISTS `payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `student_id` int(11) DEFAULT NULL,
        `fee_id` int(11) DEFAULT NULL,
        `payment_type` enum('student','general') NOT NULL DEFAULT 'student',
        `amount` decimal(10,2) NOT NULL,
        `payment_date` date NOT NULL,
        `receipt_no` varchar(50) DEFAULT NULL,
        `description` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        KEY `fk_payments_fee_id` (`fee_id`),
        CONSTRAINT `fk_payments_fee_id` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`),
        CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create fee_amounts table (depends on fees)
    "CREATE TABLE IF NOT EXISTS `fee_amounts` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `fee_id` int(11) NOT NULL,
        `class_name` varchar(50) DEFAULT NULL,
        `category` varchar(50) DEFAULT NULL,
        `amount` decimal(10,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_fee_class` (`fee_id`,`class_name`),
        KEY `idx_fee_category` (`fee_id`,`category`),
        CONSTRAINT `fee_amounts_ibfk_1` FOREIGN KEY (`fee_id`) REFERENCES `fees` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    // Create payment_allocations table (depends on payments and student_fees)
    "CREATE TABLE IF NOT EXISTS `payment_allocations` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `payment_id` int(11) NOT NULL,
        `student_fee_id` int(11) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `payment_id` (`payment_id`),
        KEY `student_fee_id` (`student_fee_id`),
        CONSTRAINT `payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
        CONSTRAINT `payment_allocations_ibfk_2` FOREIGN KEY (`student_fee_id`) REFERENCES `student_fees` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Initial data inserts
$initial_data = [
    // Insert default classes
    "INSERT IGNORE INTO `classes` (`name`, `Level`) VALUES
    ('Creche', 'Early Years'),
    ('Nursery 1', 'Early Years'),
    ('Nursery 2', 'Early Years'),
    ('KG 1', 'Early Years'),
    ('KG 2', 'Early Years'),
    ('Basic 1', 'Primary'),
    ('Basic 2', 'Primary'),
    ('Basic 3', 'Primary'),
    ('Basic 4', 'Primary'),
    ('Basic 5', 'Primary'),
    ('Basic 6', 'Primary'),
    ('Basic 7', 'Primary');",

    // Insert default expense categories
    "INSERT IGNORE INTO `expense_categories` (`name`) VALUES
    ('Utilities'),
    ('Office Supplies'),
    ('Maintenance'),
    ('Staff Salaries'),
    ('Transportation'),
    ('Food & Catering'),
    ('Educational Materials');",

    // Insert default fees
    "INSERT IGNORE INTO `fees` (`name`, `fee_type`, `description`) VALUES
    ('Tuition Fee', 'class_based', 'Monthly tuition fees'),
    ('Feeding Fee', 'category', 'Daily feeding charges'),
    ('Registration Fee', 'fixed', 'One-time registration fee'),
    ('Uniform Fee', 'fixed', 'School uniform charges'),
    ('Book Fee', 'class_based', 'Textbooks and materials');"
];

// Create view
$view_query = "CREATE OR REPLACE VIEW `v_fee_assignments` AS 
SELECT 
    `sf`.`id` AS `assignment_id`,
    CONCAT(`s`.`first_name`,' ',`s`.`last_name`) AS `student_name`,
    `s`.`class` AS `student_class`,
    `f`.`name` AS `fee_name`,
    `f`.`fee_type` AS `fee_type`,
    `sf`.`amount` AS `amount`,
    `sf`.`due_date` AS `due_date`,
    `sf`.`term` AS `term`,
    `sf`.`assigned_date` AS `assigned_date`,
    `sf`.`status` AS `status`,
    `sf`.`notes` AS `notes`,
    TO_DAYS(`sf`.`due_date`) - TO_DAYS(CURDATE()) AS `days_to_due`,
    CASE 
        WHEN `sf`.`status` = 'paid' THEN 'Paid' 
        WHEN `sf`.`due_date` < CURDATE() AND `sf`.`status` = 'pending' THEN 'Overdue' 
        WHEN TO_DAYS(`sf`.`due_date`) - TO_DAYS(CURDATE()) <= 7 AND `sf`.`status` = 'pending' THEN 'Due Soon' 
        ELSE 'Pending' 
    END AS `payment_status` 
FROM ((`student_fees` `sf` JOIN `students` `s` ON(`sf`.`student_id` = `s`.`id`)) 
JOIN `fees` `f` ON(`sf`.`fee_id` = `f`.`id`)) 
WHERE `sf`.`status` <> 'cancelled' 
ORDER BY `sf`.`due_date` DESC,`s`.`class`,`s`.`first_name`;";

$success_count = 0;
$error_count = 0;

// Execute table creation queries
echo '<h2>📊 Creating Database Tables</h2>';
foreach ($setup_queries as $query) {
    if ($conn->query($query) === TRUE) {
        $success_count++;
        echo '<p>✅ Table created successfully</p>';
    } else {
        $error_count++;
        echo '<p>❌ Error: ' . $conn->error . '</p>';
    }
}

// Insert initial data
echo '<h2>📝 Inserting Default Data</h2>';
foreach ($initial_data as $query) {
    if ($conn->query($query) === TRUE) {
        $success_count++;
        echo '<p>✅ Default data inserted</p>';
    } else {
        $error_count++;
        echo '<p>❌ Error: ' . $conn->error . '</p>';
    }
}

// Create view
echo '<h2>👁️ Creating Database View</h2>';
if ($conn->query($view_query) === TRUE) {
    $success_count++;
    echo '<p>✅ View created successfully</p>';
} else {
    $error_count++;
    echo '<p>❌ Error creating view: ' . $conn->error . '</p>';
}

// Summary
echo '<h2>📋 Setup Summary</h2>';
echo "<p><strong>✅ Successful operations:</strong> $success_count</p>";
echo "<p><strong>❌ Failed operations:</strong> $error_count</p>";

if ($error_count == 0) {
    echo '<h2>🎉 Setup Complete!</h2>';
    echo '<p><strong>Your Salba Montessori Accounting System is ready!</strong></p>';
    echo '<ul>';
    echo '<li>✅ All 11 database tables created</li>';
    echo '<li>✅ Default classes (Creche to Basic 7) added</li>';
    echo '<li>✅ Default expense categories added</li>';
    echo '<li>✅ Default fee types added</li>';
    echo '<li>✅ Database view created</li>';
    echo '</ul>';
    
    // Create setup complete marker
    file_put_contents('setup_complete.txt', 'Setup completed on ' . date('Y-m-d H:i:s'));
    
    echo '<h3>🔐 Next Steps:</h3>';
    echo '<ol>';
    echo '<li><strong>Create Admin User:</strong> <a href="pages/register.php">Register Admin Account</a></li>';
    echo '<li><strong>Access Dashboard:</strong> <a href="pages/login.php">Login to System</a></li>';
    echo '<li><strong>Security:</strong> Delete this setup.php file after testing</li>';
    echo '</ol>';
    
    echo '<div style="background: #d4edda; padding: 15px; border-radius: 10px; margin: 20px 0;">';
    echo '<h4>🚀 Quick Start Links:</h4>';
    echo '<p><a href="pages/register.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Create Admin Account</a></p>';
    echo '</div>';
    
} else {
    echo '<h2>⚠️ Setup Issues</h2>';
    echo '<p>There were some errors during setup. Please check your database connection and try again.</p>';
    echo '<p><strong>Common solutions:</strong></p>';
    echo '<ul>';
    echo '<li>Verify database credentials in includes/db_connect.php</li>';
    echo '<li>Ensure database user has CREATE and INSERT privileges</li>';
    echo '<li>Check if database name is correct</li>';
    echo '</ul>';
}

$conn->close();
echo '</body></html>';
?>