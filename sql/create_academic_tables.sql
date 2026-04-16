-- Academic Settings Tables Migration
-- Creates missing tables for assessment configurations, grading scales, and class curriculum mapping

-- 1. ASSESSMENT CONFIGURATIONS TABLE
CREATE TABLE IF NOT EXISTS `assessment_configurations` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `academic_year` VARCHAR(9) NOT NULL COMMENT 'Format: YYYY/YYYY',
  `semester` VARCHAR(50) NOT NULL COMMENT 'Semester 1, Semester 2, Semester 3, etc.',
  `assessment_name` VARCHAR(100) NOT NULL COMMENT 'e.g., Exam, Class Test, Assignment',
  `max_marks_allocation` DECIMAL(5,2) NOT NULL COMMENT 'Percentage (0-100)',
  `is_exam` TINYINT(1) DEFAULT 0 COMMENT '1 for exam, 0 for OA/continuous assessment',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_assessment` (`academic_year`, `semester`, `assessment_name`),
  INDEX `idx_year_term` (`academic_year`, `semester`),
  INDEX `idx_exam_flag` (`is_exam`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CLASS SUBJECTS MAPPING TABLE
CREATE TABLE IF NOT EXISTS `class_subjects` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `class_name` VARCHAR(100) NOT NULL COMMENT 'e.g., Primary 1, Secondary 2',
  `subject_id` INT(11) NOT NULL,
  `description` TEXT COMMENT 'Subject description for this class',
  `is_compulsory` TINYINT(1) DEFAULT 1 COMMENT '1 for mandatory, 0 for elective',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`subject_id`) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY `unique_class_subject` (`class_name`, `subject_id`),
  INDEX `idx_class_name` (`class_name`),
  INDEX `idx_subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. GRADING SCALES TABLE
CREATE TABLE IF NOT EXISTS `grading_scales` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `scale_name` VARCHAR(100) NOT NULL COMMENT 'e.g., WAEC, CONTINUOUS, CUSTOM',
  `description` TEXT,
  `min_mark` DECIMAL(5,2) NOT NULL COMMENT 'Minimum mark for this grade',
  `max_mark` DECIMAL(5,2) NOT NULL COMMENT 'Maximum mark for this grade',
  `grade_letter` VARCHAR(5) NOT NULL COMMENT 'A+, A, B, C, D, E, F',
  `grade_point` DECIMAL(3,1) COMMENT '4.0 scale grade point',
  `is_pass` TINYINT(1) DEFAULT 1 COMMENT '1 if passing grade, 0 if failing',
  `sort_order` INT(3) COMMENT 'Display order (A+ = 1, A = 2, etc)',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100),
  UNIQUE KEY `unique_scale_grade` (`scale_name`, `grade_letter`),
  INDEX `idx_scale_name` (`scale_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. PASS MARKS TABLE
CREATE TABLE IF NOT EXISTS `pass_marks` (
  `id` INT(11) PRIMARY KEY AUTO_INCREMENT,
  `subject_id` INT(11) NOT NULL,
  `class_name` VARCHAR(100),
  `pass_mark` DECIMAL(5,2) NOT NULL COMMENT 'Minimum mark to pass',
  `credit_mark` DECIMAL(5,2) COMMENT 'Mark required for credit',
  `distinction_mark` DECIMAL(5,2) COMMENT 'Mark required for distinction',
  `academic_year` VARCHAR(9) COMMENT 'If year-specific, else NULL for global',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(100),
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`subject_id`) REFERENCES subjects(id) ON DELETE CASCADE,
  UNIQUE KEY `unique_subject_class_year` (`subject_id`, `class_name`, `academic_year`),
  INDEX `idx_subject_id` (`subject_id`),
  INDEX `idx_class_name` (`class_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample WAEC grading scale
INSERT IGNORE INTO `grading_scales` 
(`scale_name`, `description`, `min_mark`, `max_mark`, `grade_letter`, `grade_point`, `is_pass`, `sort_order`) 
VALUES 
('WAEC', 'West African Examinations Council Standard', 90, 100, 'A+', 4.0, 1, 1),
('WAEC', 'West African Examinations Council Standard', 80, 89, 'A', 3.7, 1, 2),
('WAEC', 'West African Examinations Council Standard', 70, 79, 'B', 3.3, 1, 3),
('WAEC', 'West African Examinations Council Standard', 60, 69, 'C', 3.0, 1, 4),
('WAEC', 'West African Examinations Council Standard', 50, 59, 'D', 2.0, 1, 5),
('WAEC', 'West African Examinations Council Standard', 40, 49, 'E', 1.0, 1, 6),
('WAEC', 'West African Examinations Council Standard', 0, 39, 'F', 0.0, 0, 7);

-- Insert sample Continuous Assessment scale
INSERT IGNORE INTO `grading_scales` 
(`scale_name`, `description`, `min_mark`, `max_mark`, `grade_letter`, `grade_point`, `is_pass`, `sort_order`) 
VALUES 
('CONTINUOUS', 'Continuous Assessment Scale', 85, 100, 'A', 4.0, 1, 1),
('CONTINUOUS', 'Continuous Assessment Scale', 75, 84, 'B', 3.0, 1, 2),
('CONTINUOUS', 'Continuous Assessment Scale', 65, 74, 'C', 2.0, 1, 3),
('CONTINUOUS', 'Continuous Assessment Scale', 50, 64, 'D', 1.0, 1, 4),
('CONTINUOUS', 'Continuous Assessment Scale', 0, 49, 'F', 0.0, 0, 5);
