-- Update student_fees table to support enhanced fee assignment functionality

-- Add new columns to student_fees table
ALTER TABLE student_fees 
ADD COLUMN amount DECIMAL(10,2) DEFAULT 0.00 AFTER fee_id,
ADD COLUMN term VARCHAR(50) DEFAULT NULL AFTER amount,
ADD COLUMN notes TEXT DEFAULT NULL AFTER term,
ADD COLUMN assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP AFTER notes,
ADD COLUMN status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending' AFTER assigned_date;

-- Update existing records to have proper amount values
-- This will set the amount based on the fee's fixed amount (for existing fixed fees)
UPDATE student_fees sf
JOIN fees f ON sf.fee_id = f.id
SET sf.amount = f.amount
WHERE sf.amount = 0.00 AND f.fee_type = 'fixed';

-- Add index for better performance
CREATE INDEX idx_student_fees_status ON student_fees(status);
CREATE INDEX idx_student_fees_due_date ON student_fees(due_date);
CREATE INDEX idx_student_fees_assigned_date ON student_fees(assigned_date);

-- Create view for easy fee assignment reporting
CREATE OR replace VIEW v_fee_assignments AS
SELECT 
    sf.id as assignment_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    s.class as student_class,
    f.name as fee_name,
    f.fee_type,
    sf.amount,
    sf.due_date,
    sf.term,
    sf.assigned_date,
    sf.status,
    sf.notes,
    DATEDIFF(sf.due_date, CURDATE()) as days_to_due,
    CASE 
        WHEN sf.status = 'paid' THEN 'Paid'
        WHEN sf.due_date < CURDATE() AND sf.status = 'pending' THEN 'Overdue'
        WHEN DATEDIFF(sf.due_date, CURDATE()) <= 7 AND sf.status = 'pending' THEN 'Due Soon'
        ELSE 'Pending'
    END as payment_status
FROM student_fees sf
JOIN students s ON sf.student_id = s.id
JOIN fees f ON sf.fee_id = f.id
WHERE sf.status != 'cancelled'
ORDER BY sf.due_date DESC, s.class, s.first_name;