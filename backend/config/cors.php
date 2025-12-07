<?php

/**
 * CORS handler for all API endpoints.
 * MUST be called at the very top of every PHP file, before any output.
 *
 * Usage:
 *   require_once __DIR__ . '/../../config/cors.php';
 *   handleCors();
 */

function handleCors(): void
{
    // Only run in web context (not CLI)
    if (php_sapi_name() === 'cli') {
        return;
    }

    // Prevent any output before headers
    if (ob_get_level() > 0) {
        ob_clean();
    }

    // Remove any existing CORS headers that might have been set by Apache/mod_headers
    // This prevents duplicate headers (e.g., "*, http://localhost:3000")
    if (function_exists('header_remove')) {
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header_remove('Access-Control-Allow-Methods');
        header_remove('Access-Control-Allow-Headers');
        header_remove('Access-Control-Max-Age');
    }

    $config = require __DIR__ . '/env.php';
    $allowedOrigin = $config['ALLOWED_ORIGIN'] ?? 'http://localhost:3000';

    // Origin validation: only allow the configured origin (NEVER use "*" with credentials)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && $origin === $allowedOrigin) {
        header("Access-Control-Allow-Origin: {$origin}", true); // true = replace existing
    } else {
        // Always set the configured origin, never "*"
        header("Access-Control-Allow-Origin: {$allowedOrigin}", true); // true = replace existing
    }

    header('Access-Control-Allow-Credentials: true', true);
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS', true);
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With', true);
    header('Access-Control-Max-Age: 86400', true); // Cache preflight for 24 hours

    // Handle OPTIONS preflight request - MUST exit early with proper headers
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        header('Content-Type: text/plain', true);
        header('Content-Length: 0', true);
        // Ensure no output
        if (ob_get_level() > 0) {
            ob_clean();
        }
        exit(0);
    }
}


