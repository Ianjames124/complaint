<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
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

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

try {
    // Verify admin authentication
    $authData = requireAdmin();
    
    // Get staff ID from URL
    $staffId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($staffId <= 0) {
        sendResponse(false, 'Invalid staff ID', null, 400);
    }
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Invalid JSON input', null, 400);
    }
    
    // Validate required fields
    $updates = [];
    $params = [':id' => $staffId];
    
    // Validate and prepare updates
    if (isset($input['name'])) {
        $name = trim($input['name']);
        if (empty($name)) {
            sendResponse(false, 'Name cannot be empty', null, 400);
        }
        $updates[] = 'name = :name';
        $params[':name'] = $name;
    }
    
    if (isset($input['email'])) {
        $email = strtolower(trim($input['email']));
        if (!isValidEmail($email)) {
            sendResponse(false, 'Invalid email format', null, 400);
        }
        $updates[] = 'email = :email';
        $params[':email'] = $email;
    }
    
    if (isset($input['department_id'])) {
        $departmentId = !empty($input['department_id']) ? (int)$input['department_id'] : null;
        if ($departmentId !== null && $departmentId <= 0) {
            sendResponse(false, 'Invalid department ID', null, 400);
        }
        $updates[] = 'department_id = :department_id';
        $params[':department_id'] = $departmentId;
    }
    
    if (isset($input['status']) && in_array($input['status'], ['active', 'inactive'])) {
        $updates[] = 'status = :status';
        $params[':status'] = $input['status'];
    }
    
    if (empty($updates)) {
        sendResponse(false, 'No valid fields to update', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Check if staff exists and is not the current user
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) {
        sendResponse(false, 'Staff member not found', null, 404);
    }
    
    // Prevent modifying your own admin status
    if ($staff['id'] == $authData['user_id'] && isset($input['role']) && $input['role'] !== 'admin') {
        sendResponse(false, 'You cannot change your own admin role', null, 403);
    }
    
    // Check for duplicate email
    if (isset($input['email'])) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmt->execute([$email, $staffId]);
        if ($stmt->rowCount() > 0) {
            sendResponse(false, 'Email already in use by another account', null, 409);
        }
    }
    
    // Build and execute update query
    $query = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    if ($stmt->execute()) {
        // Get updated staff data
        $stmt = $db->prepare("
            SELECT u.id, u.name, u.email, u.role, u.status, u.department_id, 
                   u.created_at, u.updated_at, d.name as department_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$staffId]);
        $updatedStaff = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, 'Staff member updated successfully', $updatedStaff);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log('Database error: ' . print_r($errorInfo, true));
        sendResponse(false, 'Failed to update staff member', null, 500);
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
