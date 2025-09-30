-- Update to support Basic 7 (JHS 1) class level
-- This ensures the fee_amounts table has proper data for the new class

-- Insert sample fee amounts for Basic 7 if tuition fees already exist
-- This is a safe update that only adds Basic 7 amounts where tuition fees exist

-- First, let's add Basic 7 amounts for any existing class-based tuition fees
INSERT IGNORE INTO fee_amounts (fee_id, class_name, amount)
SELECT f.id, 'Basic 7', 800.00
FROM fees f 
WHERE f.name LIKE '%tuition%' 
  AND f.fee_type = 'class_based'
  AND NOT EXISTS (
    SELECT 1 FROM fee_amounts fa 
    WHERE fa.fee_id = f.id AND fa.class_name = 'Basic 7'
  );

-- Verify the class structure is properly supported
-- This query shows all classes with their associated fee amounts
SELECT 
    f.name as fee_name,
    fa.class_name,
    fa.amount
FROM fees f
LEFT JOIN fee_amounts fa ON f.id = fa.fee_id
WHERE f.fee_type = 'class_based'
ORDER BY f.name, 
    CASE fa.class_name
        WHEN 'Creche' THEN 1
        WHEN 'Nursery 1' THEN 2
        WHEN 'Nursery 2' THEN 3
        WHEN 'KG 1' THEN 4
        WHEN 'KG 2' THEN 5
        WHEN 'Primary 1' THEN 6
        WHEN 'Primary 2' THEN 7
        WHEN 'Primary 3' THEN 8
        WHEN 'Primary 4' THEN 9
        WHEN 'Primary 5' THEN 10
        WHEN 'Primary 6' THEN 11
        WHEN 'Basic 7' THEN 12
        ELSE 99
    END;