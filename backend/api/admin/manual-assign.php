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
require_once __DIR__ . '/../../utils/notification_email.php';
require_once __DIR__ . '/../../utils/notification_sms.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$admin = requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$complaintId = isset($input['complaint_id']) ? (int) $input['complaint_id'] : 0;
$staffId = isset($input['staff_id']) ? (int) $input['staff_id'] : 0;
$reason = isset($input['reason']) ? trim($input['reason']) : '';
$allowCrossDepartment = isset($input['allow_cross_department']) ? (bool) $input['allow_cross_department'] : false;

if ($complaintId <= 0 || $staffId <= 0) {
    sendJsonResponse(false, 'complaint_id and staff_id are required');
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get complaint details
    $stmt = $db->prepare('SELECT id, department_id, status FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }

    // Get staff details
    $stmt = $db->prepare('SELECT id, full_name, role, department_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch();
    
    if (!$staff || $staff['role'] !== 'staff') {
        $db->rollBack();
        sendJsonResponse(false, 'Staff user not found', null, 404);
    }

    // Check department match if cross-department not allowed
    if (!$allowCrossDepartment && $complaint['department_id'] && $staff['department_id'] != $complaint['department_id']) {
        $db->rollBack();
        sendJsonResponse(false, 'Staff is not in the complaint\'s department. Enable cross-department assignment to proceed.', null, 400);
    }

    // Get previous assignment
    $stmt = $db->prepare('SELECT staff_id FROM staff_assignments WHERE complaint_id = ? ORDER BY assigned_at DESC LIMIT 1');
    $stmt->execute([$complaintId]);
    $previousAssignment = $stmt->fetch();
    $previousStaffId = $previousAssignment ? (int) $previousAssignment['staff_id'] : null;

    // Update or create assignment
    if ($previousAssignment && $previousStaffId === $staffId) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint is already assigned to this staff member', null, 400);
    }

    if ($previousAssignment) {
        // Update existing assignment
        $stmt = $db->prepare('UPDATE staff_assignments SET staff_id = ?, assigned_by_admin_id = ?, assigned_at = NOW() WHERE complaint_id = ? ORDER BY assigned_at DESC LIMIT 1');
        $stmt->execute([$staffId, $admin['id'], $complaintId]);
        
        // Decrease previous staff's active cases
        if ($previousStaffId) {
            $stmt = $db->prepare('UPDATE users SET active_cases = GREATEST(0, active_cases - 1) WHERE id = ?');
            $stmt->execute([$previousStaffId]);
        }
    } else {
        // Create new assignment
        $stmt = $db->prepare('INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id, assigned_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$complaintId, $staffId, $admin['id']]);
    }

    // Log assignment
    $assignmentType = $previousAssignment ? 'reassignment' : 'manual';
    $stmt = $db->prepare('INSERT INTO assignment_logs (complaint_id, previous_staff_id, new_staff_id, assigned_by_admin_id, assignment_type, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $previousStaffId,
        $staffId,
        $admin['id'],
        $assignmentType,
        $reason ?: ($assignmentType === 'reassignment' ? 'Manual reassignment by admin' : 'Manual assignment by admin')
    ]);

    // Update complaint
    $stmt = $db->prepare('UPDATE complaints SET status = ?, staff_id = ?, department_id = ? WHERE id = ?');
    $newDepartmentId = $allowCrossDepartment ? $staff['department_id'] : ($complaint['department_id'] ?: $staff['department_id']);
    $stmt->execute(['Assigned', $staffId, $newDepartmentId, $complaintId]);

    // Update staff active cases count
    $stmt = $db->prepare('UPDATE users SET active_cases = active_cases + 1, last_assigned_at = NOW() WHERE id = ?');
    $stmt->execute([$staffId]);

    // Add status update
    $notes = $assignmentType === 'reassignment' 
        ? "Reassigned from staff ID {$previousStaffId} to {$staff['full_name']}" 
        : "Manually assigned to {$staff['full_name']}";
    if ($reason) {
        $notes .= ". Reason: {$reason}";
    }
    
    $stmt = $db->prepare('INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $admin['id'],
        'admin',
        'Assigned',
        $notes
    ]);

    $db->commit();

    // Get complaint details for notifications and real-time event
    $stmt = $db->prepare('SELECT citizen_id, title, category, priority_level FROM complaints WHERE id = ?');
    $stmt->execute([$complaintId]);
    $complaintDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Send email and SMS notifications to staff
    if ($complaintDetails) {
        sendComplaintAssignedEmail(
            $staffId,
            $complaintId,
            [
                'title' => $complaintDetails['title'] ?? '',
                'category' => $complaintDetails['category'] ?? '',
                'priority_level' => $complaintDetails['priority_level'] ?? 'Medium',
                'status' => 'Assigned'
            ],
            $admin['full_name'] ?? 'Admin'
        );
        
        // Send SMS notification to staff
        sendSMSNotification(
            $staffId,
            $complaintId,
            [
                'title' => $complaintDetails['title'] ?? '',
                'category' => $complaintDetails['category'] ?? '',
                'priority_level' => $complaintDetails['priority_level'] ?? 'Medium'
            ],
            $admin['full_name'] ?? 'Admin'
        );
        
        // Emit real-time event
        send_realtime_event('assignment_created', [
            'complaint_id' => $complaintId,
            'staff_id' => $staffId,
            'title' => $complaintDetails['title'] ?? '',
            'category' => $complaintDetails['category'] ?? '',
            'priority_level' => $complaintDetails['priority_level'] ?? 'Medium',
            'status' => 'Assigned',
            'assigned_by' => $admin['full_name'] ?? 'Admin',
            'assignment_type' => $assignmentType,
            'is_reassignment' => $assignmentType === 'reassignment'
        ]);
    }
    
    sendJsonResponse(true, $assignmentType === 'reassignment' ? 'Complaint reassigned successfully' : 'Complaint assigned successfully', [
        'staff_id' => $staffId,
        'staff_name' => $staff['full_name'],
        'assignment_type' => $assignmentType
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in manual-assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to assign complaint', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in manual-assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

