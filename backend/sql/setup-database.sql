-- Quick setup script for XAMPP MySQL
-- Run this in phpMyAdmin or MySQL command line

-- Step 1: Create the database
CREATE DATABASE IF NOT EXISTS complaint_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Step 2: Use the database
USE complaint_db;

-- Step 3: Run the schema.sql file (or copy its contents here)
-- The schema.sql file contains all table definitions

-- After running this, import schema.sql and sample_data.sql

