<?php

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    // If vendor doesn't exist, provide a helpful error for endpoints that need JWT
    if (!function_exists('sendJsonResponse')) {
        function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void
        {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            $response = ['success' => $success, 'message' => $message];
            if ($data !== null) {
                $response['data'] = $data;
            }
            echo json_encode($response);
            exit;
        }
    }
    // For functions that need JWT, they'll fail gracefully
    require_once __DIR__ . '/../config/Database.php';
    return; // Early return - JWT functions won't work but basic structure is loaded
}

require_once $vendorAutoload;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../config/Database.php';

/**
 * Common auth helpers for APIs.
 */

function getJwtConfig(): array
{
    return require __DIR__ . '/../config/jwt.php';
}

function getEnvConfig(): array
{
    return require __DIR__ . '/../config/env.php';
}

// Load centralized sendJsonResponse from helpers if not already loaded
if (!function_exists('sendJsonResponse')) {
    // Try to load from helpers.php
    $helpersPath = __DIR__ . '/../utils/helpers.php';
    if (file_exists($helpersPath)) {
        require_once $helpersPath;
    } else {
        // Fallback declaration if helpers.php doesn't exist
        function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void
        {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            $response = [
                'success' => $success,
                'message' => $message,
            ];
            if ($data !== null) {
                $response['data'] = $data;
            }
            echo json_encode($response);
            exit;
        }
    }
}

function getAuthorizationHeaderToken(): ?string
{
    $headers = null;
    
    // Try multiple methods to get Authorization header (Apache/FastCGI compatibility)
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Apache may lowercase header names
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        } elseif (isset($requestHeaders['authorization'])) {
            $headers = trim($requestHeaders['authorization']);
        }
    }
    
    // Also check REDIRECT_HTTP_AUTHORIZATION (common in some Apache setups)
    if (!$headers && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if ($headers && stripos($headers, 'Bearer ') === 0) {
        return substr($headers, 7);
    }

    return null;
}

function getTokenFromCookie(): ?string
{
    $env = getEnvConfig();
    if (!($env['AUTH_COOKIE_ENABLED'] ?? false)) {
        return null;
    }
    $cookieName = $env['AUTH_COOKIE_NAME'] ?? 'auth_token';
    return $_COOKIE[$cookieName] ?? null;
}

function getCurrentUser(): ?array
{
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    $token = getAuthorizationHeaderToken() ?? getTokenFromCookie();
    if (!$token) {
        // Log for debugging (but don't expose in production)
        error_log('getCurrentUser: No token found. Headers: ' . json_encode([
            'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? 'not set',
            'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 'not set',
            'has_apache_headers' => function_exists('apache_request_headers'),
        ]));
        return null;
    }

    $jwtConfig = getJwtConfig();

    try {
        $decoded = JWT::decode($token, new Key($jwtConfig['secret'], 'HS256'));
        $payload = (array) $decoded;
        $cachedUser = (array)($payload['user'] ?? []);
        return $cachedUser;
    } catch (\Firebase\JWT\ExpiredException $e) {
        error_log('JWT expired: ' . $e->getMessage());
        return null;
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        error_log('JWT signature invalid: ' . $e->getMessage());
        return null;
    } catch (Exception $e) {
        error_log('JWT decode error: ' . $e->getMessage() . ' | Token preview: ' . substr($token, 0, 20) . '...');
        return null;
    }
}

function requireAuth(): array
{
    $user = getCurrentUser();
    if (!$user) {
        sendJsonResponse(false, 'Unauthorized', null, 401);
    }
    return $user;
}

/**
 * Ensure the current user has one of the required roles.
 *
 * @param array $roles array of role strings, e.g. ['admin', 'staff']
 * @return array current user payload
 */
function requireRole(array $roles): array
{
    $user = requireAuth();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        sendJsonResponse(false, 'Forbidden: insufficient permissions', null, 403);
    }
    return $user;
}

/**
 * Get Bearer token from Authorization header or cookie
 * 
 * @return string|null The JWT token or null if not found
 */
function get_bearer_token(): ?string
{
    return getAuthorizationHeaderToken() ?? getTokenFromCookie();
}

/**
 * Verify JWT token and return decoded payload
 * 
 * @param string $token The JWT token to verify
 * @return array|null Decoded payload or null if invalid
 */
function verify_jwt(string $token): ?array
{
    $jwtConfig = getJwtConfig();
    
    try {
        $decoded = JWT::decode($token, new Key($jwtConfig['secret'], 'HS256'));
        $payload = (array) $decoded;
        
        // Extract user data from payload
        $user = (array)($payload['user'] ?? []);
        
        // Return full payload with user data
        return [
            'user' => $user,
            'role' => $user['role'] ?? null,
            'id' => $user['id'] ?? null,
            'email' => $user['email'] ?? null,
            'full_name' => $user['full_name'] ?? null,
            'department_id' => $user['department_id'] ?? null,
            'iss' => $payload['iss'] ?? null,
            'iat' => $payload['iat'] ?? null,
            'exp' => $payload['exp'] ?? null,
        ];
    } catch (\Firebase\JWT\ExpiredException $e) {
        error_log('JWT expired: ' . $e->getMessage());
        return null;
    } catch (\Firebase\JWT\SignatureInvalidException $e) {
        error_log('JWT signature invalid: ' . $e->getMessage());
        return null;
    } catch (Exception $e) {
        error_log('JWT decode error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Authorize user based on JWT token and required roles
 * This is the main RBAC authorization function
 * 
 * @param array $roles Array of allowed roles, e.g. ['admin', 'staff'] or ['admin'] for admin only
 * @return array User data (same format as requireRole/requireAuth for backward compatibility)
 */
function authorize(array $roles = []): array
{
    $token = get_bearer_token();
    
    if (!$token) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: No authentication token provided'
        ]);
        exit;
    }
    
    $payload = verify_jwt($token);
    
    if (!$payload) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid or expired token'
        ]);
        exit;
    }
    
    // Extract user data from payload
    $user = $payload['user'] ?? $payload;
    $userRole = $user['role'] ?? $payload['role'] ?? null;
    
    // If roles are specified, check if user has one of the required roles
    if (!empty($roles)) {
        if (!$userRole || !in_array($userRole, $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden: Insufficient permissions',
                'required_roles' => $roles,
                'user_role' => $userRole
            ]);
            exit;
        }
    }
    
    // Return user data in consistent format (same as requireAuth/requireRole)
    return $user;
}


