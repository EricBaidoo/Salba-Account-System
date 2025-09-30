-- Add fee_id to payments for category/general payments
ALTER TABLE payments ADD COLUMN fee_id INT NULL AFTER student_id;
-- (Optional) Add a type column to distinguish between student and general payments
ALTER TABLE payments ADD COLUMN payment_type ENUM('student','general') NOT NULL DEFAULT 'student' AFTER fee_id;
-- Add foreign key constraint for fee_id
ALTER TABLE payments ADD CONSTRAINT fk_payments_fee_id FOREIGN KEY (fee_id) REFERENCES fees(id);
