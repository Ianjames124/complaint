<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

// Include required files
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

// Use sendJsonResponse from auth.php (already loaded)

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse(false, 'Method not allowed. Only GET is supported.', null, 405);
    }

    // Check admin privileges
    try {
        requireAdmin();
    } catch (Exception $e) {
        error_log('Admin check failed: ' . $e->getMessage());
        sendJsonResponse(false, 'Unauthorized: ' . $e->getMessage(), null, 401);
    }

    try {
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

        // Prepare and execute query
        $query = "SELECT 
                    id, 
                    full_name, 
                    email, 
                    department_id, 
                    status, 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at
                  FROM users
                  WHERE role = :role
                  ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        $role = 'staff';
        $stmt->bindParam(':role', $role, PDO::PARAM_STR);
        $stmt->execute();

        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($staff)) {
            $staff = []; // Ensure we always return an array
        }

        sendJsonResponse(true, 'Staff list retrieved successfully', [
            'staff' => $staff,
            'count' => count($staff)
        ]);

    } catch (PDOException $e) {
        error_log('Database error in staff-list.php: ' . $e->getMessage());
        sendJsonResponse(false, 'Database error occurred. Please try again later.', null, 500);
    }

} catch (Exception $e) {
    error_log('Unexpected error in staff-list.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An unexpected error occurred. Please try again later.', null, 500);
}