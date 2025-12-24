-- Schema migration for term+academic_year scoping and arrears carry-forward

-- students (reference)
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  class VARCHAR(100) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

-- fees (includes template for Outstanding Balance)
CREATE TABLE IF NOT EXISTS fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL UNIQUE,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fee_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
  description TEXT NULL
) ENGINE=InnoDB;

-- student_fees (term/year-aware assignments)
CREATE TABLE IF NOT EXISTS student_fees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  fee_id INT NOT NULL,
  due_date DATE NULL,
  amount DECIMAL(10,2) NOT NULL,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  term VARCHAR(32) NULL,
  academic_year VARCHAR(9) NULL,
  assigned_date DATETIME NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  INDEX idx_sf_scope (student_id, term, academic_year, status),
  CONSTRAINT fk_sf_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_sf_fee FOREIGN KEY (fee_id) REFERENCES fees(id)
) ENGINE=InnoDB;

-- payments (term/year-aware payments)
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_date DATE NOT NULL,
  receipt_no VARCHAR(50) NULL,
  description TEXT NULL,
  term VARCHAR(32) NULL,
  academic_year VARCHAR(9) NULL,
  INDEX idx_pay_scope (student_id, term, academic_year),
  CONSTRAINT fk_pay_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB;

-- Ensure Outstanding Balance fee exists and migrate legacy name
UPDATE fees SET name = 'Outstanding Balance', description = 'Auto-created for outstanding balance carry forward'
WHERE name = 'Arrears Carry Forward';

INSERT INTO fees (name, amount, fee_type, description)
SELECT 'Outstanding Balance', 0.00, 'fixed', 'Auto-created for outstanding balance carry forward'
WHERE NOT EXISTS (SELECT 1 FROM fees WHERE name = 'Outstanding Balance');

-- Optional: normalize term names for consistency
UPDATE student_fees SET term = 'First Term' WHERE term IN ('1st Term','First');
UPDATE student_fees SET term = 'Second Term' WHERE term IN ('2nd Term','Second');
UPDATE student_fees SET term = 'Third Term' WHERE term IN ('3rd Term','Third');

-- Optional: set amount_paid default for NULLs
UPDATE student_fees SET amount_paid = 0.00 WHERE amount_paid IS NULL;
