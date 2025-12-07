-- ============================================
-- Safe Migration Script for Existing Database
-- ============================================
-- This script uses stored procedures to safely add columns/indexes
-- Works with MySQL 5.7+ and MariaDB 10.2+

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ============================================
-- Helper Procedure: Add Column If Not Exists
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS AddColumnIfNotExists$$
CREATE PROCEDURE AddColumnIfNotExists(
    IN tableName VARCHAR(128),
    IN columnName VARCHAR(128),
    IN columnDefinition TEXT
)
BEGIN
    DECLARE columnExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO columnExists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = tableName
        AND COLUMN_NAME = columnName;
    
    IF columnExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD COLUMN `', columnName, '` ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- ============================================
-- Helper Procedure: Add Index If Not Exists
-- ============================================
DROP PROCEDURE IF EXISTS AddIndexIfNotExists$$
CREATE PROCEDURE AddIndexIfNotExists(
    IN tableName VARCHAR(128),
    IN indexName VARCHAR(128),
    IN indexDefinition TEXT
)
BEGIN
    DECLARE indexExists INT DEFAULT 0;
    
    SELECT COUNT(*) INTO indexExists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = tableName
        AND INDEX_NAME = indexName;
    
    IF indexExists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` ADD INDEX `', indexName, '` ', indexDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- 1. CREATE DEPARTMENTS TABLE
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
-- Ensure proper column types
ALTER TABLE `users`
    MODIFY COLUMN `full_name` VARCHAR(150) NOT NULL,
    MODIFY COLUMN `email` VARCHAR(150) NOT NULL;

-- Add missing columns
CALL AddColumnIfNotExists('users', 'department_id', 'INT NULL');
CALL AddColumnIfNotExists('users', 'phone_number', 'VARCHAR(20) NULL');
CALL AddColumnIfNotExists('users', 'address', 'VARCHAR(500) NULL');
CALL AddColumnIfNotExists('users', 'active_cases', 'INT NOT NULL DEFAULT 0');
CALL AddColumnIfNotExists('users', 'completed_cases', 'INT NOT NULL DEFAULT 0');
CALL AddColumnIfNotExists('users', 'last_assigned_at', 'DATETIME NULL');
CALL AddColumnIfNotExists('users', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Update role enum if needed
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin', 'staff', 'citizen') NOT NULL DEFAULT 'citizen';
ALTER TABLE `users` MODIFY COLUMN `status` ENUM('active', 'inactive', 'disabled') NOT NULL DEFAULT 'active';

-- Add indexes
CALL AddIndexIfNotExists('users', 'idx_role', '(role)');
CALL AddIndexIfNotExists('users', 'idx_status', '(status)');
CALL AddIndexIfNotExists('users', 'idx_department', '(department_id)');
CALL AddIndexIfNotExists('users', 'idx_phone', '(phone_number)');

-- Add foreign key
ALTER TABLE `users`
    DROP FOREIGN KEY IF EXISTS `fk_users_department`;
ALTER TABLE `users`
    ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 3. UPDATE COMPLAINTS TABLE
-- ============================================
-- Ensure proper status enum
ALTER TABLE `complaints` 
    MODIFY COLUMN `status` ENUM('Pending', 'Assigned', 'In Progress', 'Completed', 'Closed', 'Rejected') NOT NULL DEFAULT 'Pending';

-- Add missing columns
CALL AddColumnIfNotExists('complaints', 'title', 'VARCHAR(255) NULL');
CALL AddColumnIfNotExists('complaints', 'priority_level', "ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium'");
CALL AddColumnIfNotExists('complaints', 'department_id', 'INT NULL');
CALL AddColumnIfNotExists('complaints', 'staff_id', 'INT NULL');
CALL AddColumnIfNotExists('complaints', 'sla_due_at', 'DATETIME NULL');
CALL AddColumnIfNotExists('complaints', 'sla_status', "ENUM('On Time', 'Warning', 'Breached') NOT NULL DEFAULT 'On Time'");
CALL AddColumnIfNotExists('complaints', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Migrate subject to title if needed
UPDATE `complaints` SET `title` = `subject` WHERE `title` IS NULL AND `subject` IS NOT NULL;

-- Add indexes
CALL AddIndexIfNotExists('complaints', 'idx_priority', '(priority_level)');
CALL AddIndexIfNotExists('complaints', 'idx_department', '(department_id)');
CALL AddIndexIfNotExists('complaints', 'idx_staff', '(staff_id)');
CALL AddIndexIfNotExists('complaints', 'idx_sla_due', '(sla_due_at)');
CALL AddIndexIfNotExists('complaints', 'idx_sla_status', '(sla_status)');

-- Add foreign keys
ALTER TABLE `complaints`
    DROP FOREIGN KEY IF EXISTS `fk_complaints_department`,
    DROP FOREIGN KEY IF EXISTS `fk_complaints_staff`;
ALTER TABLE `complaints`
    ADD CONSTRAINT `fk_complaints_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_complaints_staff` FOREIGN KEY (`staff_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 4. UPDATE COMPLAINT_FILES TABLE
-- ============================================
CALL AddColumnIfNotExists('complaint_files', 'file_name', 'VARCHAR(255) NULL');
CALL AddColumnIfNotExists('complaint_files', 'file_size', 'INT NOT NULL DEFAULT 0');
CALL AddColumnIfNotExists('complaint_files', 'uploaded_by_user_id', 'INT NULL');

-- Migrate file_name from file_path if needed
UPDATE `complaint_files` 
SET `file_name` = SUBSTRING_INDEX(`file_path`, '/', -1) 
WHERE `file_name` IS NULL AND `file_path` IS NOT NULL;

CALL AddIndexIfNotExists('complaint_files', 'idx_uploaded_by', '(uploaded_by_user_id)');

ALTER TABLE `complaint_files`
    DROP FOREIGN KEY IF EXISTS `fk_complaint_files_user`;
ALTER TABLE `complaint_files`
    ADD CONSTRAINT `fk_complaint_files_user` FOREIGN KEY (`uploaded_by_user_id`) 
        REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- ============================================
-- 5. CREATE MISSING TABLES
-- ============================================
-- (Use CREATE TABLE IF NOT EXISTS from complete_schema.sql)
-- Audit logs, notifications, sms_logs, email_logs, etc.

-- See complete_schema.sql for full table definitions
-- These tables are created with IF NOT EXISTS, so safe to run multiple times

-- ============================================
-- 6. UPDATE EXISTING DATA
-- ============================================
UPDATE `complaints` SET `priority_level` = 'Medium' WHERE `priority_level` IS NULL OR `priority_level` = '';
UPDATE `complaints` SET `sla_status` = 'On Time' WHERE `sla_status` IS NULL OR `sla_status` = '';

-- ============================================
-- CLEANUP
-- ============================================
DROP PROCEDURE IF EXISTS AddColumnIfNotExists;
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Migration completed successfully!' AS status;

