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

// Any authenticated user can upload
$user = requireAuth();

if (!isset($_FILES['file'])) {
    sendJsonResponse(false, 'No file uploaded');
}

$complaintId = isset($_POST['complaint_id']) ? (int) $_POST['complaint_id'] : null;

$file      = $_FILES['file'];
$maxSize   = 5 * 1024 * 1024; // 5MB
$allowedExt = ['jpg', 'jpeg', 'png', 'pdf', 'mp4'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendJsonResponse(false, 'File upload error');
}

if ($file['size'] > $maxSize) {
    sendJsonResponse(false, 'File exceeds maximum size of 5MB');
}

$originalName = $file['name'];
$ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!in_array($ext, $allowedExt, true)) {
    sendJsonResponse(false, 'Invalid file type');
}

$year  = date('Y');
$month = date('m');

$baseUploadDir = __DIR__ . '/../../uploads';
$targetDir     = $baseUploadDir . '/' . $year . '/' . $month;

if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
    sendJsonResponse(false, 'Failed to create upload directory');
}

$randomName = bin2hex(random_bytes(16)) . '.' . $ext;
$targetPath = $targetDir . '/' . $randomName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    sendJsonResponse(false, 'Failed to move uploaded file');
}

// Store relative path from backend root
$relativePath = 'uploads/' . $year . '/' . $month . '/' . $randomName;

$db = (new Database())->getConnection();

$stmt = $db->prepare(
    'INSERT INTO complaint_files (complaint_id, file_path, file_type, uploaded_at)
     VALUES (?, ?, ?, NOW())'
);

$complaintIdValue = $complaintId ?: null;

try {
    $stmt->execute([
        $complaintIdValue,
        $relativePath,
        $ext,
    ]);
    $fileId = (int) $db->lastInsertId();

    sendJsonResponse(true, 'File uploaded successfully', [
        'file_id'    => $fileId,
        'file_path'  => $relativePath,
        'file_type'  => $ext,
        'complaint_id' => $complaintIdValue,
    ]);
} catch (PDOException $e) {
    sendJsonResponse(false, 'Failed to save file record');
}


