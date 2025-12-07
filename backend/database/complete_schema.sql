-- ============================================
-- E-Complaint & Request System
-- Complete Database Schema
-- Production-Ready with All Features
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================
-- 1. DEPARTMENTS TABLE
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
-- 2. USERS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff', 'citizen') NOT NULL DEFAULT 'citizen',
    `department_id` INT NULL,
    `status` ENUM('active', 'inactive', 'disabled') NOT NULL DEFAULT 'active',
    `phone_number` VARCHAR(20) NULL,
    `address` VARCHAR(500) NULL,
    `active_cases` INT NOT NULL DEFAULT 0,
    `completed_cases` INT NOT NULL DEFAULT 0,
    `last_assigned_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_department` (`department_id`),
    INDEX `idx_phone` (`phone_number`),
    CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. COMPLAINTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `complaints` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `citizen_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `status` ENUM('Pending', 'Assigned', 'In Progress', 'Completed', 'Closed', 'Rejected') NOT NULL DEFAULT 'Pending',
    `priority_level` ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
    `department_id` INT NULL,
    `staff_id` INT NULL,
    `sla_due_at` DATETIME NULL,
    `sla_status` ENUM('On Time', 'Warning', 'Breached') NOT NULL DEFAULT 'On Time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_citizen` (`citizen_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority_level`),
    INDEX `idx_department` (`department_id`),
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_category` (`category`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_sla_due` (`sla_due_at`),
    INDEX `idx_sla_status` (`sla_status`),
    CONSTRAINT `fk_complaints_citizen` FOREIGN KEY (`citizen_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_staff` FOREIGN KEY (`staff_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. COMPLAINT FILES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `complaint_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `file_path` VARCHAR(512) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_type` VARCHAR(100) NOT NULL,
    `file_size` INT NOT NULL DEFAULT 0,
    `uploaded_by_user_id` INT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_uploaded_by` (`uploaded_by_user_id`),
    CONSTRAINT `fk_complaint_files_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_complaint_files_user` FOREIGN KEY (`uploaded_by_user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. STAFF ASSIGNMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `staff_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `staff_id` INT NOT NULL,
    `assigned_by_admin_id` INT NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_staff` (`staff_id`),
    INDEX `idx_assigned_at` (`assigned_at`),
    CONSTRAINT `fk_assignments_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_assignments_staff` FOREIGN KEY (`staff_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_assignments_admin` FOREIGN KEY (`assigned_by_admin_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. STATUS UPDATES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `status_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `updated_by_user_id` INT NOT NULL,
    `role` ENUM('admin', 'staff', 'citizen') NOT NULL,
    `status` ENUM('Pending', 'Assigned', 'In Progress', 'Completed', 'Closed', 'Rejected') NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint` (`complaint_id`),
    INDEX `idx_user` (`updated_by_user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`),
    CONSTRAINT `fk_status_updates_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_status_updates_user` FOREIGN KEY (`updated_by_user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. AUDIT LOGS TABLE
-- ============================================
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

-- ============================================
-- 8. NOTIFICATIONS TABLE
-- ============================================
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

-- ============================================
-- 9. SMS LOGS TABLE
-- ============================================
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

-- ============================================
-- 10. EMAIL LOGS TABLE
-- ============================================
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

-- ============================================
-- 11. ASSIGNMENT LOGS TABLE
-- ============================================
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

-- ============================================
-- 12. PRIORITY CHANGE LOGS TABLE
-- ============================================
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

-- ============================================
-- 13. SLA LOGS TABLE
-- ============================================
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

-- ============================================
-- 14. AUTO ASSIGN SETTINGS TABLE
-- ============================================
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

-- ============================================
-- 15. RATE LIMITS TABLE
-- ============================================
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
-- COMMIT TRANSACTION
-- ============================================
SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- ============================================
-- ADD TABLE COMMENTS
-- ============================================
ALTER TABLE `departments` COMMENT = 'System departments for organizing staff and complaints';
ALTER TABLE `users` COMMENT = 'System users: admin, staff, and citizens';
ALTER TABLE `complaints` COMMENT = 'Complaints and requests submitted by citizens';
ALTER TABLE `complaint_files` COMMENT = 'File attachments for complaints';
ALTER TABLE `staff_assignments` COMMENT = 'Staff assignment history for complaints';
ALTER TABLE `status_updates` COMMENT = 'Status change history for complaints';
ALTER TABLE `audit_logs` COMMENT = 'System audit log for tracking all critical user actions';
ALTER TABLE `notifications` COMMENT = 'User notifications (email, SMS, in-app, real-time)';
ALTER TABLE `sms_logs` COMMENT = 'SMS notification logs';
ALTER TABLE `email_logs` COMMENT = 'Email notification logs';
ALTER TABLE `assignment_logs` COMMENT = 'Detailed assignment change logs';
ALTER TABLE `priority_change_logs` COMMENT = 'Priority level change history';
ALTER TABLE `sla_logs` COMMENT = 'SLA status change history';
ALTER TABLE `auto_assign_settings` COMMENT = 'Auto-assignment configuration settings';
ALTER TABLE `rate_limits` COMMENT = 'API rate limiting records';

