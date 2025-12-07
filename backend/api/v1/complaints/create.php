<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../../config/DB.php';
require_once __DIR__ . '/../../../config/jwt_config.php';

function sendResponse($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

function handleFileUploads($files, $complaintId) {
    $uploadDir = __DIR__ . "/../../../uploads/complaints/{$complaintId}/";
    $webPath = "/uploads/complaints/{$complaintId}/";
    $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $attachments = [];
    
    foreach ($files as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            continue;
        }
        
        // Validate file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            continue;
        }
        
        // Validate file size
        if ($file['size'] > $maxFileSize) {
            continue;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $attachments[] = [
                'name' => $file['name'],
                'path' => $webPath . $filename,
                'type' => $mimeType,
                'size' => $file['size'],
                'uploaded_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    return $attachments;
}

try {
    // Verify authentication (only citizens can submit complaints)
    $authData = requireAuth();
    
    if ($authData['role'] !== 'citizen') {
        sendResponse(false, 'Only citizens can submit complaints', null, 403);
    }
    
    // Check if request is multipart/form-data
    $isMultipart = strpos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') !== false;
    
    // Get input data
    if ($isMultipart) {
        $input = $_POST;
        $files = $_FILES['attachments'] ?? [];
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(false, 'Invalid JSON input', null, 400);
        }
        $files = [];
    }
    
    // Validate required fields
    $requiredFields = ['title', 'description', 'category', 'location'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        sendResponse(false, 'Missing required fields: ' . implode(', ', $missingFields), null, 400);
    }
    
    // Sanitize input
    $title = trim($input['title']);
    $description = trim($input['description']);
    $category = trim($input['category']);
    $location = trim($input['location']);
    $priority = isset($input['priority']) && in_array($input['priority'], ['low', 'medium', 'high']) 
        ? $input['priority'] 
        : 'medium';
    
    // Validate input lengths
    if (strlen($title) > 255) {
        sendResponse(false, 'Title is too long (max 255 characters)', null, 400);
    }
    
    if (strlen($description) > 5000) {
        sendResponse(false, 'Description is too long (max 5000 characters)', null, 400);
    }
    
    // Get database connection
    $db = DB::getInstance()->getConnection();
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Insert complaint
        $query = "INSERT INTO complaints 
                 (citizen_id, title, description, category, location, priority, status, created_at, updated_at)
                 VALUES (:citizen_id, :title, :description, :category, :location, :priority, 'pending', NOW(), NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':citizen_id' => $authData['user_id'],
            ':title' => $title,
            ':description' => $description,
            ':category' => $category,
            ':location' => $location,
            ':priority' => $priority
        ]);
        
        $complaintId = $db->lastInsertId();
        
        // Handle file uploads if any
        $attachments = [];
        if (!empty($files)) {
            $attachments = handleFileUploads($files, $complaintId);
            
            // Update complaint with attachments if any
            if (!empty($attachments)) {
                $updateStmt = $db->prepare("UPDATE complaints SET attachments = :attachments WHERE id = :id");
                $updateStmt->execute([
                    ':attachments' => json_encode($attachments),
                    ':id' => $complaintId
                ]);
            }
        }
        
        // Add to status history
        $historyStmt = $db->prepare("
            INSERT INTO complaint_status_history 
            (complaint_id, status, changed_by, notes, changed_at)
            VALUES (?, 'pending', ?, 'Complaint submitted', NOW())
        ");
        $historyStmt->execute([$complaintId, $authData['user_id']]);
        
        // Commit transaction
        $db->commit();
        
        // Get the created complaint
        $stmt = $db->prepare("SELECT * FROM complaints WHERE id = ?");
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add attachments to response
        if (!empty($attachments)) {
            $complaint['attachments'] = $attachments;
        }
        
        sendResponse(true, 'Complaint submitted successfully', $complaint, 201);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    sendResponse(false, 'Database error occurred', null, 500);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    sendResponse(false, $e->getMessage(), null, $e->getCode() ?: 500);
}
?>
