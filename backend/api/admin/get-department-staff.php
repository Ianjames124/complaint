<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$admin = requireAdmin();

$departmentId = isset($_GET['department_id']) ? (int) $_GET['department_id'] : null;
$includeAll = isset($_GET['include_all']) ? filter_var($_GET['include_all'], FILTER_VALIDATE_BOOLEAN) : false;

$db = (new Database())->getConnection();

try {
    $whereClause = 'WHERE u.role = "staff" AND u.status = "active"';
    $params = [];
    
    if ($departmentId && !$includeAll) {
        $whereClause .= ' AND u.department_id = ?';
        $params[] = $departmentId;
    }

    $sql = "
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.department_id,
            d.name AS department_name,
            COUNT(DISTINCT CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) AS active_cases,
            COUNT(DISTINCT CASE WHEN c.status = 'Completed' THEN c.id END) AS completed_cases,
            u.last_assigned_at
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN complaints c ON c.staff_id = u.id
        {$whereClause}
        GROUP BY u.id, u.full_name, u.email, u.department_id, d.name, u.last_assigned_at
        ORDER BY d.name, u.full_name
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $staff = $stmt->fetchAll();

    // Format the data
    foreach ($staff as &$member) {
        $member['id'] = (int) $member['id'];
        $member['department_id'] = $member['department_id'] ? (int) $member['department_id'] : null;
        $member['active_cases'] = (int) $member['active_cases'];
        $member['completed_cases'] = (int) $member['completed_cases'];
    }

    sendJsonResponse(true, 'Department staff fetched', [
        'staff' => $staff,
        'department_id' => $departmentId,
        'include_all' => $includeAll
    ]);

} catch (PDOException $e) {
    error_log('Database error in get-department-staff.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to fetch department staff', null, 500);
} catch (Exception $e) {
    error_log('Error in get-department-staff.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

