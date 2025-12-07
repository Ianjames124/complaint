<?php
// Error reporting for development - will be overridden by production settings
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load required files
try {
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';
} catch (Exception $e) {
    // If there's an error loading required files
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server configuration error',
        'error' => $e->getMessage()
    ]);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

try {
    // Use RBAC authorize function - only citizen can access
    $user = authorize(['citizen']);
    
    // Initialize database connection
    $db = (new Database())->getConnection();

// Fetch complaints
$stmt = $db->prepare(
    'SELECT c.*
     FROM complaints c
     WHERE c.citizen_id = ?
     ORDER BY c.created_at DESC'
);
$stmt->execute([$user['id']]);
$complaints = $stmt->fetchAll();

// Attach latest status update and files
foreach ($complaints as &$complaint) {
    $complaintId = (int) $complaint['id'];

    // latest status update
    $stmtStatus = $db->prepare(
        'SELECT status, notes, created_at, role
         FROM status_updates
         WHERE complaint_id = ?
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmtStatus->execute([$complaintId]);
    $complaint['latest_status_update'] = $stmtStatus->fetch() ?: null;

    // files
    $stmtFiles = $db->prepare(
        'SELECT id, file_path, file_type, uploaded_at
         FROM complaint_files
         WHERE complaint_id = ?'
    );
    $stmtFiles->execute([$complaintId]);
    $complaint['files'] = $stmtFiles->fetchAll();
}
unset($complaint);

    // Send successful response
    sendJsonResponse(true, 'My complaints fetched', [
        'complaints' => $complaints,
    ]);
} catch (Exception $e) {
    // Log the error for debugging
    error_log('Error in my-complaints.php: ' . $e->getMessage());
    
    // Send error response
    sendJsonResponse(false, 'An error occurred while fetching complaints', [
        'error' => $e->getMessage()
    ], 500);
}


