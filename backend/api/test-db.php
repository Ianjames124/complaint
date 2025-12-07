<?php
// Test database connection
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/cors.php';
handleCors();

header('Content-Type: application/json');

$config = require __DIR__ . '/../config/env.php';

$result = [
    'config' => [
        'DB_HOST' => $config['DB_HOST'],
        'DB_NAME' => $config['DB_NAME'],
        'DB_USER' => $config['DB_USER'],
        'DB_PASS' => $config['DB_PASS'] ? '***' : '(empty)',
    ],
    'tests' => []
];

// Test 1: Connect to MySQL server (without database)
try {
    $dsn = sprintf('mysql:host=%s;charset=utf8mb4', $config['DB_HOST']);
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $result['tests']['mysql_server'] = ['status' => 'success', 'message' => 'Connected to MySQL server'];
} catch (PDOException $e) {
    $result['tests']['mysql_server'] = ['status' => 'error', 'message' => $e->getMessage()];
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Test 2: Check if database exists
try {
    $stmt = $pdo->query("SHOW DATABASES LIKE '{$config['DB_NAME']}'");
    $exists = $stmt->fetch();
    if ($exists) {
        $result['tests']['database_exists'] = ['status' => 'success', 'message' => "Database '{$config['DB_NAME']}' exists"];
    } else {
        $result['tests']['database_exists'] = ['status' => 'error', 'message' => "Database '{$config['DB_NAME']}' does NOT exist. You need to create it."];
    }
} catch (PDOException $e) {
    $result['tests']['database_exists'] = ['status' => 'error', 'message' => $e->getMessage()];
}

// Test 3: Connect to the specific database
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['DB_HOST'], $config['DB_NAME']);
    $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASS']);
    $result['tests']['database_connection'] = ['status' => 'success', 'message' => "Successfully connected to database '{$config['DB_NAME']}'"];
    
    // Test 4: Check if tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        $result['tests']['tables'] = ['status' => 'success', 'message' => 'Tables found: ' . implode(', ', $tables)];
    } else {
        $result['tests']['tables'] = ['status' => 'warning', 'message' => 'Database exists but has no tables. Run schema.sql to create tables.'];
    }
} catch (PDOException $e) {
    $result['tests']['database_connection'] = ['status' => 'error', 'message' => $e->getMessage()];
}

echo json_encode($result, JSON_PRETTY_PRINT);

