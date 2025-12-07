<?php
// CORS must be first, before any output or error handling
require_once __DIR__ . '/../../config/cors.php';
handleCors();

// Now set error handling (after CORS headers are set)
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// Set error handler to return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
    ]);
    exit;
});

set_exception_handler(function($exception) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
    ]);
    exit;
});

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/rate_limit.php';
require_once __DIR__ . '/../../utils/security.php';
require_once __DIR__ . '/../../utils/error_handler.php';

// Setup error handlers
setupErrorHandlers();

// Load centralized sendJsonResponse from helpers
if (!function_exists('sendJsonResponse')) {
    require_once __DIR__ . '/../../utils/helpers.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Enforce rate limiting for registration (3 requests per 5 minutes)
enforceRateLimit('api/auth/register', 0, 3, 300);

// Read and validate JSON body
$jsonInput = file_get_contents('php://input');
if (empty($jsonInput)) {
    sendJsonResponse(false, 'Request body is empty', null, 400);
}

$input = json_decode($jsonInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, 'Invalid JSON body: ' . json_last_error_msg(), null, 400);
}

if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid request format', null, 400);
}

// Validate and sanitize input
$fullName = isset($input['full_name']) ? trim($input['full_name']) : '';
$email = isset($input['email']) ? trim(strtolower($input['email'])) : '';
$password = $input['password'] ?? '';

// Validate full_name
if (empty($fullName)) {
    sendJsonResponse(false, 'full_name is required', null, 400);
}
if (strlen($fullName) < 2 || strlen($fullName) > 255) {
    sendJsonResponse(false, 'full_name must be between 2 and 255 characters', null, 400);
}
$fullName = sanitizeInput($fullName);

// Validate email
if (empty($email)) {
    sendJsonResponse(false, 'Email is required', null, 400);
}
$email = validateEmail($email);
if ($email === false) {
    sendJsonResponse(false, 'Valid email address is required', null, 400);
}

// Validate password
if (empty($password)) {
    sendJsonResponse(false, 'Password is required', null, 400);
}
if (strlen($password) < 8) {
    sendJsonResponse(false, 'Password must be at least 8 characters long', null, 400);
}
// Additional password strength check (optional - can be relaxed)
if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    sendJsonResponse(false, 'Password must contain at least one uppercase letter, one lowercase letter, and one number', null, 400);
}

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }
} catch (PDOException $e) {
    error_log('Database connection error in register.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database connection failed. Please try again later.', null, 500);
} catch (Exception $e) {
    error_log('Error in register.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred. Please try again later.', null, 500);
}

// Check unique email
try {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . implode(', ', $db->errorInfo()));
    }
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        sendJsonResponse(false, 'Email already registered', null, 400);
    }
} catch (PDOException $e) {
    error_log('Database error checking email in register.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred while checking email availability', null, 500);
}

// Use secure password hashing with bcrypt
try {
    $passwordHash = hashPassword($password);
    if (!$passwordHash) {
        throw new Exception('Failed to hash password');
    }
} catch (Exception $e) {
    error_log('Password hashing error in register.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred during registration', null, 500);
}

// Insert user
try {
    $stmt = $db->prepare('INSERT INTO users (full_name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$stmt) {
        throw new Exception('Failed to prepare insert statement: ' . implode(', ', $db->errorInfo()));
    }
    
    $stmt->execute([$fullName, $email, $passwordHash, 'citizen', 'active']);
    
    sendJsonResponse(true, 'Registration successful', [
        'user_id' => (int)$db->lastInsertId()
    ], 201);
} catch (PDOException $e) {
    error_log('Database error inserting user in register.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    
    // Check for duplicate email error
    if ($e->errorInfo[1] == 1062) { // MySQL duplicate entry error
        sendJsonResponse(false, 'Email already registered', null, 400);
    } else {
        sendJsonResponse(false, 'Failed to register user. Please try again later.', [
            'error_code' => 'database_error'
        ], 500);
    }
} catch (Exception $e) {
    error_log('Error inserting user in register.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred during registration', null, 500);
}


