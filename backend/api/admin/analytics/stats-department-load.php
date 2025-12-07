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
    // Get department load (pending complaints per department)
    $sql = "
        SELECT 
            COALESCE(d.id, 0) as department_id,
            COALESCE(d.name, 'Unassigned') as department_name,
            COUNT(DISTINCT CASE WHEN c.status IN ('Pending', 'Assigned', 'In Progress') THEN c.id END) as pending_count,
            COUNT(DISTINCT c.id) as total_count
        FROM departments d
        LEFT JOIN complaints c ON c.department_id = d.id
            AND c.category NOT LIKE 'Request:%'
        GROUP BY d.id, d.name
        HAVING pending_count > 0 OR total_count > 0
        ORDER BY pending_count DESC
    ";

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Failed to prepare SQL statement: ' . implode(', ', $db->errorInfo()));
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for Recharts PieChart
    $chartData = [];
    $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    $colorIndex = 0;

    foreach ($results as $row) {
        $chartData[] = [
            'name' => $row['department_name'],
            'value' => (int) $row['pending_count'],
            'total' => (int) $row['total_count'],
            'color' => $colors[$colorIndex % count($colors)]
        ];
        $colorIndex++;
    }

    // If no departments, add unassigned
    if (empty($chartData)) {
        $sqlUnassigned = "
            SELECT COUNT(*) as count
            FROM complaints
            WHERE category NOT LIKE 'Request:%'
            AND (department_id IS NULL OR department_id = 0)
            AND status IN ('Pending', 'Assigned', 'In Progress')
        ";
        $stmtUnassigned = $db->prepare($sqlUnassigned);
        $stmtUnassigned->execute();
        $unassigned = $stmtUnassigned->fetch();
        
        if ($unassigned && $unassigned['count'] > 0) {
            $chartData[] = [
                'name' => 'Unassigned',
                'value' => (int) $unassigned['count'],
                'total' => (int) $unassigned['count'],
                'color' => '#9ca3af'
            ];
        }
    }

    sendJsonResponse(true, 'Department load data fetched', [
        'data' => $chartData
    ]);

} catch (PDOException $e) {
    error_log('Database error in stats-department-load.php: ' . $e->getMessage());
    error_log('SQL Error Info: ' . print_r($e->errorInfo, true));
    sendJsonResponse(false, 'Failed to fetch department load data: ' . $e->getMessage(), [
        'error_code' => 'database_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
} catch (Exception $e) {
    error_log('Error in stats-department-load.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'An error occurred: ' . $e->getMessage(), [
        'error_code' => 'server_error',
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], 500);
}

