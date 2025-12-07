<?php
/**
 * Simple test endpoint to debug create-complaint.php
 * This helps identify which part is failing
 */

// Start output buffering
ob_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$steps = [];
$errors = [];

// Step 1: Check basic PHP
$steps[] = 'PHP version: ' . PHP_VERSION;

// Step 2: Check if files exist
$files = [
    'cors.php' => __DIR__ . '/../../config/cors.php',
    'Database.php' => __DIR__ . '/../../config/Database.php',
    'auth.php' => __DIR__ . '/../../middleware/auth.php',
    'rate_limit.php' => __DIR__ . '/../../middleware/rate_limit.php',
    'audit_logger.php' => __DIR__ . '/../../utils/audit_logger.php',
    'security.php' => __DIR__ . '/../../utils/security.php',
    'error_handler.php' => __DIR__ . '/../../utils/error_handler.php',
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        $steps[] = "✓ File exists: {$name}";
    } else {
        $errors[] = "✗ File missing: {$name} at {$path}";
    }
}

// Step 3: Try to require files
try {
    require_once __DIR__ . '/../../config/cors.php';
    $steps[] = '✓ CORS loaded';
} catch (Exception $e) {
    $errors[] = '✗ CORS error: ' . $e->getMessage();
} catch (Error $e) {
    $errors[] = '✗ CORS fatal: ' . $e->getMessage();
}

try {
    require_once __DIR__ . '/../../config/Database.php';
    $steps[] = '✓ Database loaded';
} catch (Exception $e) {
    $errors[] = '✗ Database error: ' . $e->getMessage();
} catch (Error $e) {
    $errors[] = '✗ Database fatal: ' . $e->getMessage();
}

try {
    require_once __DIR__ . '/../../middleware/auth.php';
    $steps[] = '✓ Auth loaded';
    
    if (function_exists('authorize')) {
        $steps[] = '✓ authorize() function exists';
    } else {
        $errors[] = '✗ authorize() function not found';
    }
} catch (Exception $e) {
    $errors[] = '✗ Auth error: ' . $e->getMessage();
} catch (Error $e) {
    $errors[] = '✗ Auth fatal: ' . $e->getMessage();
}

try {
    require_once __DIR__ . '/../../utils/security.php';
    $steps[] = '✓ Security loaded';
    
    if (function_exists('validateString')) {
        $steps[] = '✓ validateString() exists';
    } else {
        $errors[] = '✗ validateString() not found';
    }
    
    if (function_exists('validateJsonInput')) {
        $steps[] = '✓ validateJsonInput() exists';
    } else {
        $errors[] = '✗ validateJsonInput() not found';
    }
} catch (Exception $e) {
    $errors[] = '✗ Security error: ' . $e->getMessage();
} catch (Error $e) {
    $errors[] = '✗ Security fatal: ' . $e->getMessage();
}

// Step 4: Check database connection
try {
    $db = (new Database())->getConnection();
    if ($db) {
        $steps[] = '✓ Database connection successful';
        
        // Check if complaints table exists
        $stmt = $db->query("SHOW TABLES LIKE 'complaints'");
        if ($stmt->rowCount() > 0) {
            $steps[] = '✓ complaints table exists';
        } else {
            $errors[] = '✗ complaints table does not exist';
        }
    } else {
        $errors[] = '✗ Database connection returned null';
    }
} catch (Exception $e) {
    $errors[] = '✗ Database connection error: ' . $e->getMessage();
} catch (Error $e) {
    $errors[] = '✗ Database connection fatal: ' . $e->getMessage();
}

// Output results
while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode([
    'success' => count($errors) === 0,
    'steps' => $steps,
    'errors' => $errors,
    'total_steps' => count($steps),
    'total_errors' => count($errors)
], JSON_PRETTY_PRINT);

