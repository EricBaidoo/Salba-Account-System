-- Add amount_paid to student_fees for partial payment tracking
ALTER TABLE student_fees ADD COLUMN amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER amount;

-- Create payment_allocations table for mapping payments to student_fees
CREATE TABLE IF NOT EXISTS payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    student_fee_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id)
);

-- Initialize amount_paid for already paid fees
UPDATE student_fees SET amount_paid = amount WHERE status = 'paid';
