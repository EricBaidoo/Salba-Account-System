-- Extend messages table with delivery tracking
ALTER TABLE `messages` ADD COLUMN `channel` ENUM('email', 'sms', 'whatsapp', 'in_app') DEFAULT 'in_app' AFTER `is_read`;
ALTER TABLE `messages` ADD COLUMN `status` ENUM('draft', 'queued', 'sent', 'delivered', 'failed') DEFAULT 'draft' AFTER `channel`;
ALTER TABLE `messages` ADD COLUMN `metadata` JSON AFTER `status`;
ALTER TABLE `messages` ADD COLUMN `scheduled_at` DATETIME NULL AFTER `metadata`;

-- Extend announcements table with targeting
ALTER TABLE `announcements` ADD COLUMN `channel` ENUM('email', 'sms', 'whatsapp', 'in_app') DEFAULT 'in_app' AFTER `audience`;
ALTER TABLE `announcements` ADD COLUMN `audience_type` ENUM('all', 'staff', 'students', 'parents', 'class', 'custom') DEFAULT 'all' AFTER `channel`;
ALTER TABLE `announcements` ADD COLUMN `audience_query` JSON AFTER `audience_type`;
ALTER TABLE `announcements` ADD COLUMN `status` ENUM('draft', 'scheduled', 'sent') DEFAULT 'draft' AFTER `audience_query`;
ALTER TABLE `announcements` ADD COLUMN `scheduled_at` DATETIME NULL AFTER `status`;

-- Create message delivery audit log
CREATE TABLE IF NOT EXISTS `message_delivery_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `message_id` INT,
    `recipient_phone` VARCHAR(20),
    `recipient_email` VARCHAR(120),
    `recipient_user_id` INT,
    `channel` ENUM('email', 'sms', 'whatsapp', 'in_app') NOT NULL,
    `status` ENUM('queued', 'sent', 'delivered', 'failed') DEFAULT 'queued',
    `error_message` TEXT,
    `attempt_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `sent_at` TIMESTAMP NULL,
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`id`) ON DELETE SET NULL,
    INDEX `idx_message_id` (`message_id`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
