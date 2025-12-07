<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/email.php';

header('Content-Type: application/json');

// Use sendJsonResponse from auth.php (already loaded)

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Method not allowed', null, 405);
    }

    // Check admin privileges
    try {
        $currentUser = requireAdmin();
        
        if (!is_array($currentUser) || ($currentUser['role'] ?? '') !== 'admin') {
            error_log('Access denied: User is not an admin');
            sendJsonResponse(false, 'Unauthorized: Admin access required', null, 403);
        }
    } catch (Exception $e) {
        error_log('Error in requireAdmin: ' . $e->getMessage());
        sendJsonResponse(false, 'Authentication error: ' . $e->getMessage(), null, 403);
    }
    
    // Get and validate input data
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        sendJsonResponse(false, 'Request body is empty', null, 400);
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(false, 'Invalid JSON input: ' . json_last_error_msg(), null, 400);
    }
    
    // Log the received input for debugging
    error_log('Received input: ' . print_r($input, true));
    
    // Extract and validate required fields
    $fullName = trim($input['full_name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $password = $input['password'] ?? '';
    $departmentId = null;
    
    // Validate department_id if provided
    if (isset($input['department_id']) && $input['department_id'] !== '' && $input['department_id'] !== null) {
        if (!is_numeric($input['department_id'])) {
            sendJsonResponse(false, 'Department ID must be a number', null, 400);
        }
        $departmentId = (int)$input['department_id'];
        if ($departmentId <= 0) {
            $departmentId = null; // Treat 0 or negative as null
        }
    }
    
    // Validate required fields with specific error messages
    $errors = [];
    
    if (empty($fullName)) {
        $errors[] = 'Full name is required';
    } elseif (strlen($fullName) < 2) {
        $errors[] = 'Full name must be at least 2 characters long';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Generate a random password if none provided
    if (empty($password)) {
        $password = bin2hex(random_bytes(8)); // Generates a 16-character random string
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    // If there are validation errors, return them
    if (!empty($errors)) {
        sendJsonResponse(false, 'Validation failed', ['errors' => $errors], 400);
    }
    
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set PDO to throw exceptions
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if users table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'users'");
    if ($tableCheck->rowCount() === 0) {
        throw new Exception('The users table does not exist in the database.');
    }
    
    // Check if department exists if provided
    if ($departmentId) {
        $deptCheck = $db->prepare('SELECT id FROM departments WHERE id = ? LIMIT 1');
        $deptCheck->execute([$departmentId]);
        if ($deptCheck->rowCount() === 0) {
            sendJsonResponse(false, 'Invalid department ID', null, 400);
        }
    }
    
    // Check if email already exists
    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":email", $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        sendJsonResponse(false, 'Email already exists', null, 400);
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert new staff member
    $query = "INSERT INTO users (full_name, email, password_hash, role, status, department_id, created_at) 
              VALUES (:full_name, :email, :password_hash, 'staff', 'active', :department_id, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":full_name", $fullName);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":password_hash", $hashedPassword);
    $stmt->bindParam(":department_id", $departmentId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $staffId = $db->lastInsertId();
        
        sendJsonResponse(true, 'Staff created successfully', [
            'id' => $staffId,
            'full_name' => $fullName,
            'email' => $email
        ]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log('Database error: ' . print_r($errorInfo, true));
        sendJsonResponse(false, 'Failed to create staff: ' . ($errorInfo[2] ?? 'Unknown error'), null, 500);
    }
    
} catch (PDOException $e) {
    error_log('PDOException in create-staff.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error: ' . $e->getMessage(), null, 500);
    
} catch (Exception $e) {
    error_log('Error in create-staff.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), null, 500);
}


