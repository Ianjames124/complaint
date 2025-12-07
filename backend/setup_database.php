<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$db_host = 'localhost';
$db_name = 'complaint_db';
$db_user = 'root'; // Change this to your MySQL username
$db_pass = '';     // Change this to your MySQL password

try {
    // Connect to MySQL server
    $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db_name`");
    
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('admin', 'staff', 'citizen') NOT NULL DEFAULT 'citizen',
            `department_id` INT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create complaints table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `complaints` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `citizen_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `location` VARCHAR(255) NOT NULL,
            `status` ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`citizen_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_citizen` (`citizen_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create requests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `requests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `citizen_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `location` VARCHAR(255) NOT NULL,
            `status` ENUM('pending', 'in_progress', 'completed', 'rejected') DEFAULT 'pending',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`citizen_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX `idx_citizen` (`citizen_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create departments table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `departments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create admin user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = 'admin@example.com'");
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        $password = 'admin123'; // Change this to a secure password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role) 
            VALUES (:name, :email, :password, 'admin')
        ");
        $stmt->execute([
            ':name' => 'Admin User',
            ':email' => 'admin@example.com',
            ':password' => $hashedPassword
        ]);
        
        echo "Admin user created successfully.\n";
        echo "Email: admin@example.com\n";
        echo "Password: $password\n";
        echo "Please change this password after first login.\n";
    }
    
    echo "Database setup completed successfully!\n";
    
} catch(PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
?>
