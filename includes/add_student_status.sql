-- Add status column to students table
ALTER TABLE students ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';

-- Update existing students to be active by default
UPDATE students SET status = 'active' WHERE status IS NULL;