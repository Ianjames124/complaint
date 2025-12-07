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

$days = isset($_GET['days']) ? (int) $_GET['days'] : 30; // Default to last 30 days
$days = max(7, min(365, $days)); // Limit between 7 and 365 days

$db = (new Database())->getConnection();

try {
    // Get daily complaint counts
    $sql = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM complaints
        WHERE category NOT LIKE 'Request:%'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
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
        $date = new DateTime($row['date']);
        $chartData[] = [
            'name' => $date->format('M d'),
            'value' => (int) $row['count'],
            'date' => $row['date']
        ];
    }

    sendJsonResponse(true, 'Daily complaints data fetched', [
        'data' => $chartData,
        'days' => $days,
        'total' => array_sum(array_column($chartData, 'value'))
    ]);

} catch (PDOException $e) {
    error_log('Database error in stats-daily-complaints.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    sendJsonResponse(false, 'Failed to fetch daily complaints data: ' . $e->getMessage(), [
        'error_code' => 'database_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log('Error in stats-daily-complaints.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'error_code' => 'server_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

