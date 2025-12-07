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
$method = isset($input['method']) ? trim($input['method']) : 'workload'; // 'workload' or 'round_robin'

if ($complaintId <= 0) {
    sendJsonResponse(false, 'complaint_id is required');
}

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get complaint details
    $stmt = $db->prepare('SELECT id, category, priority_level, department_id FROM complaints WHERE id = ? LIMIT 1');
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        $db->rollBack();
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }

    // Get assignment method from settings
    $stmt = $db->prepare('SELECT setting_value FROM auto_assign_settings WHERE setting_key = ?');
    $stmt->execute(['assignment_method']);
    $setting = $stmt->fetch();
    $assignmentMethod = $setting ? $setting['setting_value'] : 'workload';

    // Determine department (from complaint or category mapping)
    $departmentId = $complaint['department_id'];
    
    // If no department, try to map from category
    if (!$departmentId) {
        $stmt = $db->prepare('SELECT id FROM departments WHERE name LIKE ? LIMIT 1');
        $category = $complaint['category'];
        $stmt->execute(["%{$category}%"]);
        $dept = $stmt->fetch();
        $departmentId = $dept ? $dept['id'] : null;
    }

    // Get eligible staff
    $staffQuery = 'SELECT u.id, u.full_name, u.department_id, 
                    COUNT(CASE WHEN c.status IN ("Pending", "Assigned", "In Progress") THEN 1 END) as active_cases,
                    MAX(sa.assigned_at) as last_assigned_at
                    FROM users u
                    LEFT JOIN complaints c ON c.staff_id = u.id AND c.status IN ("Pending", "Assigned", "In Progress")
                    LEFT JOIN staff_assignments sa ON sa.staff_id = u.id
                    WHERE u.role = "staff" AND u.status = "active"';
    
    $params = [];
    if ($departmentId) {
        $staffQuery .= ' AND u.department_id = ?';
        $params[] = $departmentId;
    }
    
    $staffQuery .= ' GROUP BY u.id, u.full_name, u.department_id';
    
    if ($assignmentMethod === 'workload' || $method === 'workload') {
        // Assign to staff with lowest workload
        // Emergency priority gets priority in assignment
        if ($complaint['priority_level'] === 'Emergency') {
            $staffQuery .= ' ORDER BY 
                CASE WHEN COUNT(CASE WHEN c.priority_level = "Emergency" AND c.status IN ("Pending", "Assigned", "In Progress") THEN 1 END) = 0 THEN 0 ELSE 1 END,
                active_cases ASC, 
                last_assigned_at ASC';
        } else {
            $staffQuery .= ' ORDER BY active_cases ASC, last_assigned_at ASC';
        }
    } else {
        // Round-robin: assign to staff with oldest last assignment
        $staffQuery .= ' ORDER BY last_assigned_at ASC NULLS FIRST, active_cases ASC';
    }
    
    $staffQuery .= ' LIMIT 1';
    
    $stmt = $db->prepare($staffQuery);
    $stmt->execute($params);
    $selectedStaff = $stmt->fetch();
    
    if (!$selectedStaff) {
        $db->rollBack();
        sendJsonResponse(false, 'No available staff found for assignment', null, 404);
    }
    
    $staffId = (int) $selectedStaff['id'];
    
    // Check if already assigned
    $stmt = $db->prepare('SELECT id FROM staff_assignments WHERE complaint_id = ? ORDER BY assigned_at DESC LIMIT 1');
    $stmt->execute([$complaintId]);
    $existingAssignment = $stmt->fetch();
    $previousStaffId = null;
    
    if ($existingAssignment) {
        // Get previous staff
        $stmt = $db->prepare('SELECT staff_id FROM staff_assignments WHERE id = ?');
        $stmt->execute([$existingAssignment['id']]);
        $prev = $stmt->fetch();
        $previousStaffId = $prev ? (int) $prev['staff_id'] : null;
        
        // Update existing assignment
        $stmt = $db->prepare('UPDATE staff_assignments SET staff_id = ?, assigned_by_admin_id = ?, assigned_at = NOW() WHERE id = ?');
        $stmt->execute([$staffId, $admin['id'], $existingAssignment['id']]);
    } else {
        // Create new assignment
        $stmt = $db->prepare('INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id, assigned_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$complaintId, $staffId, $admin['id']]);
    }
    
    // Log assignment
    $stmt = $db->prepare('INSERT INTO assignment_logs (complaint_id, previous_staff_id, new_staff_id, assigned_by_admin_id, assignment_type, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $previousStaffId,
        $staffId,
        $admin['id'],
        'auto',
        "Auto-assigned using {$assignmentMethod} method"
    ]);
    
    // Update complaint
    $stmt = $db->prepare('UPDATE complaints SET status = ?, staff_id = ?, department_id = ? WHERE id = ?');
    $stmt->execute(['Assigned', $staffId, $departmentId ?: null, $complaintId]);
    
    // Update staff active cases count
    $stmt = $db->prepare('UPDATE users SET active_cases = active_cases + 1, last_assigned_at = NOW() WHERE id = ?');
    $stmt->execute([$staffId]);
    
    // Add status update
    $stmt = $db->prepare('INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([
        $complaintId,
        $admin['id'],
        'admin',
        'Assigned',
        "Auto-assigned to {$selectedStaff['full_name']} using {$assignmentMethod} method"
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
            'Auto-Assignment System'
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
            'Auto-Assignment System'
        );
        
        // Emit real-time event
        send_realtime_event('assignment_created', [
            'complaint_id' => $complaintId,
            'staff_id' => $staffId,
            'title' => $complaintDetails['title'] ?? '',
            'category' => $complaintDetails['category'] ?? '',
            'priority_level' => $complaintDetails['priority_level'] ?? 'Medium',
            'status' => 'Assigned',
            'assigned_by' => 'Auto-Assignment System',
            'assignment_type' => 'auto'
        ]);
    }
    
    sendJsonResponse(true, 'Complaint auto-assigned successfully', [
        'staff_id' => $staffId,
        'staff_name' => $selectedStaff['full_name'],
        'method' => $assignmentMethod
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in auto-assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to auto-assign complaint', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in auto-assign.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

