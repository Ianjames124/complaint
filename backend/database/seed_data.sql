-- ============================================
-- Sample Seed Data for E-Complaint System
-- ============================================
-- This file contains sample data for testing and development
-- WARNING: Only run this in development/test environments

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. DEPARTMENTS
-- ============================================
INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Public Works', 'Infrastructure, roads, and public facilities maintenance'),
(2, 'Health & Safety', 'Public health, safety concerns, and emergency services'),
(3, 'Environment', 'Environmental protection, waste management, and green spaces'),
(4, 'Transportation', 'Traffic management, parking, and public transportation'),
(5, 'General', 'General complaints and service requests')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- ============================================
-- 2. USERS
-- ============================================
-- Admin User (password: Admin123!)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `department_id`, `status`) VALUES
(1, 'System Administrator', 'admin@complaint-system.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'admin', NULL, 'active')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- Staff Users (password: Staff123!)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `department_id`, `status`, `phone_number`) VALUES
(2, 'John Smith', 'john.smith@complaint-system.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'staff', 1, 'active', '+1234567890'),
(3, 'Sarah Johnson', 'sarah.johnson@complaint-system.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'staff', 2, 'active', '+1234567891'),
(4, 'Michael Brown', 'michael.brown@complaint-system.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'staff', 3, 'active', '+1234567892'),
(5, 'Emily Davis', 'emily.davis@complaint-system.local', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'staff', 4, 'active', '+1234567893')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- Citizen Users (password: Citizen123!)
INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `status`, `phone_number`, `address`) VALUES
(10, 'Alice Williams', 'alice.williams@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'citizen', 'active', '+1987654321', '123 Main Street, City, State 12345'),
(11, 'Bob Martinez', 'bob.martinez@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'citizen', 'active', '+1987654322', '456 Oak Avenue, City, State 12345'),
(12, 'Carol Anderson', 'carol.anderson@example.com', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5GyY5Y5Y5Y5Yu', 'citizen', 'active', '+1987654323', '789 Pine Road, City, State 12345')
ON DUPLICATE KEY UPDATE `full_name` = VALUES(`full_name`);

-- ============================================
-- 3. SAMPLE COMPLAINTS
-- ============================================
INSERT INTO `complaints` (`id`, `citizen_id`, `title`, `description`, `category`, `location`, `status`, `priority_level`, `department_id`, `staff_id`, `sla_due_at`, `sla_status`, `created_at`) VALUES
(1, 10, 'Pothole on Main Street', 'Large pothole near intersection of Main Street and First Avenue causing vehicle damage', 'Infrastructure', 'Main Street & First Avenue', 'Assigned', 'High', 1, 2, DATE_ADD(NOW(), INTERVAL 24 HOUR), 'On Time', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 11, 'Broken Streetlight', 'Streetlight not working on Oak Avenue between 2nd and 3rd Street', 'Infrastructure', 'Oak Avenue, between 2nd and 3rd Street', 'In Progress', 'Medium', 1, 2, DATE_ADD(NOW(), INTERVAL 48 HOUR), 'On Time', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 12, 'Illegal Dumping', 'Large amount of trash dumped in public park near Pine Road', 'Environment', 'Public Park, Pine Road', 'Pending', 'Medium', 3, NULL, DATE_ADD(NOW(), INTERVAL 48 HOUR), 'On Time', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 10, 'Traffic Signal Malfunction', 'Traffic light stuck on red at Main Street and Second Avenue', 'Transportation', 'Main Street & Second Avenue', 'Assigned', 'Emergency', 4, 5, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'On Time', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 11, 'Noise Complaint', 'Excessive noise from construction site after hours', 'General', 'Construction Site, Oak Avenue', 'Completed', 'Low', 5, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 'On Time', DATE_SUB(NOW(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- ============================================
-- 4. STAFF ASSIGNMENTS
-- ============================================
INSERT INTO `staff_assignments` (`complaint_id`, `staff_id`, `assigned_by_admin_id`, `assigned_at`) VALUES
(1, 2, 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 2, 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 5, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 3, 1, DATE_SUB(NOW(), INTERVAL 5 DAY))
ON DUPLICATE KEY UPDATE `assigned_at` = VALUES(`assigned_at`);

-- ============================================
-- 5. STATUS UPDATES
-- ============================================
INSERT INTO `status_updates` (`complaint_id`, `updated_by_user_id`, `role`, `status`, `notes`, `created_at`) VALUES
(1, 1, 'admin', 'Assigned', 'Assigned to John Smith for investigation', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'staff', 'In Progress', 'Site visit scheduled for tomorrow', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 'staff', 'In Progress', 'Working on fixing the streetlight', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(4, 1, 'admin', 'Assigned', 'Emergency assignment - traffic signal malfunction', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 3, 'staff', 'Completed', 'Issue resolved - construction hours adjusted', DATE_SUB(NOW(), INTERVAL 2 DAY))
ON DUPLICATE KEY UPDATE `notes` = VALUES(`notes`);

-- ============================================
-- 6. AUTO ASSIGN SETTINGS
-- ============================================
INSERT INTO `auto_assign_settings` (`setting_key`, `setting_value`, `updated_by_admin_id`) VALUES
('auto_assign_enabled', '1', 1),
('assignment_method', 'workload', 1),
('emergency_priority_alert', '1', 1)
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Seed data inserted successfully!' AS status;

