-- ============================================
-- Advanced Staff Assignment Module Migration
-- ============================================
-- Run this SQL to upgrade your database

-- 1. Add priority and SLA columns to complaints table
ALTER TABLE complaints
ADD COLUMN IF NOT EXISTS priority_level ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium',
ADD COLUMN IF NOT EXISTS sla_due_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS sla_status ENUM('On Time', 'Warning', 'Breached') NOT NULL DEFAULT 'On Time',
ADD COLUMN IF NOT EXISTS department_id INT NULL,
ADD COLUMN IF NOT EXISTS staff_id INT NULL,
ADD INDEX idx_priority (priority_level),
ADD INDEX idx_sla_due (sla_due_at),
ADD INDEX idx_sla_status (sla_status),
ADD INDEX idx_department (department_id),
ADD INDEX idx_staff (staff_id),
ADD CONSTRAINT fk_complaints_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
ADD CONSTRAINT fk_complaints_staff FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL;

-- 2. Add performance tracking columns to users table (for staff)
ALTER TABLE users
ADD COLUMN IF NOT EXISTS active_cases INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS completed_cases INT NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_assigned_at DATETIME NULL;

-- 3. Create assignment_logs table
CREATE TABLE IF NOT EXISTS assignment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    previous_staff_id INT NULL,
    new_staff_id INT NOT NULL,
    assigned_by_admin_id INT NOT NULL,
    assignment_type ENUM('auto', 'manual', 'reassignment') NOT NULL DEFAULT 'manual',
    reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_assignment_logs_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_logs_prev_staff FOREIGN KEY (previous_staff_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_assignment_logs_new_staff FOREIGN KEY (new_staff_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_assignment_logs_admin FOREIGN KEY (assigned_by_admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id),
    INDEX idx_new_staff (new_staff_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create priority_change_logs table
CREATE TABLE IF NOT EXISTS priority_change_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    old_priority ENUM('Low', 'Medium', 'High', 'Emergency') NULL,
    new_priority ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL,
    changed_by_user_id INT NOT NULL,
    reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_priority_logs_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    CONSTRAINT fk_priority_logs_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create sla_logs table
CREATE TABLE IF NOT EXISTS sla_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    old_status ENUM('On Time', 'Warning', 'Breached') NULL,
    new_status ENUM('On Time', 'Warning', 'Breached') NOT NULL,
    timestamp DATETIME NOT NULL,
    notes TEXT NULL,
    CONSTRAINT fk_sla_logs_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    INDEX idx_complaint (complaint_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create auto_assign_settings table (for admin configuration)
CREATE TABLE IF NOT EXISTS auto_assign_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_by_admin_id INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_auto_assign_admin FOREIGN KEY (updated_by_admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default auto-assign settings
INSERT INTO auto_assign_settings (setting_key, setting_value) VALUES
('auto_assign_enabled', '1'),
('assignment_method', 'workload'), -- 'workload' or 'round_robin'
('emergency_priority_alert', '1')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- 7. Update existing complaints to have default priority
UPDATE complaints SET priority_level = 'Medium' WHERE priority_level IS NULL OR priority_level = '';

-- 8. Create view for staff workload calculation
CREATE OR REPLACE VIEW staff_workload_view AS
SELECT 
    u.id AS staff_id,
    u.full_name AS staff_name,
    u.department_id,
    d.name AS department_name,
    COUNT(CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN 1 END) AS active_cases,
    COUNT(CASE WHEN c.status = 'Completed' THEN 1 END) AS completed_cases,
    COUNT(CASE WHEN c.status = 'Closed' THEN 1 END) AS closed_cases,
    COUNT(CASE WHEN c.priority_level = 'Emergency' AND c.status IN ('Pending', 'Assigned', 'In Progress') THEN 1 END) AS emergency_cases,
    AVG(CASE 
        WHEN c.status = 'Completed' AND c.created_at IS NOT NULL AND 
             (SELECT MIN(created_at) FROM status_updates WHERE complaint_id = c.id AND status = 'Completed') IS NOT NULL
        THEN TIMESTAMPDIFF(HOUR, c.created_at, (SELECT MIN(created_at) FROM status_updates WHERE complaint_id = c.id AND status = 'Completed'))
        ELSE NULL
    END) AS avg_resolution_hours
FROM users u
LEFT JOIN complaints c ON c.staff_id = u.id
LEFT JOIN departments d ON u.department_id = d.id
WHERE u.role = 'staff' AND u.status = 'active'
GROUP BY u.id, u.full_name, u.department_id, d.name;

