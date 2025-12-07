<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../../config/DB.php';
require_once __DIR__ . '/../../config/jwt_config.php';

// Function to send JSON response
function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

try {
    // Verify admin authentication
    $authData = requireAdmin();
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON input', null, 400);
    }
    
    // Validate required fields
    $requiredFields = ['complaint_id', 'status'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field]) && $input[$field] !== 0) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missingFields), null, 400);
    }
    
    $complaintId = (int)$input['complaint_id'];
    $status = trim($input['status']);
    $adminNotes = isset($input['admin_notes']) ? trim($input['admin_notes']) : null;
    
    // Validate status
    $validStatuses = ['pending', 'in_progress', 'resolved', 'rejected'];
    if (!in_array($status, $validStatuses)) {
        sendResponse(false, 'Invalid status. Must be one of: ' . implode(', ', $validStatuses), null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Check if complaint exists
    $stmt = $db->prepare("SELECT id FROM complaints WHERE id = ?");
    $stmt->execute([$complaintId]);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(false, 'Complaint not found', null, 404);
    }
    
    // Update complaint status
    $stmt = $db->prepare("
        UPDATE complaints 
        SET status = ?, 
            updated_at = NOW(),
            admin_notes = COALESCE(?, admin_notes)
        WHERE id = ?
    ");
    
    $stmt->execute([$status, $adminNotes, $complaintId]);
    
    // Log the status update
    $logStmt = $db->prepare("
        INSERT INTO complaint_status_history (complaint_id, status, changed_by, notes)
        VALUES (?, ?, ?, ?)
    ");
    
    $logStmt->execute([
        $complaintId,
        $status,
        $authData['user_id'] ?? null,
        $adminNotes
    ]);
    
    sendResponse(true, 'Complaint status updated successfully', [
        'complaint_id' => $complaintId,
        'new_status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ], 200);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
