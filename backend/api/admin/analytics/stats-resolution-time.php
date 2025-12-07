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
    // Get average resolution time by category
    $sql = "
        SELECT 
            c.category,
            AVG(TIMESTAMPDIFF(HOUR, c.created_at, su.created_at)) as avg_hours,
            COUNT(*) as count
        FROM complaints c
        INNER JOIN status_updates su ON su.complaint_id = c.id
        WHERE c.category NOT LIKE 'Request:%'
        AND c.status IN ('Completed', 'Closed')
        AND su.status IN ('Completed', 'Closed')
        AND su.id = (
            SELECT MIN(id) 
            FROM status_updates 
            WHERE complaint_id = c.id 
            AND status IN ('Completed', 'Closed')
        )
        AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY c.category
        ORDER BY avg_hours DESC
        LIMIT 10
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
            'name' => $row['category'],
            'value' => round((float) $row['avg_hours'], 1),
            'count' => (int) $row['count']
        ];
    }

    sendJsonResponse(true, 'Resolution time data fetched', [
        'data' => $chartData,
        'days' => $days
    ]);

} catch (PDOException $e) {
    error_log('Database error in stats-resolution-time.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    sendJsonResponse(false, 'Failed to fetch resolution time data: ' . $e->getMessage(), [
        'error_code' => 'database_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log('Error in stats-resolution-time.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'error_code' => 'server_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

