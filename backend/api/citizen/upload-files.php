<?php
// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Get current user from JWT token
$user = getCurrentUser();
if (!$user || !isset($user['id'])) {
    sendJsonResponse(false, 'Authentication required', null, 401);
}

// Only allow citizens
if (($user['role'] ?? '') !== 'citizen') {
    sendJsonResponse(false, 'Access denied. Only citizens can upload files.', null, 403);
}

// Check if files were uploaded
// Handle FormData with files[] - PHP receives it as 'files'
if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    sendJsonResponse(false, 'No files uploaded', null, 400);
}

$files = $_FILES['files'];
$maxSize = 10 * 1024 * 1024; // 10MB per file
$allowedImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowedVideoExt = ['mp4', 'mov', 'avi'];
$maxImages = 5;
$maxVideos = 1;

// Count images and videos
$imageCount = 0;
$videoCount = 0;
$uploadedFiles = [];

// Normalize $_FILES array if single file (convert to array format)
if (!is_array($files['name'])) {
    $files = [
        'name' => [$files['name']],
        'type' => [$files['type']],
        'tmp_name' => [$files['tmp_name']],
        'error' => [$files['error']],
        'size' => [$files['size']]
    ];
}

// Process each file
$fileCount = count($files['name']);
for ($i = 0; $i < $fileCount; $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        continue; // Skip files with errors
    }
    
    $originalName = $files['name'][$i];
    $tmpName = $files['tmp_name'][$i];
    $fileSize = $files['size'][$i];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    
    // Validate file size
    if ($fileSize > $maxSize) {
        sendJsonResponse(false, "File '$originalName' exceeds maximum size of 10MB", null, 400);
    }
    
    // Validate file type
    $isImage = in_array($ext, $allowedImageExt, true);
    $isVideo = in_array($ext, $allowedVideoExt, true);
    
    if (!$isImage && !$isVideo) {
        sendJsonResponse(false, "File '$originalName' has invalid file type. Allowed: images (jpg, png, gif, webp) or videos (mp4, mov, avi)", null, 400);
    }
    
    // Count images and videos
    if ($isImage) {
        $imageCount++;
        if ($imageCount > $maxImages) {
            sendJsonResponse(false, "Maximum $maxImages images allowed", null, 400);
        }
    }
    
    if ($isVideo) {
        $videoCount++;
        if ($videoCount > $maxVideos) {
            sendJsonResponse(false, "Maximum $maxVideos video allowed", null, 400);
        }
    }
    
    // Create upload directory structure: uploads/complaints/{year}/{month}/
    $year = date('Y');
    $month = date('m');
    $baseUploadDir = __DIR__ . '/../../uploads/complaints';
    $targetDir = $baseUploadDir . '/' . $year . '/' . $month;
    
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        sendJsonResponse(false, 'Failed to create upload directory', null, 500);
    }
    
    // Generate unique filename
    $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $randomName;
    
    // Move uploaded file
    if (!move_uploaded_file($tmpName, $targetPath)) {
        sendJsonResponse(false, "Failed to upload file '$originalName'", null, 500);
    }
    
    // Store relative path from backend root
    $relativePath = 'uploads/complaints/' . $year . '/' . $month . '/' . $randomName;
    
    // Save file record to database (without complaint_id initially)
    try {
        $db = (new Database())->getConnection();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Note: Schema only has file_path and file_type, not file_name or file_size
        // Store original name in file_path or use a separate approach
        // For now, we'll store the original name as part of the path metadata
        $stmt = $db->prepare('
            INSERT INTO complaint_files (complaint_id, file_path, file_type, uploaded_at)
            VALUES (?, ?, ?, NOW())
        ');
        
        // Store original filename in a comment or use JSON in file_path
        // For simplicity, we'll use the relative path and store original name separately if needed
        $stmt->execute([
            null, // complaint_id will be set later
            $relativePath,
            $isImage ? 'image' : 'video'
        ]);
        
        $fileId = (int) $db->lastInsertId();
        
        $uploadedFiles[] = [
            'file_id' => $fileId,
            'file_path' => $relativePath,
            'file_name' => $originalName, // Store for frontend use
            'file_type' => $isImage ? 'image' : 'video',
            'file_size' => $fileSize // Store for frontend use
        ];
        
    } catch (PDOException $e) {
        error_log('Database error in upload-files.php: ' . $e->getMessage());
        // Delete uploaded file if database insert fails
        @unlink($targetPath);
        sendJsonResponse(false, 'Failed to save file record', null, 500);
    }
}

if (empty($uploadedFiles)) {
    sendJsonResponse(false, 'No files were successfully uploaded', null, 400);
}

sendJsonResponse(true, 'Files uploaded successfully', [
    'files' => $uploadedFiles,
    'count' => count($uploadedFiles)
]);

