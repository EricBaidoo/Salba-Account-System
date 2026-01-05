-- Simplified Term Budget Tables

-- Main budget table for each term
CREATE TABLE IF NOT EXISTS `term_budgets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `term` VARCHAR(50) NOT NULL,
  `academic_year` VARCHAR(50) NOT NULL,
  `expected_income` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `status` ENUM('draft', 'approved', 'locked') NOT NULL DEFAULT 'draft',
  `locked_at` DATETIME NULL,
  `locked_by` VARCHAR(100) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_term_year` (`term`, `academic_year`),
  INDEX `idx_term_year` (`term`, `academic_year`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget items for income and expense categories
CREATE TABLE IF NOT EXISTS `term_budget_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `term_budget_id` INT(11) NOT NULL,
  `type` ENUM('income', 'expense') NOT NULL DEFAULT 'expense',
  `category` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`term_budget_id`) REFERENCES `term_budgets`(`id`) ON DELETE CASCADE,
  INDEX `idx_budget_id` (`term_budget_id`),
  INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
