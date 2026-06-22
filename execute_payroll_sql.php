<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Payroll Tables Creation Log</h2>";

$queries = [
    "staff_salary_structures table" => "
    CREATE TABLE IF NOT EXISTS `staff_salary_structures` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `staff_id` INT(11) NOT NULL,
        `base_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `custom_allowances` TEXT DEFAULT NULL,
        `custom_deductions` TEXT DEFAULT NULL,
        `tier_1_ssnit_employer` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `tier_1_ssnit_employee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `tier_2_ssnit` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `bank_name` VARCHAR(100) DEFAULT NULL,
        `account_number` VARCHAR(50) DEFAULT NULL,
        `branch` VARCHAR(100) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_staff_salary (staff_id),
        CONSTRAINT fk_salary_staff FOREIGN KEY (staff_id) REFERENCES staff_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "payroll_runs table" => "
    CREATE TABLE IF NOT EXISTS `payroll_runs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `payroll_month` TINYINT(2) NOT NULL,
        `payroll_year` INT(4) NOT NULL,
        `total_gross` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_net` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `total_employer_ssnit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `status` ENUM('draft','approved','paid') NOT NULL DEFAULT 'draft',
        `created_by` INT(11) NULL,
        `approved_by` INT(11) NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_run_month_year (payroll_month, payroll_year),
        CONSTRAINT fk_run_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_run_approver FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ",

    "payroll_records table" => "
    CREATE TABLE IF NOT EXISTS `payroll_records` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `payroll_run_id` INT(11) NOT NULL,
        `staff_id` INT(11) NOT NULL,
        `base_salary` DECIMAL(10,2) NOT NULL,
        `custom_allowances` TEXT DEFAULT NULL,
        `custom_deductions` TEXT DEFAULT NULL,
        `global_taxes` TEXT DEFAULT NULL,
        `tier_1_employee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `tier_2_employee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `net_salary` DECIMAL(10,2) NOT NULL,
        `status` ENUM('pending','paid') NOT NULL DEFAULT 'pending',
        `payment_method` VARCHAR(50) DEFAULT 'Bank Transfer',
        `payment_date` DATE DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_record_run_staff (payroll_run_id, staff_id),
        CONSTRAINT fk_record_run FOREIGN KEY (payroll_run_id) REFERENCES payroll_runs(id) ON DELETE CASCADE,
        CONSTRAINT fk_record_staff FOREIGN KEY (staff_id) REFERENCES staff_profiles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    "
];

$all_success = true;
foreach ($queries as $name => $sql) {
    if ($conn->query($sql)) {
        echo "<div style='color: green; margin-bottom: 10px;'>✅ Successfully created/verified table <strong>$name</strong>.</div>";
    } else {
        echo "<div style='color: red; margin-bottom: 10px;'>❌ Error creating table <strong>$name</strong>: " . $conn->error . "</div>";
        $all_success = false;
    }
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
if ($all_success) {
    echo "<h3 style='color: green;'>Payroll database setup complete! 🎉</h3>";
} else {
    echo "<h3 style='color: red;'>Setup finished with errors. Please check the logs above.</h3>";
}
echo "</div>";
?>
