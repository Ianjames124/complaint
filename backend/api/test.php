<?php
// Temporary test file to check for errors
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PHP Version: " . phpversion() . "\n";
echo "Testing file includes...\n";

try {
    require_once __DIR__ . '/../config/env.php';
    echo "✓ env.php loaded\n";
    
    $config = require __DIR__ . '/../config/env.php';
    echo "✓ Config loaded: " . json_encode($config) . "\n";
    
    require_once __DIR__ . '/../config/cors.php';
    echo "✓ cors.php loaded\n";
    
    handleCors();
    echo "✓ handleCors() executed\n";
    
    require_once __DIR__ . '/../config/Database.php';
    echo "✓ Database.php loaded\n";
    
    echo "\nAll files loaded successfully!\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

