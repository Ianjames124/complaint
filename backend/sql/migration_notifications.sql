-- ============================================
-- Email + SMS Notification System Migration
-- ============================================

-- 1. Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('email', 'sms', 'in_app') NOT NULL DEFAULT 'in_app',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'read', 'failed') NOT NULL DEFAULT 'sent',
    related_complaint_id INT NULL,
    related_type VARCHAR(50) NULL, -- 'complaint_received', 'complaint_assigned', 'complaint_resolved'
    metadata JSON NULL, -- Store additional data like email_id, sms_id, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_complaint FOREIGN KEY (related_complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_related_complaint (related_complaint_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create sms_logs table
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    complaint_id INT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'delivered') NOT NULL DEFAULT 'sent',
    provider VARCHAR(50) NULL, -- 'twilio', 'other'
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_logs_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sms_logs_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
    INDEX idx_staff_id (staff_id),
    INDEX idx_complaint_id (complaint_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create email_logs table (for tracking email sends)
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    complaint_id INT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    email_type VARCHAR(50) NOT NULL, -- 'complaint_received', 'complaint_assigned', 'complaint_resolved'
    status ENUM('sent', 'failed', 'bounced') NOT NULL DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_email_logs_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_complaint_id (complaint_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add phone_number column to users table if not exists
ALTER TABLE users
ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) NULL,
ADD INDEX idx_phone_number (phone_number);

