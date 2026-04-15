-- Migration to update users table for dedicated User Management
-- Standardizing roles and adding account status

ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('admin', 'supervisor', 'facilitator', 'staff') DEFAULT 'staff';

-- Add is_active if it doesn't exist
ALTER TABLE `users` 
ADD COLUMN `is_active` TINYINT(1) DEFAULT 1 AFTER `role`;

-- If there are any users with 'admin' role, make sure they stay 'admin'
UPDATE `users` SET `role` = 'admin' WHERE `username` = 'Admin_Eric';
