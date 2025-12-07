<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'complaint_db';  // Using existing database name

try {
    // Connect directly to the existing database
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Read the schema file
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    
    // Remove DROP TABLE statements to prevent data loss
    $schema = preg_replace('/^DROP TABLE IF EXISTS .*;$/m', '', $schema);
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', 
            preg_split('/;\s*[\r\n]+/', $schema)
        )
    );
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "Executed: " . substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : '') . "\n";
            } catch (PDOException $e) {
                // Skip errors for tables/columns that already exist
                if (strpos($e->getMessage(), 'already exists') === false) {
                    throw $e;
                }
                echo "Skipped (already exists): " . substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : '') . "\n";
            }
        }
    }
    
    echo "\nDatabase schema update completed successfully!\n";
    
} catch (PDOException $e) {
    die("Error updating database schema: " . $e->getMessage() . "\n");
}
?>
