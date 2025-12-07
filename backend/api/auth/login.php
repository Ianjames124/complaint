<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

// Include required files for JWT (must be before use statement)
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rate_limit.php';
require_once __DIR__ . '/../../utils/security.php';
require_once __DIR__ . '/../../utils/error_handler.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

// Setup error handlers
setupErrorHandlers();

// Set error handlers (after CORS headers are set)
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

try {

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed',
        ]);
        exit;
    }

    // Read and validate JSON body first (before rate limiting)
    $jsonInput = file_get_contents('php://input');
    $input = validateJsonInput($jsonInput);
    if ($input === false) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON body',
        ]);
        exit;
    }

    // Check rate limiting for login attempts (10 failed attempts per 5 minutes per IP)
    // We check without recording - will only record on failed login
    $ipAddress = getClientIpAddress();
    $rateLimitKey = md5("api/auth/login:ip_{$ipAddress}");
    
    try {
        $db = (new Database())->getConnection();
        $now = time();
        $windowStart = $now - 300; // 5 minutes
        
        // Clean old entries
        $db->prepare('DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?) AND endpoint = ?')
            ->execute([$windowStart, 'api/auth/login']);
        
        // Count failed attempts in current window (without recording this one yet)
        $stmt = $db->prepare('
            SELECT COUNT(*) as count 
            FROM rate_limits 
            WHERE rate_key = ? AND created_at >= FROM_UNIXTIME(?)
        ');
        $stmt->execute([$rateLimitKey, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $failedAttempts = (int)($result['count'] ?? 0);
        
        $maxAttempts = 10;
        $allowed = $failedAttempts < $maxAttempts;
        
        if (!$allowed) {
            $retryAfter = 300 - ($now - $windowStart);
            $minutes = max(1, ceil($retryAfter / 60));
            
            http_response_code(429);
            header('Content-Type: application/json');
            header('X-RateLimit-Limit: ' . $maxAttempts);
            header('X-RateLimit-Remaining: 0');
            header('X-RateLimit-Reset: ' . ($now + $retryAfter));
            header('Retry-After: ' . $retryAfter);
            
            echo json_encode([
                'success' => false,
                'message' => "Too many login attempts. Please wait {$minutes} minute(s) before trying again.",
                'error_code' => 'rate_limit_exceeded',
                'retry_after' => $retryAfter,
                'retry_after_minutes' => $minutes
            ]);
            exit;
        }
    } catch (Exception $e) {
        // If rate limit check fails, allow the request (fail open)
        error_log('Rate limit check failed: ' . $e->getMessage());
    }

    // Validate and sanitize email
    $email = validateEmail($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($email === false) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Valid email address is required',
        ]);
        exit;
    }

    if (empty($password)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Password is required',
        ]);
        exit;
    }

    $db = (new Database())->getConnection();

    // Query user
    $stmt = $db->prepare('SELECT id, full_name, email, password_hash, role, department_id, status 
                          FROM users 
                          WHERE email = ? 
                          LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // User not found - record failed attempt and don't reveal if email exists
        error_log('Login attempt: User not found for email: ' . $email);
        
        // Record failed login attempt for rate limiting
        try {
            $db = (new Database())->getConnection();
            $ipAddress = getClientIpAddress();
            $stmt = $db->prepare('
                INSERT INTO rate_limits (rate_key, endpoint, user_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $rateLimitKey,
                'api/auth/login',
                null,
                $ipAddress
            ]);
        } catch (Exception $e) {
            error_log('Failed to record rate limit: ' . $e->getMessage());
        }
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password',
        ]);
        exit;
    }
    
    // Verify password using secure method
    if (!verifyPassword($password, $user['password_hash'])) {
        // Password incorrect - record failed attempt
        error_log('Login attempt: Invalid password for email: ' . $email);
        
        // Record failed login attempt for rate limiting
        try {
            $db = (new Database())->getConnection();
            $ipAddress = getClientIpAddress();
            $stmt = $db->prepare('
                INSERT INTO rate_limits (rate_key, endpoint, user_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $rateLimitKey,
                'api/auth/login',
                null,
                $ipAddress
            ]);
        } catch (Exception $e) {
            error_log('Failed to record rate limit: ' . $e->getMessage());
        }
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password',
        ]);
        exit;
    }
    
    // Successful login - clear rate limit entries for this IP to allow normal usage
    try {
        $db = (new Database())->getConnection();
        $ipAddress = getClientIpAddress();
        $key = md5("api/auth/login:ip_{$ipAddress}");
        $stmt = $db->prepare('DELETE FROM rate_limits WHERE rate_key = ?');
        $stmt->execute([$key]);
    } catch (Exception $e) {
        error_log('Failed to clear rate limit on successful login: ' . $e->getMessage());
    }

    if ($user['status'] !== 'active') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Account is not active',
        ]);
        exit;
    }
    
    // Check if password needs rehashing (e.g., if cost parameter changed)
    if (passwordNeedsRehash($user['password_hash'])) {
        $newHash = hashPassword($password);
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $user['id']]);
    }

    // JWT config
    $jwtConfig = getJwtConfig();
    $envConfig = getEnvConfig();

    $now = time();
    $payload = [
        'iss' => $jwtConfig['issuer'],
        'iat' => $now,
        'exp' => $now + $jwtConfig['expires_in'],
        'user' => [
            'id'            => (int)$user['id'],
            'full_name'     => $user['full_name'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'department_id' => $user['department_id'],
        ],
    ];

    // Encode token
    $token = JWT::encode($payload, $jwtConfig['secret'], 'HS256');

    // Optional cookie login with secure settings
    if ($envConfig['AUTH_COOKIE_ENABLED'] ?? false) {
        $cookieName = $envConfig['AUTH_COOKIE_NAME'] ?? 'auth_token';
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            $cookieName,
            $token,
            [
                'expires'  => $now + $jwtConfig['expires_in'],
                'path'     => '/',
                'domain'   => '', // Empty for current domain only
                'secure'   => $isSecure, // Only send over HTTPS
                'httponly' => true, // Prevent JavaScript access
                'samesite' => 'Strict', // CSRF protection
            ]
        );
    }

    // Success response - unified format
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $token,
            'user'  => $payload['user'],
        ],
    ]);
    exit;

} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during login',
    ]);
    exit;
}
