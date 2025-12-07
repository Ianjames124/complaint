<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only admin can access
$admin = authorize(['admin']);

$days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
$days = max(7, min(365, $days));

$db = (new Database())->getConnection();

try {
    // Get staff performance (resolved complaints count)
    // Use staff_assignments table for accurate assignment tracking
    $sql = "
        SELECT 
            u.id,
            u.full_name as staff_name,
            COUNT(DISTINCT CASE WHEN c.status IN ('Completed', 'Closed') THEN c.id END) as resolved_count,
            COUNT(DISTINCT CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) as active_count,
            COUNT(DISTINCT c.id) as total_assigned
        FROM users u
        LEFT JOIN staff_assignments sa ON sa.staff_id = u.id
        LEFT JOIN complaints c ON c.id = sa.complaint_id
            AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        WHERE u.role = 'staff' 
        AND u.status = 'active'
        GROUP BY u.id, u.full_name
        HAVING total_assigned > 0
        ORDER BY resolved_count DESC, active_count ASC
        LIMIT 15
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement: ' . implode(', ', $db->errorInfo()));
    }
    
    $stmt->execute([$days]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for Recharts
    $chartData = [];
    foreach ($results as $row) {
        $chartData[] = [
            'name' => $row['staff_name'],
            'value' => (int) $row['resolved_count'],
            'active' => (int) $row['active_count'],
            'total' => (int) $row['total_assigned']
        ];
    }

    sendJsonResponse(true, 'Staff performance data fetched', [
        'data' => $chartData,
        'days' => $days
    ]);

} catch (PDOException $e) {
    error_log('Database error in stats-staff-performance.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    sendJsonResponse(false, 'Failed to fetch staff performance data: ' . $e->getMessage(), [
        'error_code' => 'database_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log('Error in stats-staff-performance.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'error_code' => 'server_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

