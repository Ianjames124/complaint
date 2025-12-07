<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';
require_once __DIR__ . '/../../middleware/rate_limit.php';
require_once __DIR__ . '/../../utils/audit_logger.php';
require_once __DIR__ . '/../../utils/security.php';
require_once __DIR__ . '/../../utils/error_handler.php';
require_once __DIR__ . '/../../utils/send_realtime_event.php';
require_once __DIR__ . '/../../utils/notification_email.php';

// Setup error handlers
setupErrorHandlers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only admin can access
$admin = authorize(['admin']);

// Enforce rate limiting
enforceRateLimit('api/admin/update-status', $admin['id'], 20, 60);

// Read and validate JSON body
$jsonInput = file_get_contents('php://input');
$input = validateJsonInput($jsonInput);
if ($input === false) {
    sendJsonResponse(false, 'Invalid JSON body', null, 400);
}

// Validate and sanitize input
$complaintId = validateInt($input['complaint_id'] ?? 0, 1);
$status = validateEnum($input['status'] ?? '', ['Pending', 'Assigned', 'In Progress', 'Completed', 'Closed']);
$notes = validateString($input['notes'] ?? '', 0, 1000);

if ($complaintId === false || $status === false) {
    sendJsonResponse(false, 'complaint_id and status are required. Status must be one of: Pending, Assigned, In Progress, Completed, Closed', null, 400);
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get current status before update
    $stmt = $db->prepare('SELECT id, status, title FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }
    
    $oldStatus = $complaint['status'] ?? null;

    $stmt = $db->prepare('UPDATE complaints SET status = ? WHERE id = ?');
    $stmt->execute([$status, $complaintId]);

    $stmt = $db->prepare(
        'INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $adminId = $admin['id'] ?? null;
    $notesValue = ($notes !== false) ? $notes : '';
    $stmt->execute([
        $complaintId,
        $adminId,
        'admin',
        $status,
        $notesValue,
    ]);

    $db->commit();
    
    // Log audit action
    $adminId = $admin['id'] ?? null;
    if ($adminId) {
        logStatusUpdate($adminId, $admin['role'], $complaintId, $oldStatus, $status, ($notes !== false && $notes !== '') ? $notes : null);
    }

    // Get complaint details for notifications and real-time event
    $stmt = $db->prepare('SELECT citizen_id, title, status FROM complaints WHERE id = ?');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Send email notification if complaint is resolved
    if ($complaint && isset($complaint['citizen_id']) && in_array($status, ['Completed', 'Closed'])) {
        sendComplaintResolvedEmail(
            (int)$complaint['citizen_id'],
            $complaintId,
            [
                'title' => $complaint['title'] ?? '',
                'status' => $status
            ],
            ($admin['full_name'] ?? 'Admin')
        );
    }
    
    // Emit real-time event to citizen and admin
    if ($complaint && isset($complaint['citizen_id'])) {
        send_realtime_event('complaint_status_updated', [
            'complaint_id' => $complaintId,
            'citizen_id' => (int)$complaint['citizen_id'],
            'title' => $complaint['title'] ?? '',
            'old_status' => $complaint['status'] ?? '',
            'new_status' => $status,
            'updated_by' => ($admin['full_name'] ?? 'Admin'),
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    sendJsonResponse(true, 'Status updated successfully');
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in update-status.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to update status', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in update-status.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}


