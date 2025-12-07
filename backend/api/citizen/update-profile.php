<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rate_limit.php';
require_once __DIR__ . '/../../utils/audit_logger.php';
require_once __DIR__ . '/../../utils/security.php';
require_once __DIR__ . '/../../utils/error_handler.php';

// Setup error handlers
setupErrorHandlers();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only citizen can access
$user = authorize(['citizen']);

// Enforce rate limiting
enforceRateLimit('api/citizen/update-profile', $user['id'], 10, 60);

// Read and validate JSON body
$jsonInput = file_get_contents('php://input');
$input = validateJsonInput($jsonInput);
if ($input === false) {
    sendJsonResponse(false, 'Invalid JSON body', null, 400);
}

// Validate and sanitize fields
$fullName = validateString($input['full_name'] ?? '', 2, 255);
$email = validateEmail($input['email'] ?? '');
$phone = validateString($input['phone'] ?? '', 0, 20);
$address = validateString($input['address'] ?? '', 0, 500);

// Validate required fields
if ($fullName === false) {
    sendJsonResponse(false, 'Full name is required and must be between 2 and 255 characters', null, 400);
}

if ($email === false) {
    sendJsonResponse(false, 'Valid email address is required', null, 400);
}

try {
    $db = (new Database())->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set PDO to throw exceptions
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if email is already taken by another user
    $stmtCheck = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmtCheck->execute([$email, $user['id']]);
    if ($stmtCheck->rowCount() > 0) {
        sendJsonResponse(false, 'Email is already taken by another user', null, 400);
    }
    
    // Update user profile
    // Note: If your users table doesn't have phone/address columns, you may need to add them
    // For now, we'll update what exists
    $updateFields = ['full_name = ?', 'email = ?', 'updated_at = NOW()'];
    $updateParams = [$fullName, $email];
    
    // Add phone if column exists (check schema)
    if (!empty($phone)) {
        $updateFields[] = 'phone = ?';
        $updateParams[] = $phone;
    }
    
    // Add address if column exists
    if (!empty($address)) {
        $updateFields[] = 'address = ?';
        $updateParams[] = $address;
    }
    
    $updateParams[] = $user['id']; // For WHERE clause
    
    $sql = 'UPDATE users SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($updateParams);
    
    // Get updated user data
    $stmtUser = $db->prepare('SELECT id, full_name, email, role, phone, address FROM users WHERE id = ?');
    $stmtUser->execute([$user['id']]);
    $updatedUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    
    // Log audit action
    $updatedFields = [];
    if (isset($input['full_name'])) $updatedFields['full_name'] = $fullName;
    if (isset($input['email'])) $updatedFields['email'] = $email;
    if (isset($input['phone'])) $updatedFields['phone'] = $phone;
    if (isset($input['address'])) $updatedFields['address'] = $address;
    
    if (!empty($updatedFields)) {
        logUserUpdate($user['id'], $user['role'], $user['id'], $updatedFields);
    }
    
    sendJsonResponse(true, 'Profile updated successfully', [
        'user' => $updatedUser
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in update-profile.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log('Error in update-profile.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}

