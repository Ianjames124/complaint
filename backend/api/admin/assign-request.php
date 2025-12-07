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

$admin = requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$requestId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$staffId   = isset($input['staff_id']) ? (int) $input['staff_id'] : 0;

if ($requestId <= 0 || $staffId <= 0) {
    sendJsonResponse(false, 'complaint_id and staff_id are required');
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Ensure this is a request (category starts with "Request:")
    $stmt = $db->prepare("SELECT id FROM complaints WHERE id = ? AND category LIKE 'Request:%' LIMIT 1");
    $stmt->execute([$requestId]);
    if (!$stmt->fetch()) {
        $db->rollBack();
        sendJsonResponse(false, 'Request not found', null, 404);
    }

    $stmt = $db->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();
    if (!$staff || $staff['role'] !== 'staff') {
        $db->rollBack();
        sendJsonResponse(false, 'Staff user not found', null, 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id, assigned_at)
         VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([$requestId, $staffId, $admin['id']]);

    $stmt = $db->prepare('UPDATE complaints SET status = ? WHERE id = ?');
    $stmt->execute(['Assigned', $requestId]);

    $stmt = $db->prepare(
        'INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $requestId,
        $admin['id'],
        'admin',
        'Assigned',
        'Assigned request to staff ID ' . $staffId,
    ]);

    $db->commit();

    // Get complaint details for real-time event
    $stmt = $db->prepare('SELECT citizen_id, title, category FROM complaints WHERE id = ?');
    $stmt->execute([$requestId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    // Emit real-time event to assigned staff
    if ($complaint) {
        send_realtime_event('assignment_created', [
            'complaint_id' => $requestId,
            'staff_id' => $staffId,
            'title' => $complaint['title'] ?? '',
            'category' => $complaint['category'] ?? '',
            'status' => 'Assigned',
            'assigned_by' => $admin['full_name'] ?? 'Admin'
        ]);
    }

    sendJsonResponse(true, 'Request assigned successfully');
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in assign-request.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to assign request', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in assign-request.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}


