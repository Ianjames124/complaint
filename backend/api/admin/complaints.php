<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/pagination.php';
require_once __DIR__ . '/../../utils/image_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only admin can access
$admin = authorize(['admin']);

$status      = isset($_GET['status']) ? trim($_GET['status']) : null;
$department  = isset($_GET['department_id']) ? (int) $_GET['department_id'] : null;
$dateFrom    = isset($_GET['date_from']) ? trim($_GET['date_from']) : null;
$dateTo      = isset($_GET['date_to']) ? trim($_GET['date_to']) : null;

[$limit, $offset, $page, $perPage] = getPaginationParams();

$db = (new Database())->getConnection();

$where  = [];
$params = [];

// Only show complaints (not requests - requests have category starting with "Request:")
$where[] = "c.category NOT LIKE 'Request:%'";
$params = [];

if ($status) {
    $where[]   = 'c.status = ?';
    $params[]  = $status;
}

if ($department) {
    $where[]  = 'c.department_id = ?';
    $params[] = $department;
}

if ($dateFrom) {
    $where[]  = 'c.created_at >= ?';
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo) {
    $where[]  = 'c.created_at <= ?';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$countSql = "SELECT COUNT(*) as total
             FROM complaints c
             JOIN users u ON c.citizen_id = u.id
             $whereSql";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$total = (int) ($stmtCount->fetch()['total'] ?? 0);

// Get complaints with pagination
$sql = "SELECT c.*, u.full_name as citizen_name, u.email as citizen_email
        FROM complaints c
        JOIN users u ON c.citizen_id = u.id
        $whereSql
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Add images to each complaint
foreach ($complaints as &$complaint) {
    $images = getComplaintImages($complaint['id']);
    foreach ($images as &$image) {
        $image['url'] = '/api/complaints/image.php?file=' . urlencode($image['image_path']);
    }
    $complaint['images'] = $images;
}
unset($complaint, $image);

sendJsonResponse(true, 'Complaints fetched', [
    'complaints' => $complaints,
    'pagination' => [
        'page'      => $page,
        'per_page'  => $perPage,
        'total'     => $total,
        'total_pages' => $perPage > 0 ? ceil($total / $perPage) : 0,
    ],
]);
