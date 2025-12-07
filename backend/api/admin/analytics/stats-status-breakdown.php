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

$db = (new Database())->getConnection();

try {
    // Get status breakdown
    $sql = "
        SELECT 
            status,
            COUNT(*) as count
        FROM complaints
        WHERE category NOT LIKE 'Request:%'
        GROUP BY status
        ORDER BY 
            CASE status
                WHEN 'Pending' THEN 1
                WHEN 'Assigned' THEN 2
                WHEN 'In Progress' THEN 3
                WHEN 'Completed' THEN 4
                WHEN 'Closed' THEN 5
                ELSE 6
            END
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement: ' . implode(', ', $db->errorInfo()));
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for Recharts PieChart
    $chartData = [];
    $statusColors = [
        'Pending' => '#f59e0b',
        'Assigned' => '#3b82f6',
        'In Progress' => '#8b5cf6',
        'Completed' => '#10b981',
        'Closed' => '#6b7280',
        'Rejected' => '#ef4444'
    ];

    foreach ($results as $row) {
        $status = $row['status'];
        $chartData[] = [
            'name' => $status,
            'value' => (int) $row['count'],
            'color' => $statusColors[$status] ?? '#9ca3af'
        ];
    }

    sendJsonResponse(true, 'Status breakdown data fetched', [
        'data' => $chartData,
        'total' => array_sum(array_column($chartData, 'value'))
    ]);

} catch (PDOException $e) {
    error_log('Database error in stats-status-breakdown.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    sendJsonResponse(false, 'Failed to fetch status breakdown data: ' . $e->getMessage(), [
        'error_code' => 'database_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log('Error in stats-status-breakdown.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'error_code' => 'server_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

