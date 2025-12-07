-- Migration: Complaint Images Table
-- Creates table for storing complaint image metadata

CREATE TABLE IF NOT EXISTS `complaint_images` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `complaint_id` INT NOT NULL,
    `image_path` VARCHAR(512) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_size` INT NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_complaint_id` (`complaint_id`),
    INDEX `idx_uploaded_at` (`uploaded_at`),
    CONSTRAINT `fk_complaint_images_complaint` FOREIGN KEY (`complaint_id`) 
        REFERENCES `complaints`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT = 'Image attachments for complaints';

