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
    
    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
    $status = isset($_GET['status']) ? $_GET['status'] : 'active';
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Validate pagination
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage)); // Limit to 100 items per page
    $offset = ($page - 1) * $perPage;
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Build base query
    $query = "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at, 
                     d.name as department_name
              FROM users u
              LEFT JOIN departments d ON u.department_id = d.id
              WHERE u.role IN ('admin', 'staff')";
    
    $params = [];
    $where = [];
    
    // Apply filters
    if ($status === 'active' || $status === 'inactive') {
        $where[] = "u.status = :status";
        $params[':status'] = $status;
    }
    
    if (!empty($search)) {
        $where[] = "(u.name LIKE :search OR u.email LIKE :search OR d.name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($where)) {
        $query .= " AND " . implode(" AND ", $where);
    }
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total FROM (" . str_replace("u.id, u.name, u.email, u.role, u.status, u.created_at, d.name as department_name", "COUNT(*)", $query) . ") as count";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add sorting and pagination
    $query .= " ORDER BY u.created_at DESC LIMIT :offset, :per_page";
    
    // Execute query
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per_page', $perPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination metadata
    $totalPages = ceil($total / $perPage);
    
    sendResponse(true, 'Staff retrieved successfully', [
        'data' => $staff,
        'pagination' => [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
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
