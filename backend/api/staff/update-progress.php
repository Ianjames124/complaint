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
require_once __DIR__ . '/../../utils/send_realtime_event.php';
require_once __DIR__ . '/../../utils/notification_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only staff can access
$staff = authorize(['staff']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$status      = trim($input['status'] ?? '');
$notes       = trim($input['notes'] ?? '');
$fileIds     = $input['file_ids'] ?? []; // optional

if ($complaintId <= 0 || $status === '') {
    sendJsonResponse(false, 'complaint_id and status are required');
}

$allowedStatuses = ['In Progress', 'Completed'];
if (!in_array($status, $allowedStatuses, true)) {
    sendJsonResponse(false, 'Invalid status value for staff update');
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Use RBAC access check to ensure staff can only update assigned complaints
    $staffId = $staff['id'] ?? null;
    if (!$staffId) {
        $db->rollBack();
        sendJsonResponse(false, 'Invalid user data', null, 401);
    }
    
    if (!canAccessComplaint($staff, $complaintId, $db)) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint is not assigned to this staff member', null, 403);
    }

    // Update complaint status
    $stmt = $db->prepare('UPDATE complaints SET status = ? WHERE id = ?');
    $stmt->execute([$status, $complaintId]);

    // Insert status update
    $stmt = $db->prepare(
        'INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $complaintId,
        $staffId,
        'staff',
        $status,
        $notes,
    ]);

    // Optionally associate files with complaint
    if (is_array($fileIds) && count($fileIds) > 0) {
        $in       = implode(',', array_fill(0, count($fileIds), '?'));
        $params   = array_merge([$complaintId], $fileIds);
        $stmtFile = $db->prepare("UPDATE complaint_files SET complaint_id = ? WHERE id IN ($in)");
        $stmtFile->execute($params);
    }

    $db->commit();

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
            $staff['full_name'] ?? 'Staff'
        );
    }
    
    // Emit real-time event to citizen
    if ($complaint && isset($complaint['citizen_id'])) {
        send_realtime_event('complaint_status_updated', [
            'complaint_id' => $complaintId,
            'citizen_id' => (int)$complaint['citizen_id'],
            'title' => $complaint['title'] ?? '',
            'old_status' => $complaint['status'] ?? '',
            'new_status' => $status,
            'updated_by' => $staff['full_name'] ?? 'Staff',
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    sendJsonResponse(true, 'Progress updated successfully');
} catch (PDOException $e) {
    $db->rollBack();
    sendJsonResponse(false, 'Failed to update progress');
}


