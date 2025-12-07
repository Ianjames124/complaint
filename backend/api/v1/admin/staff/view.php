<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../../config/DB.php';
require_once __DIR__ . '/../../../../config/jwt_config.php';

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
    requireAdmin();
    
    // Get staff ID from URL
    $staffId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($staffId <= 0) {
        sendResponse(false, 'Invalid staff ID', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Get staff details
    $query = "SELECT u.id, u.name, u.email, u.role, u.status, u.department_id, 
                     u.created_at, u.updated_at, d.name as department_name
              FROM users u
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE u.id = :id AND u.role IN ('admin', 'staff')
              LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $staffId, PDO::PARAM_INT);
    $stmt->execute();
    
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        sendResponse(false, 'Staff member not found', null, 404);
    }
    
    // Get staff's assigned complaints count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM complaints WHERE assigned_to = ?");
    $stmt->execute([$staffId]);
    $complaintsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get staff's completed requests count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE assigned_to = ? AND status = 'completed'");
    $stmt->execute([$staffId]);
    $completedRequests = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Add stats to response
    $staff['stats'] = [
        'total_complaints' => (int)$complaintsCount,
        'completed_requests' => (int)$completedRequests
    ];
    
    sendResponse(true, 'Staff details retrieved successfully', $staff);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
