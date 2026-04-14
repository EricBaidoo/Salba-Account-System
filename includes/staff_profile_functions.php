<?php

function getStaffCategoryRoleMap(): array
{
    return [
        'teaching' => ['facilitator', 'assistant facilitator'],
        'non-teaching' => ['admin', 'supervisor', 'cook', 'cleaner']
    ];
}

function isValidStaffCategory(string $category): bool
{
    return array_key_exists($category, getStaffCategoryRoleMap());
}

function isValidStaffRoleForCategory(string $category, string $role): bool
{
    $map = getStaffCategoryRoleMap();
    return isset($map[$category]) && in_array($role, $map[$category], true);
}

function ensureStaffProfilesTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS staff_profiles (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NULL,
        photo_path VARCHAR(255) DEFAULT NULL,
        full_name VARCHAR(150) NOT NULL,
        date_of_birth DATE DEFAULT NULL,
        marital_status VARCHAR(30) DEFAULT NULL,
        emergency_contact VARCHAR(255) DEFAULT NULL,
        nationality VARCHAR(80) DEFAULT NULL,
        telephone_number VARCHAR(50) DEFAULT NULL,
        ghana_card_no VARCHAR(80) DEFAULT NULL,
        ssnit_number VARCHAR(80) DEFAULT NULL,
        place_of_stay_address TEXT NULL,
        land_mark VARCHAR(150) DEFAULT NULL,
        religion VARCHAR(80) DEFAULT NULL,
        ghanaian_languages VARCHAR(255) DEFAULT NULL,
        highest_qualification VARCHAR(150) DEFAULT NULL,
        entry_qualification VARCHAR(150) DEFAULT NULL,
        first_appointment_date DATE DEFAULT NULL,
        first_guarantor_name VARCHAR(150) DEFAULT NULL,
        first_guarantor_phone VARCHAR(50) DEFAULT NULL,
        first_guarantor_location TEXT NULL,
        second_guarantor_name VARCHAR(150) DEFAULT NULL,
        second_guarantor_phone VARCHAR(50) DEFAULT NULL,
        second_guarantor_location TEXT NULL,
        home_town VARCHAR(120) DEFAULT NULL,
        bank_account_details TEXT NULL,
        staff_type ENUM('teaching','non-teaching') NOT NULL DEFAULT 'teaching',
        staff_role VARCHAR(60) DEFAULT NULL,
        department VARCHAR(100) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        email VARCHAR(120) DEFAULT NULL,
        employment_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_staff_user (user_id),
        KEY idx_staff_type (staff_type),
        KEY idx_staff_status (employment_status),
        CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);

    $columns = [
        "photo_path" => "ALTER TABLE staff_profiles ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL AFTER user_id",
        "date_of_birth" => "ALTER TABLE staff_profiles ADD COLUMN date_of_birth DATE DEFAULT NULL AFTER full_name",
        "marital_status" => "ALTER TABLE staff_profiles ADD COLUMN marital_status VARCHAR(30) DEFAULT NULL AFTER date_of_birth",
        "emergency_contact" => "ALTER TABLE staff_profiles ADD COLUMN emergency_contact VARCHAR(255) DEFAULT NULL AFTER marital_status",
        "nationality" => "ALTER TABLE staff_profiles ADD COLUMN nationality VARCHAR(80) DEFAULT NULL AFTER emergency_contact",
        "telephone_number" => "ALTER TABLE staff_profiles ADD COLUMN telephone_number VARCHAR(50) DEFAULT NULL AFTER nationality",
        "ghana_card_no" => "ALTER TABLE staff_profiles ADD COLUMN ghana_card_no VARCHAR(80) DEFAULT NULL AFTER telephone_number",
        "ssnit_number" => "ALTER TABLE staff_profiles ADD COLUMN ssnit_number VARCHAR(80) DEFAULT NULL AFTER ghana_card_no",
        "place_of_stay_address" => "ALTER TABLE staff_profiles ADD COLUMN place_of_stay_address TEXT NULL AFTER ssnit_number",
        "land_mark" => "ALTER TABLE staff_profiles ADD COLUMN land_mark VARCHAR(150) DEFAULT NULL AFTER place_of_stay_address",
        "religion" => "ALTER TABLE staff_profiles ADD COLUMN religion VARCHAR(80) DEFAULT NULL AFTER land_mark",
        "ghanaian_languages" => "ALTER TABLE staff_profiles ADD COLUMN ghanaian_languages VARCHAR(255) DEFAULT NULL AFTER religion",
        "highest_qualification" => "ALTER TABLE staff_profiles ADD COLUMN highest_qualification VARCHAR(150) DEFAULT NULL AFTER ghanaian_languages",
        "entry_qualification" => "ALTER TABLE staff_profiles ADD COLUMN entry_qualification VARCHAR(150) DEFAULT NULL AFTER highest_qualification",
        "first_appointment_date" => "ALTER TABLE staff_profiles ADD COLUMN first_appointment_date DATE DEFAULT NULL AFTER entry_qualification",
        "first_guarantor_name" => "ALTER TABLE staff_profiles ADD COLUMN first_guarantor_name VARCHAR(150) DEFAULT NULL AFTER first_appointment_date",
        "first_guarantor_phone" => "ALTER TABLE staff_profiles ADD COLUMN first_guarantor_phone VARCHAR(50) DEFAULT NULL AFTER first_guarantor_name",
        "first_guarantor_location" => "ALTER TABLE staff_profiles ADD COLUMN first_guarantor_location TEXT NULL AFTER first_guarantor_phone",
        "second_guarantor_name" => "ALTER TABLE staff_profiles ADD COLUMN second_guarantor_name VARCHAR(150) DEFAULT NULL AFTER first_guarantor_location",
        "second_guarantor_phone" => "ALTER TABLE staff_profiles ADD COLUMN second_guarantor_phone VARCHAR(50) DEFAULT NULL AFTER second_guarantor_name",
        "second_guarantor_location" => "ALTER TABLE staff_profiles ADD COLUMN second_guarantor_location TEXT NULL AFTER second_guarantor_phone",
        "home_town" => "ALTER TABLE staff_profiles ADD COLUMN home_town VARCHAR(120) DEFAULT NULL AFTER second_guarantor_location",
        "bank_account_details" => "ALTER TABLE staff_profiles ADD COLUMN bank_account_details TEXT NULL AFTER home_town",
        "staff_role" => "ALTER TABLE staff_profiles ADD COLUMN staff_role VARCHAR(60) DEFAULT NULL AFTER staff_type"
    ];

    foreach ($columns as $columnName => $alterSql) {
        $check = $conn->query("SHOW COLUMNS FROM staff_profiles LIKE '" . $conn->real_escape_string($columnName) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query($alterSql);
        }
        if ($check) {
            $check->free();
        }
    }

    $conn->query("UPDATE staff_profiles SET staff_type = 'teaching' WHERE staff_type = 'teacher'");
    $conn->query("UPDATE staff_profiles SET staff_type = 'non-teaching' WHERE staff_type IN ('support', 'management')");
    $conn->query("ALTER TABLE staff_profiles MODIFY COLUMN staff_type ENUM('teaching','non-teaching') NOT NULL DEFAULT 'teaching'");

    $conn->query("UPDATE staff_profiles SET staff_role = 'facilitator' WHERE staff_type = 'teaching' AND (staff_role IS NULL OR staff_role = '')");
    $conn->query("UPDATE staff_profiles SET staff_role = 'admin' WHERE staff_type = 'non-teaching' AND (staff_role IS NULL OR staff_role = '')");
}

function backfillStaffProfilesFromUsers(mysqli $conn): void
{
        $sql = "INSERT INTO staff_profiles (user_id, full_name, staff_type, staff_role, employment_status)
            SELECT u.id,
                   u.username,
                 CASE WHEN COALESCE(u.role, 'staff') = 'staff' THEN 'teaching' ELSE 'non-teaching' END,
                 CASE WHEN COALESCE(u.role, 'staff') = 'staff' THEN 'facilitator' ELSE 'supervisor' END,
                   'active'
            FROM users u
            LEFT JOIN staff_profiles sp ON sp.user_id = u.id
            WHERE sp.id IS NULL AND COALESCE(u.role, 'staff') IN ('staff', 'supervisor')";

    $conn->query($sql);
}

function ensureClassesTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        Level VARCHAR(50),
        term VARCHAR(50),
        year VARCHAR(10),
        capacity INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_class (name, Level),
        INDEX idx_level (Level),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);
}

function ensureSubjectsTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        academic_level VARCHAR(50),
        description TEXT,
        code VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_subject (name, academic_level),
        INDEX idx_level (academic_level)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $conn->query($sql);
}
