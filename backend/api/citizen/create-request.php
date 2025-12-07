<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

$user = requireRole(['citizen']);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    sendJsonResponse(false, 'Invalid JSON body');
}

$title       = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$category    = trim($input['category'] ?? 'General');
$location    = trim($input['location'] ?? '');
$fileIds     = $input['file_ids'] ?? [];

if ($title === '' || $description === '' || $location === '') {
    sendJsonResponse(false, 'title, description and location are required');
}

// Tag category so we can distinguish requests from complaints
$storedCategory = 'Request: ' . $category;

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    $stmt = $db->prepare(
        'INSERT INTO complaints (citizen_id, title, description, category, location, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $user['id'],
        $title,
        $description,
        $storedCategory,
        $location,
        'Pending',
    ]);

    $complaintId = (int) $db->lastInsertId();

    if (is_array($fileIds) && count($fileIds) > 0) {
        $in       = implode(',', array_fill(0, count($fileIds), '?'));
        $params   = array_merge([$complaintId], $fileIds);
        $stmtFile = $db->prepare("UPDATE complaint_files SET complaint_id = ? WHERE id IN ($in)");
        $stmtFile->execute($params);
    }

    $db->commit();

    sendJsonResponse(true, 'Request submitted successfully', [
        'request_id' => $complaintId,
    ]);
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Database error in create-request.php: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to create request', null, 500);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Error in create-request.php: ' . $e->getMessage());
    sendJsonResponse(false, 'An unexpected error occurred', null, 500);
}


