<?php
/**
 * Secure Image Serving Endpoint
 * Serves complaint images with RBAC validation
 */

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../middleware/auth.php';
require_once __DIR__ . '/../../utils/helpers.php';

handleCors();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get file parameter
$filePath = $_GET['file'] ?? '';

if (empty($filePath)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'File parameter is required']);
    exit;
}

// Sanitize file path to prevent directory traversal
$filePath = str_replace(['../', '..\\', '/', '\\'], '', $filePath);
$filePath = preg_replace('/[^a-zA-Z0-9\/_\-\.]/', '', $filePath);

// Get authenticated user
$user = null;
try {
    $token = get_bearer_token();
    if ($token) {
        $payload = verify_jwt($token);
        if ($payload) {
            $user = $payload['user'] ?? $payload;
        }
    }
} catch (Exception $e) {
    error_log('Auth error in image.php: ' . $e->getMessage());
}

if (!$user || !isset($user['id']) || !isset($user['role'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    
    // Get image record
    $stmt = $db->prepare('
        SELECT ci.*, c.citizen_id, c.staff_id, c.status
        FROM complaint_images ci
        JOIN complaints c ON ci.complaint_id = c.id
        WHERE ci.image_path = ?
        LIMIT 1
    ');
    $stmt->execute([$filePath]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$image) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Image not found']);
        exit;
    }
    
    // RBAC: Check if user has permission to view this image
    $hasAccess = false;
    
    if ($user['role'] === 'admin') {
        // Admin can see all images
        $hasAccess = true;
    } elseif ($user['role'] === 'staff') {
        // Staff can see images from assigned complaints
        $hasAccess = ($image['staff_id'] == $user['id']);
    } elseif ($user['role'] === 'citizen') {
        // Citizen can see only their own complaint images
        $hasAccess = ($image['citizen_id'] == $user['id']);
    }
    
    if (!$hasAccess) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden: You do not have permission to view this image']);
        exit;
    }
    
    // Get full file path - handle both relative and absolute paths
    if (strpos($image['image_path'], '/') === 0) {
        // Absolute path
        $fullPath = __DIR__ . '/../../uploads' . $image['image_path'];
    } else {
        // Relative path
        $fullPath = __DIR__ . '/../../uploads/' . $image['image_path'];
    }
    
    if (!file_exists($fullPath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Image file not found']);
        exit;
    }
    
    // Set appropriate headers
    header('Content-Type: ' . $image['mime_type']);
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=3600');
    header('Content-Disposition: inline; filename="' . htmlspecialchars($image['file_name']) . '"');
    
    // Output image
    readfile($fullPath);
    exit;
    
} catch (Exception $e) {
    error_log('Error serving image: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error serving image']);
    exit;
}

