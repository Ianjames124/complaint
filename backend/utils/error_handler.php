<?php

/**
 * Error Handler Utilities
 * Provides consistent error responses and hides sensitive information in production
 */

/**
 * Check if we're in production mode
 * 
 * @return bool True if in production
 */
function isProduction(): bool {
    $env = require __DIR__ . '/../config/env.php';
    return ($_SERVER['SERVER_NAME'] ?? 'localhost') !== 'localhost' 
        && ($_ENV['APP_ENV'] ?? 'development') === 'production';
}

/**
 * Send consistent JSON error response
 * 
 * @param string $message Error message
 * @param int $statusCode HTTP status code
 * @param string|null $errorCode Error code for client handling
 * @param array|null $details Additional error details (only in development)
 * @return void Exits after sending response
 */
function sendErrorResponse(
    string $message,
    int $statusCode = 500,
    ?string $errorCode = null,
    ?array $details = null
): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'message' => $message
    ];
    
    if ($errorCode) {
        $response['error_code'] = $errorCode;
    }
    
    // Only include details in development
    $isProduction = isProduction();
    if (!$isProduction && $details !== null) {
        $response['details'] = $details;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Handle exceptions with consistent error responses
 * 
 * @param Exception $exception Exception to handle
 * @param string $context Context where error occurred (e.g., 'create-complaint')
 * @return void Exits after sending response
 */
function handleException(Exception $exception, string $context = 'unknown'): void {
    error_log("Error in {$context}: " . $exception->getMessage() . 
              " in " . $exception->getFile() . ":" . $exception->getLine());
    
    $isProduction = isProduction();
    
    if ($exception instanceof PDOException) {
        $message = $isProduction 
            ? 'Database error occurred. Please try again later.'
            : 'Database error: ' . $exception->getMessage();
        sendErrorResponse($message, 500, 'database_error');
    } else {
        $message = $isProduction
            ? 'An unexpected error occurred. Please try again later.'
            : 'Error: ' . $exception->getMessage();
        sendErrorResponse($message, 500, 'server_error', 
            $isProduction ? null : [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ]);
    }
}

/**
 * Set up global error handlers
 */
function setupErrorHandlers(): void {
    $isProduction = isProduction();
    
    // Set error reporting based on environment
    if ($isProduction) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
    
    // Set custom error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) use ($isProduction) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        error_log("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
        
        if (!$isProduction) {
            sendErrorResponse(
                "Error: {$errstr}",
                500,
                'php_error',
                [
                    'file' => $errfile,
                    'line' => $errline,
                    'errno' => $errno
                ]
            );
        } else {
            sendErrorResponse('An error occurred', 500, 'server_error');
        }
        
        return true;
    });
    
    // Set exception handler
    set_exception_handler(function($exception) {
        handleException($exception);
    });
}

