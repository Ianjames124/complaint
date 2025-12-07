<?php
/**
 * Common Helper Functions
 * Centralized utility functions used across the application
 */

/**
 * Send JSON response with consistent format
 * 
 * @param bool $success Whether the operation was successful
 * @param string $message Response message
 * @param mixed $data Optional data to include in response
 * @param int $statusCode HTTP status code (default: 200)
 * @return void Exits after sending response
 */
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void
    {
        // Clear any output buffers to prevent accidental output
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Set HTTP status code
        http_response_code($statusCode);
        
        // Set JSON content type header
        header('Content-Type: application/json; charset=UTF-8');
        
        // Build response array
        $response = [
            'success' => $success,
            'message' => $message
        ];
        
        // Add data if provided
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        // Output JSON and exit
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Get client IP address with support for proxies and load balancers
 * 
 * @return string IP address of the client
 */
if (!function_exists('getClientIpAddress')) {
    function getClientIpAddress(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Standard proxy header
            'REMOTE_ADDR'            // Default fallback
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                // Handle comma-separated IPs (e.g., X-Forwarded-For)
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to default remote address (least reliable)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

