<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$admin = requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$slaDueAt = isset($input['sla_due_at']) ? trim($input['sla_due_at']) : null;
$slaStatus = isset($input['sla_status']) ? trim($input['sla_status']) : null;

if ($complaintId <= 0) {
    sendJsonResponse(false, 'complaint_id is required');
}

$validSlaStatuses = ['On Time', 'Warning', 'Breached'];
if ($slaStatus && !in_array($slaStatus, $validSlaStatuses)) {
    sendJsonResponse(false, 'Invalid sla_status. Must be one of: ' . implode(', ', $validSlaStatuses));
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get current SLA status
    $stmt = $db->prepare('SELECT sla_status, sla_due_at FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }

    $oldSlaStatus = $complaint['sla_status'];
    $oldSlaDueAt = $complaint['sla_due_at'];

    // Update SLA
    $updateFields = [];
    $params = [];
    
    if ($slaDueAt) {
        $updateFields[] = 'sla_due_at = ?';
        $params[] = $slaDueAt;
    }
    
    if ($slaStatus) {
        $updateFields[] = 'sla_status = ?';
        $params[] = $slaStatus;
    }
    
    if (empty($updateFields)) {
        $db->rollBack();
        sendJsonResponse(false, 'No fields to update');
    }
    
    $params[] = $complaintId;
    $sql = 'UPDATE complaints SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Log SLA change if status changed
    if ($slaStatus && $slaStatus !== $oldSlaStatus) {
        $stmt = $db->prepare('INSERT INTO sla_logs (complaint_id, old_status, new_status, timestamp, notes) VALUES (?, ?, ?, NOW(), ?)');
        $stmt->execute([
            $complaintId,
            $oldSlaStatus,
            $slaStatus,
            "SLA status manually updated by admin"
        ]);
    }

    $db->commit();

    sendJsonResponse(true, 'SLA updated successfully', [
        'complaint_id' => $complaintId,
        'old_sla_status' => $oldSlaStatus,
        'new_sla_status' => $slaStatus ?: $oldSlaStatus,
        'old_sla_due_at' => $oldSlaDueAt,
        'new_sla_due_at' => $slaDueAt ?: $oldSlaDueAt
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in update-sla.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to update SLA', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in update-sla.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

