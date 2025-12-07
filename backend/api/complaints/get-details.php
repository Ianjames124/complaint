<?php
/**
 * Get Complaint Details (Admin/Staff/Citizen)
 * Returns complaint details with RBAC access control
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/helpers.php';
require_once __DIR__ . '/../../utils/image_upload.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';

handleCors();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Get authenticated user
$user = authorize(['admin', 'staff', 'citizen']);

if (!$user || !isset($user['id']) || !isset($user['role'])) {
    sendJsonResponse(false, 'Invalid user data', null, 401);
}

// Get complaint ID from query parameter
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaintId <= 0) {
    sendJsonResponse(false, 'Invalid complaint ID', null, 400);
}

try {
    $db = (new Database())->getConnection();
    
    // Build query based on user role
    $query = '
        SELECT c.*, 
               u.full_name as citizen_name, 
               u.email as citizen_email,
               staff.full_name as staff_name,
               staff.email as staff_email
        FROM complaints c
        JOIN users u ON c.citizen_id = u.id
        LEFT JOIN users staff ON c.staff_id = staff.id
        WHERE c.id = ?
    ';
    
    $params = [$complaintId];
    
    // Add RBAC filter
    if ($user['role'] === 'citizen') {
        $query .= ' AND c.citizen_id = ?';
        $params[] = $user['id'];
    } elseif ($user['role'] === 'staff') {
        // Staff can only see assigned complaints
        $query .= ' AND c.staff_id = ?';
        $params[] = $user['id'];
    }
    // Admin can see all complaints (no additional filter)
    
    $query .= ' LIMIT 1';
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$complaint) {
        sendJsonResponse(false, 'Complaint not found or access denied', null, 404);
    }
    
    // Get status updates
    $stmtStatus = $db->prepare('
        SELECT su.*, u.full_name as updated_by_name, u.role as updated_by_role
        FROM status_updates su
        LEFT JOIN users u ON su.updated_by_user_id = u.id
        WHERE su.complaint_id = ?
        ORDER BY su.created_at ASC
    ');
    $stmtStatus->execute([$complaintId]);
    $statusUpdates = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);
    
    // Get files
    $stmtFiles = $db->prepare('
        SELECT id, file_path, file_type, uploaded_at
        FROM complaint_files
        WHERE complaint_id = ?
        ORDER BY uploaded_at ASC
    ');
    $stmtFiles->execute([$complaintId]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
    
    // Get images with URLs
    $images = getComplaintImages($complaintId);
    foreach ($images as &$image) {
        $image['url'] = '/api/complaints/image.php?file=' . urlencode($image['image_path']);
    }
    unset($image);
    
    // Get assignment info
    $stmtAssignment = $db->prepare('
        SELECT sa.*, u.full_name as staff_name, u.email as staff_email
        FROM staff_assignments sa
        JOIN users u ON sa.staff_id = u.id
        WHERE sa.complaint_id = ?
        ORDER BY sa.assigned_at DESC
        LIMIT 1
    ');
    $stmtAssignment->execute([$complaintId]);
    $assignment = $stmtAssignment->fetch(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, 'Complaint details retrieved successfully', [
        'complaint' => $complaint,
        'status_updates' => $statusUpdates,
        'files' => $files,
        'images' => $images,
        'assignment' => $assignment
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get-details.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log('Error in get-details.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}

