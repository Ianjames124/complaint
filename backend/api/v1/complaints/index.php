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
    
    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $assignedToMe = isset($_GET['assigned_to_me']) && $authData['role'] === 'staff';
    $myComplaints = isset($_GET['my_complaints']) && $authData['role'] === 'citizen';
    
    // Validate pagination
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Build base query
    $query = "SELECT c.*, u.name as citizen_name, u.email as citizen_email, 
                     s.name as staff_name, d.name as department_name
              FROM complaints c
              JOIN users u ON c.citizen_id = u.id
              LEFT JOIN users s ON c.assigned_to = s.id
              LEFT JOIN departments d ON s.department_id = d.id
              WHERE 1=1";
    
    $params = [];
    $where = [];
    
    // Apply filters based on user role
    if ($authData['role'] === 'citizen') {
        $where[] = "c.citizen_id = :citizen_id";
        $params[':citizen_id'] = $authData['user_id'];
    } elseif ($assignedToMe) {
        $where[] = "c.assigned_to = :staff_id";
        $params[':staff_id'] = $authData['user_id'];
    }
    
    // Apply status filter
    if (!empty($status)) {
        $where[] = "c.status = :status";
        $params[':status'] = $status;
    }
    
    // Apply category filter
    if (!empty($category)) {
        $where[] = "c.category = :category";
        $params[':category'] = $category;
    }
    
    // Apply search
    if (!empty($search)) {
        $where[] = "(c.title LIKE :search OR c.description LIKE :search OR u.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Add WHERE conditions
    if (!empty($where)) {
        $query .= " AND " . implode(" AND ", $where);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM (" . 
                 str_replace(
                     "c.*, u.name as citizen_name, u.email as citizen_email, s.name as staff_name, d.name as department_name", 
                     "COUNT(*)", 
                     $query
                 ) . 
                 ") as count";
    
    $countStmt = $db->prepare($countQuery);
    
    // Bind parameters for count query
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    
    $countStmt->execute();
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add sorting and pagination
    $query .= " ORDER BY c.created_at DESC LIMIT :offset, :per_page";
    
    // Execute query
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for filter
    $categories = [];
    if ($authData['role'] === 'admin' || $authData['role'] === 'staff') {
        $catStmt = $db->query("SELECT DISTINCT category FROM complaints WHERE category IS NOT NULL AND category != ''");
        $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Calculate pagination metadata
    $totalPages = ceil($total / $perPage);
    
    sendResponse(true, 'Complaints retrieved successfully', [
        'data' => $complaints,
        'meta' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ],
        'filters' => [
            'categories' => $categories,
            'statuses' => ['pending', 'in_progress', 'resolved', 'rejected']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
