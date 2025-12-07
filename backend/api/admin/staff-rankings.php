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

$sortBy = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'performance'; // 'performance', 'completion', 'sla', 'response_time'
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;

$db = (new Database())->getConnection();

try {
    $orderBy = 'total_completed DESC';
    
    switch ($sortBy) {
        case 'completion':
            $orderBy = 'total_completed DESC, sla_compliance_percentage DESC';
            break;
        case 'sla':
            $orderBy = 'sla_compliance_percentage DESC, total_completed DESC';
            break;
        case 'response_time':
            $orderBy = 'avg_response_time_hours ASC, total_completed DESC';
            break;
        case 'performance':
        default:
            $orderBy = '(sla_compliance_percentage * 0.4 + (total_completed / GREATEST(1, total_assigned)) * 60) DESC';
            break;
    }

    $sql = "
        SELECT 
            u.id AS staff_id,
            u.full_name AS staff_name,
            u.email,
            u.department_id,
            d.name AS department_name,
            COUNT(DISTINCT c.id) AS total_assigned,
            COUNT(DISTINCT CASE WHEN c.status = 'Completed' THEN c.id END) AS total_completed,
            COUNT(DISTINCT CASE WHEN c.status = 'Closed' THEN c.id END) AS total_closed,
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
                WHEN c.sla_status IN ('On Time', 'Warning') AND c.status = 'Completed' THEN c.id 
            END) AS sla_compliant_count,
            COUNT(DISTINCT CASE 
                WHEN c.status = 'Completed' THEN c.id 
            END) AS total_completed_for_sla
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN complaints c ON c.staff_id = u.id
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
        WHERE u.role = 'staff' AND u.status = 'active'
        GROUP BY u.id, u.full_name, u.email, u.department_id, d.name
        HAVING total_assigned > 0
        ORDER BY {$orderBy}
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$limit]);
    $rankings = $stmt->fetchAll();

    // Calculate metrics and add ranking
    $rank = 1;
    foreach ($rankings as &$staff) {
        $totalCompleted = (int) $staff['total_completed_for_sla'];
        $slaCompliant = (int) $staff['sla_compliant_count'];
        $staff['sla_compliance_percentage'] = $totalCompleted > 0 
            ? round(($slaCompliant / $totalCompleted) * 100, 2) 
            : 0;
        
        $staff['completion_rate'] = $staff['total_assigned'] > 0 
            ? round(($staff['total_completed'] / $staff['total_assigned']) * 100, 2) 
            : 0;
        
        $staff['avg_resolution_hours'] = $staff['avg_resolution_hours'] 
            ? round((float) $staff['avg_resolution_hours'], 2) 
            : null;
        $staff['avg_response_time_hours'] = $staff['avg_response_time_hours'] 
            ? round((float) $staff['avg_response_time_hours'], 2) 
            : null;
        
        $staff['rank'] = $rank++;
        $staff['total_assigned'] = (int) $staff['total_assigned'];
        $staff['total_completed'] = (int) $staff['total_completed'];
        $staff['total_closed'] = (int) $staff['total_closed'];
    }

    sendJsonResponse(true, 'Staff rankings fetched', [
        'rankings' => $rankings,
        'sort_by' => $sortBy,
        'limit' => $limit
    ]);

} catch (PDOException $e) {
    error_log('Database error in staff-rankings.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to fetch staff rankings', null, 500);
} catch (Exception $e) {
    error_log('Error in staff-rankings.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An error occurred', null, 500);
}

