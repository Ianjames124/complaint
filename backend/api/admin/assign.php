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
require_once __DIR__ . '/../../utils/notification_sms.php';

// Setup error handlers
setupErrorHandlers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only admin can access
$admin = authorize(['admin']);

// Enforce rate limiting
enforceRateLimit('api/admin/assign', $admin['id'], 10, 60);

// Read and validate JSON body
$jsonInput = file_get_contents('php://input');
$input = validateJsonInput($jsonInput);
if ($input === false) {
    sendJsonResponse(false, 'Invalid JSON body', null, 400);
}

// Validate and sanitize input
$complaintId = validateInt($input['complaint_id'] ?? 0, 1);
$staffId = validateInt($input['staff_id'] ?? 0, 1);

if ($complaintId === false || $staffId === false) {
    sendJsonResponse(false, 'complaint_id and staff_id are required and must be valid positive integers', null, 400);
}

    $db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Validate complaint exists
    $stmt = $db->prepare('SELECT id, status, title FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }
    
    $oldStatus = $complaint['status'] ?? null;

    // Validate staff exists and is staff role
    $stmt = $db->prepare('SELECT id, role, full_name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff || $staff['role'] !== 'staff') {
        $db->rollBack();
        sendJsonResponse(false, 'Staff user not found or invalid role', null, 404);
    }
    
    // Get previous assignment if any
    $stmt = $db->prepare('SELECT staff_id FROM staff_assignments WHERE complaint_id = ? ORDER BY assigned_at DESC LIMIT 1');
    $stmt->execute([$complaintId]);
    $previousAssignment = $stmt->fetch(PDO::FETCH_ASSOC);
    $previousStaffId = $previousAssignment ? (int)$previousAssignment['staff_id'] : null;

    // Create assignment
    $stmt = $db->prepare(
        'INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id, assigned_at)
         VALUES (?, ?, ?, NOW())'
    );
    $adminId = $admin['id'] ?? null;
    if (!$adminId) {
        $db->rollBack();
        sendJsonResponse(false, 'Invalid user data', null, 401);
    }
    
    $stmt->execute([$complaintId, $staffId, $adminId]);

    // Update complaint status
    $stmt = $db->prepare('UPDATE complaints SET status = ? WHERE id = ?');
    $stmt->execute(['Assigned', $complaintId]);

    // Add status update record
    $stmt = $db->prepare(
        'INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $complaintId,
        $adminId,
        'admin',
        'Assigned',
        'Assigned to staff ID ' . $staffId,
    ]);

    $db->commit();
    
    // Log audit action
    logComplaintAssign($adminId, $admin['role'], $complaintId, $staffId, $previousStaffId, 'manual');
    
    // Log status update
    if ($oldStatus !== 'Assigned') {
        logStatusUpdate($adminId, $admin['role'], $complaintId, $oldStatus, 'Assigned', 'Assigned to staff');
    }

    // Get complaint details for notifications and real-time event
    $stmt = $db->prepare('SELECT citizen_id, title, category, priority_level, status FROM complaints WHERE id = ?');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get staff details
    $stmt = $db->prepare('SELECT full_name FROM users WHERE id = ?');
    $stmt->execute([$staffId]);
    $staffDetails = $stmt->fetch();
    
    // Send email notification to staff
    if ($complaint && $staffDetails) {
        sendComplaintAssignedEmail(
            $staffId,
            $complaintId,
            [
                'title' => $complaint['title'] ?? '',
                'category' => $complaint['category'] ?? '',
                'priority_level' => $complaint['priority_level'] ?? 'Medium',
                'status' => $complaint['status'] ?? 'Assigned'
            ],
            $admin['full_name'] ?? 'Admin'
        );
        
        // Send SMS notification to staff
        sendSMSNotification(
            $staffId,
            $complaintId,
            [
                'title' => $complaint['title'] ?? '',
                'category' => $complaint['category'] ?? '',
                'priority_level' => $complaint['priority_level'] ?? 'Medium'
            ],
            $admin['full_name'] ?? 'Admin'
        );
    }
    
    // Emit real-time event to assigned staff
    if ($complaint) {
        send_realtime_event('assignment_created', [
            'complaint_id' => $complaintId,
            'staff_id' => $staffId,
            'title' => $complaint['title'] ?? '',
            'category' => $complaint['category'] ?? '',
            'status' => 'Assigned',
            'assigned_by' => $admin['full_name'] ?? 'Admin'
        ]);
    }
    
    sendJsonResponse(true, 'Complaint assigned successfully');
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to assign complaint', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}


