CREATE TABLE IF NOT EXISTS staff_profiles (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO staff_profiles (user_id, full_name, staff_type, staff_role, employment_status)
SELECT u.id,
       u.username,
  CASE WHEN COALESCE(u.role, 'staff') = 'staff' THEN 'teaching' ELSE 'non-teaching' END,
  CASE WHEN COALESCE(u.role, 'staff') = 'staff' THEN 'facilitator' ELSE 'supervisor' END,
       'active'
FROM users u
LEFT JOIN staff_profiles sp ON sp.user_id = u.id
WHERE sp.id IS NULL
  AND COALESCE(u.role, 'staff') IN ('staff', 'supervisor');
