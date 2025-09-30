-- Enhanced fee management with class-dependent amounts
-- Run this after the main schema to add class-dependent fee support

-- Create fee_amounts table for class-specific fees
CREATE TABLE fee_amounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fee_id INT NOT NULL,
    class_name VARCHAR(50),
    category VARCHAR(50),
    amount DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (fee_id) REFERENCES fees(id) ON DELETE CASCADE,
    INDEX idx_fee_class (fee_id, class_name),
    INDEX idx_fee_category (fee_id, category)
);

-- Add fee_type column to fees table
ALTER TABLE fees ADD COLUMN fee_type ENUM('fixed', 'class_based', 'category') DEFAULT 'fixed' AFTER amount;

-- Update fees table to allow NULL amount for variable fees
ALTER TABLE fees MODIFY COLUMN amount DECIMAL(10,2) NULL;