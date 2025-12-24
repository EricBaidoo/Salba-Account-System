-- === v_fee_assignments View Recreation ===
DROP VIEW IF EXISTS v_fee_assignments;
CREATE VIEW v_fee_assignments AS 
  SELECT 
    sf.id AS assignment_id,
    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
    s.class AS student_class,
    f.name AS fee_name,
    f.fee_type AS fee_type,
    sf.amount AS amount,
    sf.due_date AS due_date,
    sf.term AS term,
    sf.assigned_date AS assigned_date,
    sf.status AS status,
    sf.notes AS notes,
    (TO_DAYS(sf.due_date) - TO_DAYS(CURDATE())) AS days_to_due,
    (CASE 
      WHEN (sf.status = 'paid') THEN 'Paid'
      WHEN ((sf.due_date < CURDATE()) AND (sf.status = 'pending')) THEN 'Overdue'
      WHEN (((TO_DAYS(sf.due_date) - TO_DAYS(CURDATE())) <= 7) AND (sf.status = 'pending')) THEN 'Due Soon'
      ELSE 'Pending'
    END) AS payment_status
  FROM student_fees sf
    JOIN students s ON sf.student_id = s.id
    JOIN fees f ON sf.fee_id = f.id
  WHERE sf.status <> 'cancelled'
  ORDER BY sf.due_date DESC, s.class, s.first_name;
-- === Students Table Alignment ===
-- Unify column types and nullability with local standard
ALTER TABLE students MODIFY class varchar(50) NULL;
ALTER TABLE students MODIFY parent_contact varchar(100) NULL;
ALTER TABLE students MODIFY first_name varchar(100) NOT NULL;
ALTER TABLE students MODIFY last_name varchar(100) NOT NULL;

-- === Create system_settings table if missing ===
CREATE TABLE system_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  description VARCHAR(255),
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(100)
);
-- );

-- === v_fee_assignments View Recreation ===
-- Replace the following with your actual CREATE VIEW statement from local
-- DROP VIEW IF EXISTS v_fee_assignments;
-- CREATE VIEW v_fee_assignments AS
--   SELECT ... (copy the full CREATE VIEW from your local DB) ... ;
-- Full alignment and backfill script
-- Assumes academic year runs Sep (9) to Aug (8)
-- Safe to run multiple times; uses conditional exec for columns/indexes

SET @orig_fk = @@FOREIGN_KEY_CHECKS; SET FOREIGN_KEY_CHECKS = 0;

-- Ensure columns exist via information_schema + dynamic SQL
-- student_fees.academic_year
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'student_fees'
    AND COLUMN_NAME = 'academic_year'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE student_fees ADD COLUMN academic_year VARCHAR(9) NULL AFTER term',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments.term
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'term'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE payments ADD COLUMN term VARCHAR(50) NULL AFTER description',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments.academic_year
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND COLUMN_NAME = 'academic_year'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE payments ADD COLUMN academic_year VARCHAR(9) NULL AFTER term',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure scope indexes exist
-- student_fees scope index
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'student_fees'
    AND INDEX_NAME = 'idx_student_fees_scope'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX idx_student_fees_scope ON student_fees (student_id, fee_id, term, academic_year)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- payments scope index
SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payments'
    AND INDEX_NAME = 'idx_payments_scope'
);
SET @sql = IF(@idx_exists = 0,
  'CREATE INDEX idx_payments_scope ON payments (student_id, term, academic_year)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Ensure Outstanding Balance fee exists; migrate legacy name if present
UPDATE fees
SET name = 'Outstanding Balance', description = 'Auto-created for outstanding balance carry forward'
WHERE name = 'Arrears Carry Forward';

INSERT INTO fees (name, amount, fee_type, description)
SELECT 'Outstanding Balance', 0.00, 'fixed', 'Auto-created for outstanding balance carry forward'
WHERE NOT EXISTS (SELECT 1 FROM fees WHERE name = 'Outstanding Balance');

-- Normalize term labels
UPDATE student_fees SET term = NULL WHERE term = '';
UPDATE student_fees SET term = 'First Term'  WHERE term IN ('1st Term','First','First term');
UPDATE student_fees SET term = 'Second Term' WHERE term IN ('2nd Term','Second','Second term');
UPDATE student_fees SET term = 'Third Term'  WHERE term IN ('3rd Term','Third','Third term');

UPDATE payments SET term = 'First Term'  WHERE term IN ('1st Term','First','First term');
UPDATE payments SET term = 'Second Term' WHERE term IN ('2nd Term','Second','Second term');
UPDATE payments SET term = 'Third Term'  WHERE term IN ('3rd Term','Third','Third term');

-- Backfill academic_years using date fields (Sep-Aug academic year)
-- student_fees: prefer assigned_date, fallback to due_date
UPDATE student_fees
SET academic_year = CASE
  WHEN MONTH(COALESCE(assigned_date, due_date)) >= 9 THEN CONCAT(YEAR(COALESCE(assigned_date, due_date)), '/', YEAR(COALESCE(assigned_date, due_date)) + 1)
  ELSE CONCAT(YEAR(COALESCE(assigned_date, due_date)) - 1, '/', YEAR(COALESCE(assigned_date, due_date)))
END
WHERE academic_year IS NULL AND (assigned_date IS NOT NULL OR due_date IS NOT NULL);

-- payments: based on payment_date
UPDATE payments
SET academic_year = CASE
  WHEN MONTH(payment_date) >= 9 THEN CONCAT(YEAR(payment_date), '/', YEAR(payment_date) + 1)
  ELSE CONCAT(YEAR(payment_date) - 1, '/', YEAR(payment_date))
END
WHERE academic_year IS NULL AND payment_date IS NOT NULL;

-- Backfill term for payments based on payment_date (Sep-Dec: First, Jan-Mar: Second, Apr-Jun: Third)
UPDATE payments
SET term = CASE
  WHEN MONTH(payment_date) BETWEEN 9 AND 12 THEN 'First Term'
  WHEN MONTH(payment_date) BETWEEN 1 AND 3 THEN 'Second Term'
  WHEN MONTH(payment_date) BETWEEN 4 AND 6 THEN 'Third Term'
  ELSE term
END
WHERE term IS NULL AND payment_date IS NOT NULL;

-- Data hygiene
UPDATE student_fees SET amount_paid = 0.00 WHERE amount_paid IS NULL;

SET FOREIGN_KEY_CHECKS = @orig_fk;
