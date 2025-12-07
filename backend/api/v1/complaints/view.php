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

require_once __DIR__ . '/../../../config/DB.php';
require_once __DIR__ . '/../../../config/jwt_config.php';

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
    // Verify authentication
    $authData = requireAuth();
    
    // Get complaint ID from URL
    $complaintId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($complaintId <= 0) {
        sendResponse(false, 'Invalid complaint ID', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Build base query
    $query = "SELECT c.*, 
                     u.name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
                     s.id as staff_id, s.name as staff_name, s.email as staff_email,
                     d.name as department_name
              FROM complaints c
              JOIN users u ON c.citizen_id = u.id
              LEFT JOIN users s ON c.assigned_to = s.id
              LEFT JOIN departments d ON s.department_id = d.id
              WHERE c.id = :id";
    
    // Add access control based on user role
    if ($authData['role'] === 'citizen') {
        $query .= " AND c.citizen_id = :citizen_id";
    } elseif ($authData['role'] === 'staff') {
        $query .= " AND (c.assigned_to = :user_id OR :role = 'admin')";
    }
    
    $query .= " LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $complaintId, PDO::PARAM_INT);
    
    // Bind access control parameters
    if ($authData['role'] === 'citizen') {
        $stmt->bindParam(':citizen_id', $authData['user_id'], PDO::PARAM_INT);
    } elseif ($authData['role'] === 'staff') {
        $stmt->bindParam(':user_id', $authData['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':role', $authData['role']);
    }
    
    $stmt->execute();
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$complaint) {
        sendResponse(false, 'Complaint not found or access denied', null, 404);
    }
    
    // Get status history
    $historyStmt = $db->prepare("
        SELECT h.*, u.name as changed_by_name 
        FROM complaint_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.complaint_id = ?
        ORDER BY h.changed_at DESC
    ");
    $historyStmt->execute([$complaintId]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments if any
    $attachments = [];
    if (!empty($complaint['attachments'])) {
        $attachments = json_decode($complaint['attachments'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $attachments = [];
        }
    }
    
    // Format response
    $response = [
        'id' => $complaint['id'],
        'title' => $complaint['title'],
        'description' => $complaint['description'],
        'category' => $complaint['category'],
        'location' => $complaint['location'],
        'status' => $complaint['status'],
        'priority' => $complaint['priority'],
        'created_at' => $complaint['created_at'],
        'updated_at' => $complaint['updated_at'],
        'citizen' => [
            'id' => $complaint['citizen_id'],
            'name' => $complaint['citizen_name'],
            'email' => $complaint['citizen_email'],
            'phone' => $complaint['citizen_phone']
        ],
        'assigned_staff' => $complaint['staff_id'] ? [
            'id' => $complaint['staff_id'],
            'name' => $complaint['staff_name'],
            'email' => $complaint['staff_email'],
            'department' => $complaint['department_name']
        ] : null,
        'attachments' => $attachments,
        'status_history' => $statusHistory
    ];
    
    sendResponse(true, 'Complaint retrieved successfully', $response);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
