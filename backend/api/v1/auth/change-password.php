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
    // Verify authentication
    $authData = requireAuth();
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON input', null, 400);
    }
    
    // Validate required fields
    $requiredFields = ['current_password', 'new_password', 'confirm_password'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missingFields), null, 400);
    }
    
    $currentPassword = $input['current_password'];
    $newPassword = $input['new_password'];
    $confirmPassword = $input['confirm_password'];
    
    // Validate new password
    if ($newPassword !== $confirmPassword) {
        sendResponse(false, 'New password and confirm password do not match', null, 400);
    }
    
    if (strlen($newPassword) < 8) {
        sendResponse(false, 'New password must be at least 8 characters long', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Get current user's password hash
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$authData['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        sendResponse(false, 'Current password is incorrect', null, 400);
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password
    $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$newPasswordHash, $authData['user_id']]);
    
    sendResponse(true, 'Password changed successfully');
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
