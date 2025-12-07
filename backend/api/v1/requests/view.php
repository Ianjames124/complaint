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
    
    // Get request ID from URL
    $requestId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($requestId <= 0) {
        sendResponse(false, 'Invalid request ID', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Build base query
    $query = "SELECT r.*, 
                     u.name as citizen_name, u.email as citizen_email, u.phone as citizen_phone,
                     s.id as staff_id, s.name as staff_name, s.email as staff_email,
                     d.name as department_name
              FROM requests r
              JOIN users u ON r.citizen_id = u.id
              LEFT JOIN users s ON r.assigned_to = s.id
              LEFT JOIN departments d ON r.department_id = d.id
              WHERE r.id = :id";
    
    // Add access control based on user role
    if ($authData['role'] === 'citizen') {
        $query .= " AND r.citizen_id = :citizen_id";
    } elseif ($authData['role'] === 'staff') {
        $query .= " AND (r.assigned_to = :user_id OR :role = 'admin' OR r.department_id = :department_id)";
    }
    
    $query .= " LIMIT 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $requestId, PDO::PARAM_INT);
    
    // Bind access control parameters
    if ($authData['role'] === 'citizen') {
        $stmt->bindParam(':citizen_id', $authData['user_id'], PDO::PARAM_INT);
    } elseif ($authData['role'] === 'staff') {
        $stmt->bindParam(':user_id', $authData['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':role', $authData['role']);
        $stmt->bindValue(':department_id', $authData['department_id'] ?? null, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        sendResponse(false, 'Request not found or access denied', null, 404);
    }
    
    // Get status history
    $historyStmt = $db->prepare("
        SELECT h.*, u.name as changed_by_name 
        FROM request_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.request_id = ?
        ORDER BY h.changed_at DESC
    ");
    $historyStmt->execute([$requestId]);
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get comments
    $commentsStmt = $db->prepare("
        SELECT c.*, u.name as user_name, u.role as user_role, u.profile_image
        FROM request_comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.request_id = ?
        ORDER BY c.created_at ASC
    ");
    $commentsStmt->execute([$requestId]);
    $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attachments if any
    $attachments = [];
    if (!empty($request['attachments'])) {
        $attachments = json_decode($request['attachments'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $attachments = [];
        }
    }
    
    // Format response
    $response = [
        'id' => $request['id'],
        'title' => $request['title'],
        'description' => $request['description'],
        'category' => $request['category'],
        'location' => $request['location'],
        'status' => $request['status'],
        'priority' => $request['priority'],
        'created_at' => $request['created_at'],
        'updated_at' => $request['updated_at'],
        'citizen' => [
            'id' => $request['citizen_id'],
            'name' => $request['citizen_name'],
            'email' => $request['citizen_email'],
            'phone' => $request['citizen_phone']
        ],
        'assigned_staff' => $request['staff_id'] ? [
            'id' => $request['staff_id'],
            'name' => $request['staff_name'],
            'email' => $request['staff_email'],
            'department' => $request['department_name']
        ] : null,
        'attachments' => $attachments,
        'status_history' => $statusHistory,
        'comments' => $comments
    ];
    
    sendResponse(true, 'Request retrieved successfully', $response);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
