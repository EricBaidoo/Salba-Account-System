-- Add expense_categories table
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Add category_id to expenses table
ALTER TABLE expenses ADD COLUMN category_id INT NULL AFTER id;

-- Migrate existing categories to expense_categories and update expenses
INSERT INTO expense_categories (name)
SELECT DISTINCT category FROM expenses WHERE category IS NOT NULL AND category != '';

UPDATE expenses e
JOIN expense_categories c ON e.category = c.name
SET e.category_id = c.id;

-- Make category_id NOT NULL and remove old category column if migration is successful
-- ALTER TABLE expenses MODIFY category_id INT NOT NULL;
-- ALTER TABLE expenses DROP COLUMN category;
