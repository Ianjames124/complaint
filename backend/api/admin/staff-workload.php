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

$db = (new Database())->getConnection();

try {
    $whereClause = 'WHERE u.role = "staff" AND u.status = "active"';
    $params = [];
    
    if ($departmentId) {
        $whereClause .= ' AND u.department_id = ?';
        $params[] = $departmentId;
    }

    $sql = "
        SELECT 
            u.id AS staff_id,
            u.full_name AS staff_name,
            u.email,
            u.department_id,
            d.name AS department_name,
            COUNT(DISTINCT CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) AS active_cases,
            COUNT(DISTINCT CASE WHEN c.priority_level = 'Emergency' AND c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) AS emergency_cases,
            COUNT(DISTINCT CASE WHEN c.status = 'Completed' THEN c.id END) AS completed_cases,
            COUNT(DISTINCT CASE WHEN c.status = 'Closed' THEN c.id END) AS closed_cases,
            COUNT(DISTINCT CASE WHEN c.sla_status = 'Breached' THEN c.id END) AS breached_sla_count,
            COUNT(DISTINCT CASE WHEN c.sla_status = 'Warning' THEN c.id END) AS warning_sla_count,
            u.active_cases AS cached_active_cases,
            u.completed_cases AS cached_completed_cases,
            u.last_assigned_at
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN complaints c ON c.staff_id = u.id
        {$whereClause}
        GROUP BY u.id, u.full_name, u.email, u.department_id, d.name, u.active_cases, u.completed_cases, u.last_assigned_at
        ORDER BY active_cases DESC, emergency_cases DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $workloads = $stmt->fetchAll();

    // Format the data
    foreach ($workloads as &$workload) {
        $workload['active_cases'] = (int) $workload['active_cases'];
        $workload['emergency_cases'] = (int) $workload['emergency_cases'];
        $workload['completed_cases'] = (int) $workload['completed_cases'];
        $workload['closed_cases'] = (int) $workload['closed_cases'];
        $workload['breached_sla_count'] = (int) $workload['breached_sla_count'];
        $workload['warning_sla_count'] = (int) $workload['warning_sla_count'];
        $workload['cached_active_cases'] = (int) $workload['cached_active_cases'];
        $workload['cached_completed_cases'] = (int) $workload['cached_completed_cases'];
        
        // Calculate workload percentage (assuming max 50 cases per staff)
        $maxCases = 50;
        $workload['workload_percentage'] = min(100, round(($workload['active_cases'] / $maxCases) * 100, 2));
    }

    sendJsonResponse(true, 'Staff workload data fetched', [
        'workloads' => $workloads
    ]);

} catch (PDOException $e) {
    error_log('Database error in staff-workload.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to fetch staff workload data', null, 500);
} catch (Exception $e) {
    error_log('Error in staff-workload.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

