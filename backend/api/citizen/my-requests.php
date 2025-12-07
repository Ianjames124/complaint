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

// Use RBAC authorize function - only citizen can access
$user = authorize(['citizen']);

$db = (new Database())->getConnection();

// Fetch only requests (category prefixed with "Request:")
$stmt = $db->prepare(
    "SELECT c.*
     FROM complaints c
     WHERE c.citizen_id = ? AND c.category LIKE 'Request:%'
     ORDER BY c.created_at DESC"
);
$stmt->execute([$user['id']]);
$requests = $stmt->fetchAll();

foreach ($requests as &$request) {
    $complaintId = (int) $request['id'];

    $stmtStatus = $db->prepare(
        'SELECT status, notes, created_at, role
         FROM status_updates
         WHERE complaint_id = ?
         ORDER BY created_at DESC
         LIMIT 1'
    );
    $stmtStatus->execute([$complaintId]);
    $request['latest_status_update'] = $stmtStatus->fetch() ?: null;

    $stmtFiles = $db->prepare(
        'SELECT id, file_path, file_type, uploaded_at
         FROM complaint_files
         WHERE complaint_id = ?'
    );
    $stmtFiles->execute([$complaintId]);
    $request['files'] = $stmtFiles->fetchAll();
}
unset($request);

sendJsonResponse(true, 'My requests fetched', [
    'requests' => $requests,
]);


