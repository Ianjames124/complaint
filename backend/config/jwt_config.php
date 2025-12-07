<?php
// Load environment variables
require_once __DIR__ . '/env.php';

// JWT Configuration
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secret-key-here-change-this-in-production');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRE_SECONDS', 86400); // 24 hours

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function for debugging
function log_message($message, $data = null) {
    $log = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($data !== null) {
        $log .= ': ' . (is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT));
    }
    error_log($log);
}

// JWT Token Functions
function generateJWT($payload) {
    $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
    $payload['iat'] = time();
    $payload['exp'] = time() + JWT_EXPIRE_SECONDS;
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function verifyJWT($token) {
    try {
        if (!preg_match('/^[a-zA-Z0-9\-\_]+\.[a-zA-Z0-9\-\_]+\.[a-zA-Z0-9\-\_]+$/', $token)) {
            return false;
        }
        
        list($header, $payload, $signature) = explode('.', $token);
        
        // Verify signature
        $signature = str_replace(['-', '_'], ['+', '/'], $signature);
        $decodedSignature = base64_decode($signature);
        $expectedSignature = hash_hmac('sha256', "$header.$payload", JWT_SECRET, true);
        
        if (!hash_equals($expectedSignature, $decodedSignature)) {
            return false;
        }
        
        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        // Check if token is expired
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false;
        }
        
        return $decodedPayload;
    } catch (Exception $e) {
        error_log("JWT Verification Error: " . $e->getMessage());
        return false;
    }
}

function getAuthToken() {
    // Check for token in Authorization header
    $headers = getallheaders();
    
    // Log request details for debugging
    log_message('Request method', $_SERVER['REQUEST_METHOD']);
    log_message('Request URI', $_SERVER['REQUEST_URI']);
    
    // Check for Authorization header (case-insensitive)
    $authHeader = '';
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'authorization') {
            $authHeader = $value;
            break;
        }
    }
    
    // Try to extract token from Authorization header
    if ($authHeader) {
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            log_message('Using token from Authorization header', substr($token, 0, 10) . '...');
            return $token;
        }
        log_message('Invalid Authorization header format', $authHeader);
    }
    
    // Check for token in cookies
    if (isset($_COOKIE['auth_token'])) {
        $token = $_COOKIE['auth_token'];
        log_message('Using token from cookie', substr($token, 0, 10) . '...');
        return $token;
    }
    
    // Check for token in POST data (for form submissions)
    if (!empty($_POST['token'])) {
        $token = $_POST['token'];
        log_message('Using token from POST data', substr($token, 0, 10) . '...');
        return $token;
    }
    
    // Check for token in GET parameters (for testing only)
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        log_message('Using token from URL parameter (for testing)', substr($token, 0, 10) . '...');
        return $token;
    }
    
    log_message('No authentication token found in request');
    return null;
}

function requireAuth($requiredRole = null) {
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    $token = getAuthToken();
    
    log_message('Authentication check', [
        'endpoint' => $_SERVER['REQUEST_URI'],
        'method' => $_SERVER['REQUEST_METHOD'],
        'has_token' => !empty($token)
    ]);
    
    if (!$token) {
        log_message('No token provided in request', [
            'headers' => getallheaders(),
            'cookies' => $_COOKIE
        ]);
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No authentication token provided',
            'error_code' => 'missing_token',
            'hint' => 'Make sure to include the Authorization header with Bearer token'
        ]);
        exit();
    }

    $payload = verifyJWT($token);
    
    if (!$payload) {
        log_message('Invalid or expired token', [
            'token' => substr($token, 0, 10) . '...' . substr($token, -10)
        ]);
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired authentication token',
            'error_code' => 'invalid_token',
            'hint' => 'Please log in again to get a new token'
        ]);
        exit();
    }

    // Check if role is required and validate it
    if ($requiredRole !== null) {
        if (!isset($payload['role'])) {
            log_message('No role in JWT payload', $payload);
            
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User role not found in token',
                'error_code' => 'missing_role',
                'payload' => $payload
            ]);
            exit();
        }
        
        if ($payload['role'] !== $requiredRole) {
            log_message('Insufficient permissions', [
                'required_role' => $requiredRole,
                'user_role' => $payload['role'],
                'user_id' => $payload['sub'] ?? null
            ]);
            
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Insufficient permissions',
                'error_code' => 'insufficient_permissions',
                'required_role' => $requiredRole,
                'current_role' => $payload['role']
            ]);
            exit();
        }
    }

    // Add user_id to payload if not present (for backward compatibility)
    if (!isset($payload['user_id']) && isset($payload['sub'])) {
        $payload['user_id'] = $payload['sub'];
    }

    return $payload;
}

function requireAdmin() {
    return requireAuth('admin');
}

function requireStaff() {
    return requireAuth('staff');
}

function requireCitizen() {
    return requireAuth('citizen');
}
?>
