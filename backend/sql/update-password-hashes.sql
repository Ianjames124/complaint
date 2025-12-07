-- Update password hashes for existing users
-- Run this if you already have users in the database with old password hashes

-- Admin user password: Admin@123
UPDATE users 
SET password_hash = '$2y$10$SJAq5Jf9Xh5Q8hqEVCeORO5G9lx.1RUG5wjr4lcACa5oJogXJ5352'
WHERE email = 'admin@example.com';

-- Citizen user password: Citizen@123
UPDATE users 
SET password_hash = '$2y$10$Fsh7Q2F9HAGLEkTQABHJ6.QP3d3Wi3D9W/3rifAnwG9QuOoOZ8xtm'
WHERE email = 'citizen@example.com';

-- Staff user password: Staff@123
UPDATE users 
SET password_hash = '$2y$10$eiehI6NUwZxGMVmazNQh9OWK3BsY9yUQcbVdoiWULn7CfdB73B6ie'
WHERE email = 'staff@example.com';

