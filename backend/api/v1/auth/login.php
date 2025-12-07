<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../../config/DB.php';
require_once __DIR__ . '/../../../../config/jwt_config.php';

function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON input', null, 400);
    }
    
    // Validate required fields
    $requiredFields = ['email', 'password'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missingFields), null, 400);
    }
    
    $email = strtolower(trim($input['email']));
    $password = $input['password'];
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Get user by email
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify user exists and password is correct
    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        sendResponse(false, 'Account is not active. Please contact support.', null, 403);
    }
    
    // Generate JWT token
    $tokenPayload = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'name' => $user['name']
    ];
    
    $token = generateJWT($tokenPayload);
    
    // Set cookie with token
    setcookie('auth_token', $token, [
        'expires' => time() + JWT_EXPIRE_SECONDS,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    // Return success response with user data (excluding sensitive info)
    unset($user['password_hash']);
    
    sendResponse(true, 'Login successful', [
        'user' => $user,
        'token' => $token,
        'expires_in' => JWT_EXPIRE_SECONDS
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
