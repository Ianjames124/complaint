<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    sendJsonResponse(false, 'Authentication required', null, 401);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$notificationId = isset($input['notification_id']) ? (int) $input['notification_id'] : null;
$markAll = isset($input['mark_all']) ? filter_var($input['mark_all'], FILTER_VALIDATE_BOOLEAN) : false;

$db = (new Database())->getConnection();

try {
    if ($markAll) {
        // Mark all as read
        $stmt = $db->prepare('
            UPDATE notifications 
            SET status = "read", read_at = NOW() 
            WHERE user_id = ? AND status = "sent"
        ');
        $stmt->execute([$user['id']]);
        $affected = $stmt->rowCount();
        
        sendJsonResponse(true, 'All notifications marked as read', [
            'marked_count' => $affected
        ]);
    } else {
        if (!$notificationId) {
            sendJsonResponse(false, 'notification_id is required when mark_all is false');
        }
        
        // Mark single notification as read
        $stmt = $db->prepare('
            UPDATE notifications 
            SET status = "read", read_at = NOW() 
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$notificationId, $user['id']]);
        
        if ($stmt->rowCount() === 0) {
            sendJsonResponse(false, 'Notification not found', null, 404);
        }
        
        sendJsonResponse(true, 'Notification marked as read');
    }
    
} catch (PDOException $e) {
    error_log('Database error in mark-read.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to mark notification as read', null, 500);
} catch (Exception $e) {
    error_log('Error in mark-read.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

