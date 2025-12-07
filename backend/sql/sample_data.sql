-- Sample departments
INSERT INTO departments (name, description) VALUES
('General', 'General administration'),
('Public Works', 'Roads, sanitation, infrastructure'),
('Health', 'Public health services');

-- Passwords (plaintext) for reference:
-- Admin: Admin@123
-- Citizen: Citizen@123
-- Staff: Staff@123
--
-- These password_hash values are bcrypt hashes generated via PHP password_hash().

-- Admin user (Default login: admin@example.com / Admin@123)
INSERT INTO users (full_name, email, password_hash, role, department_id, status)
VALUES (
  'System Administrator',
  'admin@example.com',
  '$2y$10$SJAq5Jf9Xh5Q8hqEVCeORO5G9lx.1RUG5wjr4lcACa5oJogXJ5352', -- Admin@123
  'admin',
  1,
  'active'
);

-- Citizen user (Default login: citizen@example.com / Citizen@123)
INSERT INTO users (full_name, email, password_hash, role, status)
VALUES (
  'John Citizen',
  'citizen@example.com',
  '$2y$10$Fsh7Q2F9HAGLEkTQABHJ6.QP3d3Wi3D9W/3rifAnwG9QuOoOZ8xtm', -- Citizen@123
  'citizen',
  'active'
);

-- Staff user (Default login: staff@example.com / Staff@123)
INSERT INTO users (full_name, email, password_hash, role, department_id, status)
VALUES (
  'Staff Member',
  'staff@example.com',
  '$2y$10$eiehI6NUwZxGMVmazNQh9OWK3BsY9yUQcbVdoiWULn7CfdB73B6ie', -- Staff@123
  'staff',
  2,
  'active'
);

-- Example complaint by citizen
INSERT INTO complaints (citizen_id, title, description, category, location, status)
VALUES (
  (SELECT id FROM users WHERE email = 'citizen@example.com' LIMIT 1),
  'Pothole on Main Street',
  'There is a large pothole near the intersection causing traffic issues.',
  'Road',
  'Main Street & 3rd Ave',
  'Assigned'
);

-- Example assignment and status updates
INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id)
VALUES (
  (SELECT id FROM complaints LIMIT 1),
  (SELECT id FROM users WHERE email = 'staff@example.com' LIMIT 1),
  (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1)
);

INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes)
VALUES
(
  (SELECT id FROM complaints LIMIT 1),
  (SELECT id FROM users WHERE email = 'admin@example.com' LIMIT 1),
  'admin',
  'Assigned',
  'Assigned to Public Works staff'
),
(
  (SELECT id FROM complaints LIMIT 1),
  (SELECT id FROM users WHERE email = 'staff@example.com' LIMIT 1),
  'staff',
  'In Progress',
  'Inspection scheduled'
);


