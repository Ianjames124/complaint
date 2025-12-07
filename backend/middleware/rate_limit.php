<?php

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../utils/helpers.php';

/**
 * Rate Limiting Middleware
 * Prevents abuse by limiting requests per endpoint
 */

/**
 * Check rate limit for a user/endpoint combination
 * 
 * @param string $endpoint Endpoint identifier (e.g., 'api/citizen/create-complaint')
 * @param int $userId User ID (0 for unauthenticated users)
 * @param int $maxRequests Maximum requests allowed
 * @param int $timeWindow Time window in seconds (default: 60)
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
 */
function checkRateLimit(
    string $endpoint,
    int $userId = 0,
    int $maxRequests = 5,
    int $timeWindow = 60
): array {
    try {
        $db = (new Database())->getConnection();
        
        // Use IP address for unauthenticated users
        $identifier = $userId > 0 ? "user_{$userId}" : "ip_" . getClientIpAddress();
        $key = md5("{$endpoint}:{$identifier}");
        
        $now = time();
        $windowStart = $now - $timeWindow;
        
        // Clean old entries (older than time window)
        $db->prepare('DELETE FROM rate_limits WHERE created_at < FROM_UNIXTIME(?)')
            ->execute([$windowStart]);
        
        // Count requests in current window
        $stmt = $db->prepare('
            SELECT COUNT(*) as count 
            FROM rate_limits 
            WHERE rate_key = ? AND created_at >= FROM_UNIXTIME(?)
        ');
        $stmt->execute([$key, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $requestCount = (int)($result['count'] ?? 0);
        
        $allowed = $requestCount < $maxRequests;
        $remaining = max(0, $maxRequests - $requestCount);
        
        if ($allowed) {
            // Record this request
            $stmt = $db->prepare('
                INSERT INTO rate_limits (rate_key, endpoint, user_id, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $key,
                $endpoint,
                $userId > 0 ? $userId : null,
                $userId > 0 ? null : getClientIpAddress()
            ]);
        }
        
        $resetAt = $now + $timeWindow;
        
        return [
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset_at' => $resetAt,
            'limit' => $maxRequests
        ];
    } catch (Exception $e) {
        // On error, allow the request (fail open)
        error_log('Rate limit check failed: ' . $e->getMessage());
        return [
            'allowed' => true,
            'remaining' => $maxRequests,
            'reset_at' => time() + $timeWindow,
            'limit' => $maxRequests
        ];
    }
}

/**
 * Enforce rate limit - exits with 429 if limit exceeded
 * 
 * @param string $endpoint Endpoint identifier
 * @param int $userId User ID (0 for unauthenticated)
 * @param int $maxRequests Maximum requests allowed
 * @param int $timeWindow Time window in seconds
 * @return void Exits with 429 if limit exceeded
 */
function enforceRateLimit(
    string $endpoint,
    int $userId = 0,
    int $maxRequests = 5,
    int $timeWindow = 60
): void {
    $rateLimit = checkRateLimit($endpoint, $userId, $maxRequests, $timeWindow);
    
    if (!$rateLimit['allowed']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('X-RateLimit-Limit: ' . $rateLimit['limit']);
        header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
        header('X-RateLimit-Reset: ' . $rateLimit['reset_at']);
        header('Retry-After: ' . ($rateLimit['reset_at'] - time()));
        
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'error_code' => 'rate_limit_exceeded',
            'retry_after' => $rateLimit['reset_at'] - time()
        ]);
        exit;
    }
    
    // Set rate limit headers
    header('X-RateLimit-Limit: ' . $rateLimit['limit']);
    header('X-RateLimit-Remaining: ' . $rateLimit['remaining']);
    header('X-RateLimit-Reset: ' . $rateLimit['reset_at']);
}

// getClientIpAddress() function has been moved to utils/helpers.php

