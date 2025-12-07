<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';
require_once __DIR__ . '/../../utils/image_upload.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only citizen can access
$user = authorize(['citizen']);

// Get complaint ID from query parameter
$complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($complaintId <= 0) {
    sendJsonResponse(false, 'Invalid complaint ID', null, 400);
}

try {
    $db = (new Database())->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set PDO to throw exceptions
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Use RBAC access check to ensure citizen can only access their own complaints
    $userId = $user['id'] ?? null;
    if (!$userId) {
        sendJsonResponse(false, 'Invalid user data', null, 401);
    }
    
    // Check if user can access this complaint using RBAC helper
    if (!canAccessComplaint($user, $complaintId, $db)) {
        sendJsonResponse(false, 'Complaint not found or access denied', null, 403);
    }
    
    // Get complaint details
    $stmt = $db->prepare('
        SELECT c.*, u.full_name as citizen_name, u.email as citizen_email
        FROM complaints c
        JOIN users u ON c.citizen_id = u.id
        WHERE c.id = ? AND c.citizen_id = ?
    ');
    $stmt->execute([$complaintId, $userId]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$complaint) {
        sendJsonResponse(false, 'Complaint not found', null, 404);
    }
    
    // Get status updates (timeline)
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
    // Note: Schema may not have file_name or file_size, so we'll extract from path if needed
    $stmtFiles = $db->prepare('
        SELECT id, file_path, file_type, uploaded_at
        FROM complaint_files
        WHERE complaint_id = ?
        ORDER BY uploaded_at ASC
    ');
    $stmtFiles->execute([$complaintId]);
    $files = $stmtFiles->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract filename from path for each file
    foreach ($files as &$file) {
        $file['file_name'] = basename($file['file_path']);
        $file['file_size'] = null; // Not stored in DB, would need to check file system
    }
    unset($file);
    
    // Get complaint images
    $images = getComplaintImages($complaintId);
    // Format images with URLs
    foreach ($images as &$image) {
        $image['url'] = '/api/complaints/image.php?file=' . urlencode($image['image_path']);
    }
    unset($image);
    
    // Get assigned staff if any
    $stmtStaff = $db->prepare('
        SELECT sa.*, u.full_name as staff_name, u.email as staff_email
        FROM staff_assignments sa
        JOIN users u ON sa.staff_id = u.id
        WHERE sa.complaint_id = ?
        ORDER BY sa.assigned_at DESC
        LIMIT 1
    ');
    $stmtStaff->execute([$complaintId]);
    $assignment = $stmtStaff->fetch(PDO::FETCH_ASSOC);
    
    sendJsonResponse(true, 'Complaint details retrieved successfully', [
        'complaint' => $complaint,
        'status_updates' => $statusUpdates,
        'files' => $files,
        'images' => $images,
        'assignment' => $assignment
    ]);
    
} catch (PDOException $e) {
    error_log('Database error in get-complaint-details.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log('Error in get-complaint-details.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}

