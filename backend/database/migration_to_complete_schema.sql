-- ============================================
-- Migration Script: Update Existing Database
-- to Complete Schema
-- ============================================
-- This script safely updates existing tables and adds missing ones
-- Run this if you already have a database with some tables

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================
-- 1. CREATE DEPARTMENTS TABLE IF NOT EXISTS
-- ============================================
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. UPDATE USERS TABLE
-- ============================================
-- Add missing columns if they don't exist
ALTER TABLE `users`
    MODIFY COLUMN `full_name` VARCHAR(150) NOT NULL,
    MODIFY COLUMN `email` VARCHAR(150) NOT NULL,
    MODIFY COLUMN `role` ENUM('admin', 'staff', 'citizen') NOT NULL DEFAULT 'citizen',
    MODIFY COLUMN `status` ENUM('active', 'inactive', 'disabled') NOT NULL DEFAULT 'active';

-- Add new columns
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `department_id` INT NULL,
    ADD COLUMN IF NOT EXISTS `phone_number` VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS `address` VARCHAR(500) NULL,
    ADD COLUMN IF NOT EXISTS `active_cases` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `completed_cases` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `last_assigned_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add indexes
ALTER TABLE `users`
    ADD INDEX IF NOT EXISTS `idx_role` (`role`),
    ADD INDEX IF NOT EXISTS `idx_status` (`status`),
    ADD INDEX IF NOT EXISTS `idx_department` (`department_id`),
    ADD INDEX IF NOT EXISTS `idx_phone` (`phone_number`);

-- Add foreign key for department
ALTER TABLE `users`
    DROP FOREIGN KEY IF EXISTS `fk_users_department`,
    ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 3. UPDATE COMPLAINTS TABLE
-- ============================================
-- Ensure proper structure
ALTER TABLE `complaints`
    MODIFY COLUMN `status` ENUM('Pending', 'Assigned', 'In Progress', 'Completed', 'Closed', 'Rejected') NOT NULL DEFAULT 'Pending';

-- Add missing columns
ALTER TABLE `complaints`
    ADD COLUMN IF NOT EXISTS `title` VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS `priority_level` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
    ADD COLUMN IF NOT EXISTS `department_id` INT NULL,
    ADD COLUMN IF NOT EXISTS `staff_id` INT NULL,
    ADD COLUMN IF NOT EXISTS `sla_due_at` DATETIME NULL,
    ADD COLUMN IF NOT EXISTS `sla_status` ENUM('On Time', 'Warning', 'Breached') NOT NULL DEFAULT 'On Time',
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Migrate 'subject' to 'title' if needed
UPDATE `complaints` SET `title` = `subject` WHERE `title` IS NULL AND `subject` IS NOT NULL;

-- Add indexes
ALTER TABLE `complaints`
    ADD INDEX IF NOT EXISTS `idx_priority` (`priority_level`),
    ADD INDEX IF NOT EXISTS `idx_department` (`department_id`),
    ADD INDEX IF NOT EXISTS `idx_staff` (`staff_id`),
    ADD INDEX IF NOT EXISTS `idx_sla_due` (`sla_due_at`),
    ADD INDEX IF NOT EXISTS `idx_sla_status` (`sla_status`);

-- Add foreign keys
ALTER TABLE `complaints`
    DROP FOREIGN KEY IF EXISTS `fk_complaints_department`,
    DROP FOREIGN KEY IF EXISTS `fk_complaints_staff`,
    ADD CONSTRAINT `fk_complaints_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_complaints_staff` FOREIGN KEY (`staff_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 4. UPDATE COMPLAINT_FILES TABLE
-- ============================================
ALTER TABLE `complaint_files`
    ADD COLUMN IF NOT EXISTS `file_name` VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS `file_size` INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `uploaded_by_user_id` INT NULL;

-- Migrate file_name from file_path if needed
UPDATE `complaint_files` 
SET `file_name` = SUBSTRING_INDEX(`file_path`, '/', -1) 
WHERE `file_name` IS NULL AND `file_path` IS NOT NULL;

-- Add index and foreign key
ALTER TABLE `complaint_files`
    ADD INDEX IF NOT EXISTS `idx_uploaded_by` (`uploaded_by_user_id`),
    ADD CONSTRAINT `fk_complaint_files_user` FOREIGN KEY (`uploaded_by_user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 5. CREATE MISSING TABLES
-- ============================================

-- Audit Logs (if not exists)
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `role` ENUM('admin', 'staff', 'citizen') NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `related_complaint_id` INT NULL,
    `related_request_id` INT NULL,
    `related_user_id` INT NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_action_type` (`action_type`),
    INDEX `idx_related_complaint_id` (`related_complaint_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_audit_logs_complaint` FOREIGN KEY (`related_complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_audit_logs_related_user` FOREIGN KEY (`related_user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications (if not exists)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `type` ENUM('email', 'sms', 'in_app', 'realtime') NOT NULL DEFAULT 'in_app',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('sent', 'unread', 'read', 'failed') NOT NULL DEFAULT 'sent',
    `related_complaint_id` INT NULL,
    `related_type` VARCHAR(50) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_related_complaint` (`related_complaint_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_complaint` FOREIGN KEY (`related_complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SMS Logs (if not exists)
CREATE TABLE IF NOT EXISTS `sms_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NULL,
    `user_id` INT NULL,
    `complaint_id` INT NULL,
    `phone_number` VARCHAR(20) NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('sent', 'failed', 'delivered') NOT NULL DEFAULT 'sent',
    `provider` VARCHAR(50) NULL,
    `provider_message_id` VARCHAR(255) NULL,
    `error_message` TEXT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_staff_id` (`staff_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_complaint_id` (`complaint_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_sent_at` (`sent_at`),
    CONSTRAINT `fk_sms_logs_staff` FOREIGN KEY (`staff_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sms_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sms_logs_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email Logs (if not exists)
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `complaint_id` INT NULL,
    `recipient_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `email_type` VARCHAR(50) NOT NULL,
    `status` ENUM('sent', 'failed', 'bounced') NOT NULL DEFAULT 'sent',
    `error_message` TEXT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_complaint_id` (`complaint_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email_type` (`email_type`),
    INDEX `idx_sent_at` (`sent_at`),
    CONSTRAINT `fk_email_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_email_logs_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignment Logs (if not exists)
CREATE TABLE IF NOT EXISTS `assignment_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `previous_staff_id` INT NULL,
    `new_staff_id` INT NOT NULL,
    `assigned_by_admin_id` INT NOT NULL,
    `assignment_type` ENUM('auto', 'manual', 'reassignment') NOT NULL DEFAULT 'manual',
    `reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_new_staff` (`new_staff_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_assignment_logs_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_assignment_logs_prev_staff` FOREIGN KEY (`previous_staff_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_assignment_logs_new_staff` FOREIGN KEY (`new_staff_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_assignment_logs_admin` FOREIGN KEY (`assigned_by_admin_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Priority Change Logs (if not exists)
CREATE TABLE IF NOT EXISTS `priority_change_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `old_priority` ENUM('Low', 'Medium', 'High', 'Emergency') NULL,
    `new_priority` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL,
    `changed_by_user_id` INT NOT NULL,
    `reason` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_priority_logs_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_priority_logs_user` FOREIGN KEY (`changed_by_user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SLA Logs (if not exists)
CREATE TABLE IF NOT EXISTS `sla_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `old_status` ENUM('On Time', 'Warning', 'Breached') NULL,
    `new_status` ENUM('On Time', 'Warning', 'Breached') NOT NULL,
    `timestamp` DATETIME NOT NULL,
    `notes` TEXT NULL,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_timestamp` (`timestamp`),
    CONSTRAINT `fk_sla_logs_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto Assign Settings (if not exists)
CREATE TABLE IF NOT EXISTS `auto_assign_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_by_admin_id` INT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`),
    CONSTRAINT `fk_auto_assign_admin` FOREIGN KEY (`updated_by_admin_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limits (if not exists)
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rate_key` VARCHAR(64) NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `user_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rate_key` (`rate_key`),
    INDEX `idx_endpoint` (`endpoint`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_rate_limits_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. INSERT DEFAULT DATA
-- ============================================

-- Insert default departments
INSERT IGNORE INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Public Works', 'Infrastructure and maintenance'),
(2, 'Health & Safety', 'Health and safety concerns'),
(3, 'Environment', 'Environmental issues'),
(4, 'Transportation', 'Roads and transportation'),
(5, 'General', 'General complaints and requests');

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `status`) VALUES
(1, 'Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');

-- Insert default auto-assign settings
INSERT IGNORE INTO `auto_assign_settings` (`setting_key`, `setting_value`) VALUES
('auto_assign_enabled', '1'),
('assignment_method', 'workload'),
('emergency_priority_alert', '1');

-- ============================================
-- 7. UPDATE EXISTING DATA
-- ============================================

-- Set default priority for existing complaints
UPDATE `complaints` SET `priority_level` = 'Medium' WHERE `priority_level` IS NULL OR `priority_level` = '';

-- Set default SLA status
UPDATE `complaints` SET `sla_status` = 'On Time' WHERE `sla_status` IS NULL OR `sla_status` = '';

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Migration completed successfully!' AS status;

