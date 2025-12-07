<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';
require_once __DIR__ . '/../../utils/send_realtime_event.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$user = requireRole(['admin', 'staff']); // Both admin and staff can update priority

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$newPriority = isset($input['priority_level']) ? trim($input['priority_level']) : '';
$reason = isset($input['reason']) ? trim($input['reason']) : '';

$validPriorities = ['Low', 'Medium', 'High', 'Emergency'];
if ($complaintId <= 0 || !in_array($newPriority, $validPriorities)) {
    sendJsonResponse(false, 'complaint_id and valid priority_level (Low, Medium, High, Emergency) are required');
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get current priority
    $stmt = $db->prepare('SELECT priority_level, citizen_id, title, category, sla_due_at FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }

    $oldPriority = $complaint['priority_level'];

    // If priority hasn't changed, return success
    if ($oldPriority === $newPriority) {
        $db->rollBack();
        sendJsonResponse(true, 'Priority is already set to ' . $newPriority, [
            'priority_level' => $newPriority
        ]);
    }

    // Update priority
    $stmt = $db->prepare('UPDATE complaints SET priority_level = ? WHERE id = ?');
    $stmt->execute([$newPriority, $complaintId]);

    // Log priority change
    $stmt = $db->prepare('INSERT INTO priority_change_logs (complaint_id, old_priority, new_priority, changed_by_user_id, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $oldPriority,
        $newPriority,
        $user['id'],
        $reason ?: "Priority changed from {$oldPriority} to {$newPriority}"
    ]);

    // Recalculate SLA based on new priority
    $slaHours = [
        'Low' => 72,
        'Medium' => 48,
        'High' => 24,
        'Emergency' => 4
    ];

    $slaDueAt = date('Y-m-d H:i:s', strtotime("+{$slaHours[$newPriority]} hours"));
    
    // Get assignment date or creation date
    $stmt = $db->prepare('SELECT COALESCE(MIN(sa.assigned_at), c.created_at) as start_date FROM complaints c LEFT JOIN staff_assignments sa ON sa.complaint_id = c.id WHERE c.id = ?');
    $stmt->execute([$complaintId]);
    $startDate = $stmt->fetch();
    
    if ($startDate && $startDate['start_date']) {
        $slaDueAt = date('Y-m-d H:i:s', strtotime($startDate['start_date'] . " +{$slaHours[$newPriority]} hours"));
    }

    // Update SLA
    $stmt = $db->prepare('UPDATE complaints SET sla_due_at = ?, sla_status = ? WHERE id = ?');
    $currentTime = new DateTime();
    $slaTime = new DateTime($slaDueAt);
    $slaStatus = $currentTime > $slaTime ? 'Breached' : ($slaTime->diff($currentTime)->h < 4 ? 'Warning' : 'On Time');
    
    $stmt->execute([$slaDueAt, $slaStatus, $complaintId]);

    // Log SLA change if status changed
    $stmt = $db->prepare('SELECT sla_status FROM complaints WHERE id = ?');
    $stmt->execute([$complaintId]);
    $currentSlaStatus = $stmt->fetch()['sla_status'];
    
    if ($currentSlaStatus !== $slaStatus) {
        $stmt = $db->prepare('INSERT INTO sla_logs (complaint_id, old_status, new_status, timestamp, notes) VALUES (?, ?, ?, NOW(), ?)');
        $stmt->execute([
            $complaintId,
            $currentSlaStatus,
            $slaStatus,
            "SLA recalculated due to priority change to {$newPriority}"
        ]);
    }

    // Add status update note
    $stmt = $db->prepare('INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $user['id'],
        $user['role'],
        $complaint['status'] ?? 'Pending',
        "Priority changed from {$oldPriority} to {$newPriority}" . ($reason ? ". Reason: {$reason}" : '')
    ]);

    $db->commit();

    // Emit real-time event
    send_realtime_event('complaint_priority_updated', [
        'complaint_id' => $complaintId,
        'citizen_id' => $complaint['citizen_id'],
        'old_priority' => $oldPriority,
        'new_priority' => $newPriority,
        'title' => $complaint['title'] ?? '',
        'updated_by' => $user['full_name'] ?? ($user['role'] === 'admin' ? 'Admin' : 'Staff')
    ]);

    sendJsonResponse(true, 'Priority updated successfully', [
        'complaint_id' => $complaintId,
        'old_priority' => $oldPriority,
        'new_priority' => $newPriority,
        'sla_due_at' => $slaDueAt,
        'sla_status' => $slaStatus
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in update-priority.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to update priority', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in update-priority.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

