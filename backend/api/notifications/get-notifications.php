<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    sendJsonResponse(false, 'Authentication required', null, 401);
}

$db = (new Database())->getConnection();

try {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $limit = max(1, min(100, $limit)); // Limit between 1 and 100
    
    $unreadOnly = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;
    
    $whereClause = 'WHERE user_id = ?';
    $params = [$user['id']];
    
    if ($unreadOnly) {
        $whereClause .= ' AND status = "sent"';
    }
    
    $sql = "
        SELECT 
            id,
            type,
            title,
            message,
            status,
            related_complaint_id,
            related_type,
            created_at,
            read_at
        FROM notifications
        {$whereClause}
        ORDER BY created_at DESC
        LIMIT ?
    ";
    
    $params[] = $limit;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    
    // Count unread
    $stmtUnread = $db->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND status = "sent"');
    $stmtUnread->execute([$user['id']]);
    $unreadCount = (int) ($stmtUnread->fetch()['count'] ?? 0);
    
    // Format notifications
    foreach ($notifications as &$notification) {
        $notification['id'] = (int) $notification['id'];
        $notification['related_complaint_id'] = $notification['related_complaint_id'] ? (int) $notification['related_complaint_id'] : null;
        $notification['read'] = $notification['status'] === 'read';
        $notification['read_at'] = $notification['read_at'];
    }
    
    sendJsonResponse(true, 'Notifications fetched', [
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total' => count($notifications)
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get-notifications.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to fetch notifications', null, 500);
} catch (Exception $e) {
    error_log('Error in get-notifications.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

