<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';
require_once __DIR__ . '/../../utils/pagination.php';

header('Content-Type: application/json');

// Use sendJsonResponse from auth.php (already loaded)

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJsonResponse(false, 'Method not allowed', null, 405);
    }

    // Use RBAC authorize function - only admin can access
    $currentUser = authorize(['admin']);

    // Get pagination parameters
    [$limit, $offset, $page, $perPage] = getPaginationParams();
    
    // Initialize database connection
    $db = (new Database())->getConnection();
    
    if ($db === null) {
        throw new Exception('Failed to connect to database');
    }
    
    // Set PDO to throw exceptions
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if required tables exist
    $tables = ['complaints', 'users'];
    foreach ($tables as $table) {
        $tableCheck = $db->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() === 0) {
            throw new Exception("Required table '$table' does not exist in the database.");
        }
    }

    // Get and validate query parameters
    $status = isset($_GET['status']) ? trim($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

    // Validate status if provided - FIXED: Use actual enum values from schema
    $validStatuses = ['Pending', 'Assigned', 'In Progress', 'Completed', 'Closed'];
    if ($status && !in_array($status, $validStatuses)) {
        sendJsonResponse(false, 'Invalid status value. Valid values: ' . implode(', ', $validStatuses), [
            'valid_statuses' => $validStatuses
        ], 400);
    }

    // Build WHERE clause
    $where = ["c.category LIKE 'Request:%'"];
    $params = [];

    if ($status) {
        $where[] = 'c.status = ?';
        $params[] = $status;
    }

    if ($dateFrom) {
        if (!strtotime($dateFrom)) {
            sendJsonResponse(false, 'Invalid date_from format. Use YYYY-MM-DD', null, 400);
        }
        $where[] = 'c.created_at >= ?';
        $params[] = date('Y-m-d 00:00:00', strtotime($dateFrom));
    }

    if ($dateTo) {
        if (!strtotime($dateTo)) {
            sendJsonResponse(false, 'Invalid date_to format. Use YYYY-MM-DD', null, 400);
        }
        $where[] = 'c.created_at <= ?';
        $params[] = date('Y-m-d 23:59:59', strtotime($dateTo));
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) AS total
                  FROM complaints c
                  JOIN users u ON c.citizen_id = u.id
                  $whereSql";
    
    $stmtCount = $db->prepare($countQuery);
    if (!$stmtCount) {
        throw new Exception('Failed to prepare count query: ' . implode(' ', $db->errorInfo()));
    }
    
    if (!$stmtCount->execute($params)) {
        throw new Exception('Failed to execute count query: ' . implode(' ', $stmtCount->errorInfo()));
    }
    
    $total = (int) ($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Get paginated data - FIXED: Complete SQL query
    $query = "SELECT c.*, u.full_name AS citizen_name, u.email AS citizen_email
             FROM complaints c
             JOIN users u ON c.citizen_id = u.id
             $whereSql
             ORDER BY c.created_at DESC
             LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . implode(' ', $db->errorInfo()));
    }
    
    // Add pagination parameters
    $paginationParams = array_merge($params, [(int)$limit, (int)$offset]);
    
    if (!$stmt->execute($paginationParams)) {
        throw new Exception('Failed to execute query: ' . implode(' ', $stmt->errorInfo()));
    }
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($requests === false) {
        $requests = []; // Return empty array if no requests found
    }

    sendJsonResponse(true, 'Requests fetched successfully', [
        'requests' => $requests,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $perPage > 0 ? (int)ceil($total / $perPage) : 0,
        ],
    ]);

} catch (PDOException $e) {
    error_log('Database error in requests.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Database error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
    
} catch (Exception $e) {
    error_log('Error in requests.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}


