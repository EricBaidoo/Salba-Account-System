<?php
/**
 * Staff Profile Schema Migration
 * Safe for MySQL 5.7+ — uses INFORMATION_SCHEMA for all conditional checks
 */
function run_staff_migration($conn) {

    // 1. Fix the users.role column
    $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'staff'");

    // 2. Get current DB name for all INFORMATION_SCHEMA queries
    $db = $conn->query("SELECT DATABASE()")->fetch_row()[0];

    // Helper: safely add a column if it doesn't already exist
    $add_col = function($table, $col, $definition) use ($conn, $db) {
        $exists = $conn->query("
            SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$col'
        ")->fetch_assoc()['cnt'];
        if ($exists == 0) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN `$col` $definition");
        }
    };

    // 3. Add staff_id to users if missing
    $add_col('users', 'staff_id', 'INT NULL DEFAULT NULL');

    // 4. Create staff_profiles base table (minimal — columns added below)
    $conn->query("
        CREATE TABLE IF NOT EXISTS staff_profiles (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(200) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 5. Ensure every column exists (safe to run on tables that already exist)
    $columns = [
        'staff_code'             => "VARCHAR(20) NULL",
        'date_of_birth'          => "DATE NULL",
        'marital_status'         => "VARCHAR(50) NULL",
        'nationality'            => "VARCHAR(100) NULL DEFAULT 'Ghanaian'",
        'religion'               => "VARCHAR(100) NULL",
        'languages_spoken'       => "VARCHAR(255) NULL",
        'phone_number'           => "VARCHAR(30) NULL",
        'ghana_card_no'          => "VARCHAR(50) NULL",
        'ssnit_number'           => "VARCHAR(50) NULL",
        'photo_path'             => "VARCHAR(255) NULL",
        'address'                => "TEXT NULL",
        'landmark'               => "VARCHAR(255) NULL",
        'hometown'               => "VARCHAR(150) NULL",
        'job_title'              => "VARCHAR(150) NULL",
        'department'             => "VARCHAR(100) NULL",
        'highest_qualification'  => "VARCHAR(150) NULL",
        'entry_qualification'    => "VARCHAR(150) NULL",
        'first_appointment_date' => "DATE NULL",
        'employment_status'      => "VARCHAR(30) DEFAULT 'active'",
        'bank_name'              => "VARCHAR(150) NULL",
        'bank_account_no'        => "VARCHAR(100) NULL",
        'bank_branch'            => "VARCHAR(150) NULL",
        'emergency_name'         => "VARCHAR(200) NULL",
        'emergency_phone'        => "VARCHAR(30) NULL",
        'guarantor1_name'        => "VARCHAR(200) NULL",
        'guarantor1_phone'       => "VARCHAR(30) NULL",
        'guarantor1_address'     => "TEXT NULL",
        'guarantor2_name'        => "VARCHAR(200) NULL",
        'guarantor2_phone'       => "VARCHAR(30) NULL",
        'guarantor2_address'     => "TEXT NULL",
    ];

    foreach ($columns as $col => $def) {
        $add_col('staff_profiles', $col, $def);
    }

    // 6. Enforce UNIQUE on staff_code (safe — only adds if not present)
    $idx_check = $conn->query("
        SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = 'staff_profiles' AND INDEX_NAME = 'uq_staff_code'
    ")->fetch_assoc()['cnt'];
    if ($idx_check == 0) {
        $conn->query("ALTER TABLE staff_profiles ADD UNIQUE INDEX uq_staff_code (staff_code)");
    }
}
?>
