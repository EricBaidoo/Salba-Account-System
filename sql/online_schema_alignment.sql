-- Align the online database schema to match app expectations
-- Target DB: run with the appropriate database selected (e.g., Salba_acc_online)

-- Add academic_year to student_fees if missing
ALTER TABLE student_fees
  ADD COLUMN IF NOT EXISTS academic_year VARCHAR(9) NULL AFTER term;

-- Add term and academic_year to payments if missing
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS term VARCHAR(50) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS academic_year VARCHAR(9) NULL AFTER term;

-- Add scope indexes for performance
CREATE INDEX IF NOT EXISTS idx_student_fees_scope
  ON student_fees (student_id, fee_id, term, academic_year);

CREATE INDEX IF NOT EXISTS idx_payments_scope
  ON payments (student_id, term, academic_year);

-- Ensure Outstanding Balance fee exists
INSERT INTO fees (name, amount, fee_type, description)
SELECT 'Outstanding Balance', 0.00, 'fixed', 'Auto-created for outstanding balance carry forward'
WHERE NOT EXISTS (SELECT 1 FROM fees WHERE name = 'Outstanding Balance');

-- Normalize blank terms to NULL for global assignments
UPDATE student_fees SET term = NULL WHERE term = '';

-- Optional: backfill academic_years based on date ranges (adjust to your calendar)
-- UPDATE payments SET academic_year = '2024/2025' WHERE academic_year IS NULL AND payment_date BETWEEN '2024-09-01' AND '2025-08-31';
-- UPDATE student_fees SET academic_year = '2024/2025' WHERE academic_year IS NULL AND assigned_date BETWEEN '2024-09-01' AND '2025-08-31';
