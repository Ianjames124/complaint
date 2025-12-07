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

$staffId = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : null;
$period = isset($_GET['period']) ? trim($_GET['period']) : 'month'; // 'week', 'month', 'year', 'all'

$db = (new Database())->getConnection();

try {
    // Calculate date range based on period
    $dateCondition = '';
    $params = [];
    
    switch ($period) {
        case 'week':
            $dateCondition = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            break;
        case 'month':
            $dateCondition = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            break;
        case 'year':
            $dateCondition = 'AND c.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)';
            break;
        default:
            $dateCondition = '';
    }

    $whereClause = $staffId ? 'WHERE u.id = ?' : 'WHERE u.role = "staff" AND u.status = "active"';
    if ($staffId) {
        $params[] = $staffId;
    }

    // Get staff performance metrics
    $sql = "
        SELECT 
            u.id AS staff_id,
            u.full_name AS staff_name,
            u.email,
            u.department_id,
            d.name AS department_name,
            COUNT(DISTINCT CASE WHEN c.id IS NOT NULL THEN c.id END) AS total_assigned,
            COUNT(DISTINCT CASE WHEN c.status = 'Completed' THEN c.id END) AS completed_count,
            COUNT(DISTINCT CASE WHEN c.status = 'Closed' THEN c.id END) AS closed_count,
            COUNT(DISTINCT CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) AS pending_count,
            COUNT(DISTINCT CASE WHEN c.priority_level = 'Emergency' AND c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) AS emergency_pending,
            AVG(CASE 
                WHEN c.status = 'Completed' AND su_completed.created_at IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, c.created_at, su_completed.created_at)
                ELSE NULL
            END) AS avg_resolution_hours,
            AVG(CASE 
                WHEN c.status IN ('Assigned', 'In Progress') AND sa.assigned_at IS NOT NULL
                THEN TIMESTAMPDIFF(HOUR, sa.assigned_at, NOW())
                ELSE NULL
            END) AS avg_response_time_hours,
            COUNT(DISTINCT CASE 
                WHEN c.sla_status = 'Breached' THEN c.id 
            END) AS sla_breached_count,
            COUNT(DISTINCT CASE 
                WHEN c.sla_status IN ('On Time', 'Warning') AND c.status = 'Completed' THEN c.id 
            END) AS sla_compliant_count,
            COUNT(DISTINCT CASE 
                WHEN c.status = 'Completed' THEN c.id 
            END) AS total_completed_for_sla
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN complaints c ON c.staff_id = u.id {$dateCondition}
        LEFT JOIN status_updates su_completed ON su_completed.complaint_id = c.id 
            AND su_completed.status = 'Completed' 
            AND su_completed.id = (
                SELECT MIN(id) FROM status_updates 
                WHERE complaint_id = c.id AND status = 'Completed'
            )
        LEFT JOIN staff_assignments sa ON sa.complaint_id = c.id 
            AND sa.id = (
                SELECT MIN(id) FROM staff_assignments WHERE complaint_id = c.id
            )
        {$whereClause}
        GROUP BY u.id, u.full_name, u.email, u.department_id, d.name
        ORDER BY total_assigned DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Calculate SLA compliance percentage for each staff
    foreach ($results as &$staff) {
        $totalCompleted = (int) $staff['total_completed_for_sla'];
        $slaCompliant = (int) $staff['sla_compliant_count'];
        $staff['sla_compliance_percentage'] = $totalCompleted > 0 
            ? round(($slaCompliant / $totalCompleted) * 100, 2) 
            : 0;
        
        // Format numeric values
        $staff['avg_resolution_hours'] = $staff['avg_resolution_hours'] 
            ? round((float) $staff['avg_resolution_hours'], 2) 
            : null;
        $staff['avg_response_time_hours'] = $staff['avg_response_time_hours'] 
            ? round((float) $staff['avg_response_time_hours'], 2) 
            : null;
        
        // Convert to integers
        $staff['total_assigned'] = (int) $staff['total_assigned'];
        $staff['completed_count'] = (int) $staff['completed_count'];
        $staff['closed_count'] = (int) $staff['closed_count'];
        $staff['pending_count'] = (int) $staff['pending_count'];
        $staff['emergency_pending'] = (int) $staff['emergency_pending'];
        $staff['sla_breached_count'] = (int) $staff['sla_breached_count'];
        $staff['sla_compliant_count'] = (int) $staff['sla_compliant_count'];
    }

    if ($staffId && count($results) > 0) {
        sendJsonResponse(true, 'Staff performance data fetched', [
            'staff' => $results[0],
            'period' => $period
        ]);
    } else {
        sendJsonResponse(true, 'Staff performance data fetched', [
            'staff_list' => $results,
            'period' => $period
        ]);
    }

} catch (PDOException $e) {
    error_log('Database error in staff-performance.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to fetch staff performance data', null, 500);
} catch (Exception $e) {
    error_log('Error in staff-performance.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

