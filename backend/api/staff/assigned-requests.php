<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../middleware/rbac_filter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only staff can access
$staff = authorize(['staff']);

$db = (new Database())->getConnection();

$staffId = $staff['id'] ?? null;
if (!$staffId) {
    sendJsonResponse(false, 'Invalid user data', null, 401);
}

$stmt = $db->prepare(
    "SELECT c.*, sa.assigned_at, u.full_name AS citizen_name, u.email AS citizen_email
     FROM staff_assignments sa
     JOIN complaints c ON sa.complaint_id = c.id
     JOIN users u ON c.citizen_id = u.id
     WHERE sa.staff_id = ? AND c.category LIKE 'Request:%'
     ORDER BY sa.assigned_at DESC"
);
$stmt->execute([$staffId]);
$requests = $stmt->fetchAll();

sendJsonResponse(true, 'Assigned requests fetched', [
    'requests' => $requests,
]);


