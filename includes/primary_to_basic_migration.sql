-- Migration script to update class names from Primary to Basic
-- This script safely updates existing data to use the correct Basic 1-7 naming convention

-- Update students table
UPDATE students SET class = 'Basic 1' WHERE class = 'Primary 1';
UPDATE students SET class = 'Basic 2' WHERE class = 'Primary 2';
UPDATE students SET class = 'Basic 3' WHERE class = 'Primary 3';
UPDATE students SET class = 'Basic 4' WHERE class = 'Primary 4';
UPDATE students SET class = 'Basic 5' WHERE class = 'Primary 5';
UPDATE students SET class = 'Basic 6' WHERE class = 'Primary 6';

-- Update fee_amounts table
UPDATE fee_amounts SET class_name = 'Basic 1' WHERE class_name = 'Primary 1';
UPDATE fee_amounts SET class_name = 'Basic 2' WHERE class_name = 'Primary 2';
UPDATE fee_amounts SET class_name = 'Basic 3' WHERE class_name = 'Primary 3';
UPDATE fee_amounts SET class_name = 'Basic 4' WHERE class_name = 'Primary 4';
UPDATE fee_amounts SET class_name = 'Basic 5' WHERE class_name = 'Primary 5';
UPDATE fee_amounts SET class_name = 'Basic 6' WHERE class_name = 'Primary 6';

-- Show updated class structure
SELECT DISTINCT class FROM students ORDER BY 
    CASE class
        WHEN 'Creche' THEN 1
        WHEN 'Nursery 1' THEN 2
        WHEN 'Nursery 2' THEN 3
        WHEN 'KG 1' THEN 4
        WHEN 'KG 2' THEN 5
        WHEN 'Basic 1' THEN 6
        WHEN 'Basic 2' THEN 7
        WHEN 'Basic 3' THEN 8
        WHEN 'Basic 4' THEN 9
        WHEN 'Basic 5' THEN 10
        WHEN 'Basic 6' THEN 11
        WHEN 'Basic 7' THEN 12
        ELSE 99
    END;

-- Show updated fee amounts structure  
SELECT 
    f.name as fee_name,
    fa.class_name,
    fa.amount
FROM fees f
JOIN fee_amounts fa ON f.id = fa.fee_id
WHERE f.fee_type = 'class_based'
ORDER BY f.name, 
    CASE fa.class_name
        WHEN 'Creche' THEN 1
        WHEN 'Nursery 1' THEN 2
        WHEN 'Nursery 2' THEN 3
        WHEN 'KG 1' THEN 4
        WHEN 'KG 2' THEN 5
        WHEN 'Basic 1' THEN 6
        WHEN 'Basic 2' THEN 7
        WHEN 'Basic 3' THEN 8
        WHEN 'Basic 4' THEN 9
        WHEN 'Basic 5' THEN 10
        WHEN 'Basic 6' THEN 11
        WHEN 'Basic 7' THEN 12
        ELSE 99
    END;