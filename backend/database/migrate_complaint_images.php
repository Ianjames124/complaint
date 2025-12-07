<?php
/**
 * Migration Script: Create complaint_images table
 * Run this script to add the complaint_images table to your database
 */

require_once __DIR__ . '/../config/Database.php';

echo "=== Complaint Images Migration ===\n\n";

try {
    $db = (new Database())->getConnection();
    echo "✓ Connected to database\n\n";
    
    // Read and execute migration SQL
    $sqlFile = __DIR__ . '/migration_complaint_images.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Migration SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );
    
    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $db->exec($statement);
            echo "✓ Success\n";
        }
    }
    
    echo "\n=== Migration completed successfully ===\n";
    echo "The 'complaint_images' table has been created.\n";
    
} catch (PDOException $e) {
    echo "\n✗ Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

