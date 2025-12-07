-- Audit Logs Table Migration
-- Run this migration to add audit logging capability to the system

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('admin', 'staff', 'citizen') NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    related_complaint_id INT NULL,
    related_request_id INT NULL,
    related_user_id INT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_role (role),
    INDEX idx_action_type (action_type),
    INDEX idx_related_complaint_id (related_complaint_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_complaint_id) REFERENCES complaints(id) ON DELETE SET NULL,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment
ALTER TABLE audit_logs COMMENT = 'System audit log for tracking all critical user actions';

