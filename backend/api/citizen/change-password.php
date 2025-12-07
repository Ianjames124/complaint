<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Get current user from JWT token
$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    sendJsonResponse(false, 'Authentication required', null, 401);
}

// Only allow citizens
if (($user['role'] ?? '') !== 'citizen') {
    sendJsonResponse(false, 'Access denied. Only citizens can change their password.', null, 403);
}

// Read JSON body
$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    sendJsonResponse(false, 'Request body is empty', null, 400);
}

$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(false, 'Invalid JSON body: ' . json_last_error_msg(), null, 400);
}

// Extract fields
$currentPassword = $input['current_password'] ?? '';
$newPassword = $input['new_password'] ?? '';
$confirmPassword = $input['confirm_password'] ?? '';

// Validate required fields
if (empty($currentPassword)) {
    sendJsonResponse(false, 'Current password is required', null, 400);
}

if (empty($newPassword)) {
    sendJsonResponse(false, 'New password is required', null, 400);
}

if (strlen($newPassword) < 8) {
    sendJsonResponse(false, 'New password must be at least 8 characters long', null, 400);
}

if ($newPassword !== $confirmPassword) {
    sendJsonResponse(false, 'New password and confirm password do not match', null, 400);
}

try {
    $db = (new Database())->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set PDO to throw exceptions
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get current user's password hash
    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        sendJsonResponse(false, 'User not found', null, 404);
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $userData['password_hash'])) {
        sendJsonResponse(false, 'Current password is incorrect', null, 400);
    }
    
    // Check if new password is same as current
    if (password_verify($newPassword, $userData['password_hash'])) {
        sendJsonResponse(false, 'New password must be different from current password', null, 400);
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmtUpdate = $db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
    $stmtUpdate->execute([$hashedPassword, $user['id']]);
    
    sendJsonResponse(true, 'Password changed successfully');
    
} catch (PDOException $e) {
    error_log('Database error in change-password.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log('Error in change-password.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}

