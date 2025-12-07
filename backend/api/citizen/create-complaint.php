<?php
// Start output buffering to prevent any accidental output
ob_start();

// Enable error reporting for debugging (will be controlled by setupErrorHandlers)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set error log file path (ensure logs directory exists)
$logsDir = __DIR__ . '/../../../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
$errorLogFile = $logsDir . '/error.log';
ini_set('error_log', $errorLogFile);

// Log the start of the request for debugging
error_log('=== CREATE COMPLAINT REQUEST START ===');
error_log('Request Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log('Content Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'));

// Load centralized helpers FIRST (before any other files that might declare sendJsonResponse)
require_once __DIR__ . '/../../utils/helpers.php';

// Register shutdown function to catch fatal errors
register_shutdown_function(function() use ($errorLogFile) {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        // Log the fatal error
        error_log('FATAL ERROR in create-complaint.php: ' . $error['message']);
        error_log('File: ' . ($error['file'] ?? 'unknown'));
        error_log('Line: ' . ($error['line'] ?? 0));
        error_log('Error Type: ' . $error['type']);
        
        // Clear any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Fatal error occurred - always output JSON
        http_response_code(500);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'],
            'error_type' => 'fatal_error',
            'file' => basename($error['file'] ?? 'unknown'),
            'line' => $error['line'] ?? 0
        ];
        
        // Ensure we can send JSON even if sendJsonResponse isn't available
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Wrap everything in try-catch to catch any exceptions during require
try {
    // CORS must be first, before any output
    require_once __DIR__ . '/../../config/cors.php';
    if (!function_exists('handleCors')) {
        throw new Exception('CORS handler not found');
    }
    handleCors();
} catch (Exception $e) {
    sendJsonResponse(false, 'Configuration error: ' . $e->getMessage(), null, 500);
}

// Load required files with error handling
// Note: helpers.php is already loaded above, so sendJsonResponse is available
try {
    require_once __DIR__ . '/../../config/Database.php';
    // auth.php will check for sendJsonResponse and load helpers.php if needed
    require_once __DIR__ . '/../../middleware/auth.php';
    require_once __DIR__ . '/../../middleware/rate_limit.php';
    require_once __DIR__ . '/../../utils/audit_logger.php';
    require_once __DIR__ . '/../../utils/security.php';
    require_once __DIR__ . '/../../utils/error_handler.php';
    require_once __DIR__ . '/../../utils/send_realtime_event.php';
    require_once __DIR__ . '/../../utils/notification_email.php';
    require_once __DIR__ . '/../../utils/notification_sms.php';
    require_once __DIR__ . '/../../utils/image_upload.php';
} catch (Exception $e) {
    error_log('Failed to load required files: ' . $e->getMessage());
    sendJsonResponse(false, 'Failed to load required components: ' . $e->getMessage(), null, 500);
} catch (Error $e) {
    error_log('Fatal error loading files: ' . $e->getMessage());
    sendJsonResponse(false, 'Fatal error loading components: ' . $e->getMessage(), null, 500);
}

// Setup error handlers (after all files are loaded)
try {
    if (function_exists('setupErrorHandlers')) {
        setupErrorHandlers();
    }
} catch (Exception $e) {
    error_log('Failed to setup error handlers: ' . $e->getMessage());
    // Continue - we have sendJsonResponse as fallback
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Use RBAC authorize function - only citizen can access
// Note: authorize() may call exit() directly, so we need to handle it carefully
if (!function_exists('authorize')) {
    error_log('Authorization function not found');
    sendJsonResponse(false, 'Authorization system not available', null, 500);
}

// authorize() will exit if authentication fails, so if we get here, auth succeeded
$user = authorize(['citizen']);

// Double-check user data (authorize should have already validated)
if (!$user || !isset($user['id']) || !isset($user['role'])) {
    error_log('Invalid user data after authorization: ' . json_encode($user));
    sendJsonResponse(false, 'Invalid user data', null, 401);
}

// Enforce rate limiting (5 requests per 60 seconds)
try {
    if (function_exists('enforceRateLimit')) {
        enforceRateLimit('api/citizen/create-complaint', $user['id'], 5, 60);
    }
} catch (Exception $e) {
    error_log('Rate limit error: ' . $e->getMessage());
    // Continue - rate limiting failure shouldn't block complaint creation
}

// Handle both JSON and multipart/form-data requests
$input = null;
$uploadedImages = [];

try {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    error_log('Content Type: ' . $contentType);
    
    // Check if it's multipart/form-data (file upload)
    if (strpos($contentType, 'multipart/form-data') !== false) {
        error_log('Processing multipart/form-data request...');
        
        // Get form data
        $input = [
            'title' => $_POST['title'] ?? $_POST['subject'] ?? '',
            'description' => $_POST['description'] ?? '',
            'category' => $_POST['category'] ?? 'General',
            'location' => $_POST['location'] ?? 'Not specified',
            'priority_level' => $_POST['priority_level'] ?? 'Medium'
        ];
        
        // Handle file_ids if provided as JSON string
        if (isset($_POST['file_ids']) && !empty($_POST['file_ids'])) {
            $fileIdsJson = $_POST['file_ids'];
            if (is_string($fileIdsJson)) {
                $decodedFileIds = json_decode($fileIdsJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFileIds)) {
                    $input['file_ids'] = $decodedFileIds;
                } else {
                    // Try as comma-separated string
                    $input['file_ids'] = array_filter(array_map('intval', explode(',', $fileIdsJson)));
                }
            } elseif (is_array($fileIdsJson)) {
                $input['file_ids'] = array_filter(array_map('intval', $fileIdsJson));
            }
        }
        
        // Handle uploaded images
        if (isset($_FILES['images']) && is_array($_FILES['images'])) {
            $files = $_FILES['images'];
            
            // Handle single file or multiple files
            if (is_array($files['name'])) {
                // Multiple files
                $fileCount = count($files['name']);
                error_log("Processing {$fileCount} uploaded images...");
                
                // Limit to 5 images
                if ($fileCount > 5) {
                    sendJsonResponse(false, 'Maximum 5 images allowed', null, 400);
                }
                
                for ($i = 0; $i < $fileCount; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        $uploadedImages[] = $file;
                    }
                }
            } else {
                // Single file
                if ($files['error'] === UPLOAD_ERR_OK) {
                    $uploadedImages[] = $files;
                }
            }
        }
        
        error_log('Found ' . count($uploadedImages) . ' images to upload');
    } else {
        // Handle JSON request
        error_log('Reading JSON input...');
        $jsonInput = file_get_contents('php://input');
        
        if ($jsonInput === false) {
            error_log('ERROR: file_get_contents returned false');
            sendJsonResponse(false, 'Failed to read request body', null, 400);
        }
        
        if (empty($jsonInput)) {
            error_log('ERROR: Request body is empty');
            sendJsonResponse(false, 'Request body is empty', null, 400);
        }
        
        error_log('JSON input length: ' . strlen($jsonInput));
        error_log('JSON input preview: ' . substr($jsonInput, 0, 200));
        
        // Decode JSON first to check if it's valid
        $decoded = json_decode($jsonInput, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg() . ' (code: ' . $jsonError . ')');
            sendJsonResponse(false, 'Invalid JSON format: ' . json_last_error_msg(), null, 400);
        }
        
        if (!is_array($decoded)) {
            error_log('ERROR: Decoded JSON is not an array. Type: ' . gettype($decoded));
            sendJsonResponse(false, 'Request body must be a JSON object', null, 400);
        }
        
        error_log('JSON decoded successfully. Keys: ' . implode(', ', array_keys($decoded)));
        
        if (!function_exists('validateJsonInput')) {
            error_log('WARNING: validateJsonInput function not found, using raw decoded data');
            $input = $decoded;
        } else {
            $input = validateJsonInput($jsonInput);
            if ($input === false || !is_array($input)) {
                error_log('ERROR: validateJsonInput returned false or non-array');
                sendJsonResponse(false, 'Invalid JSON body or validation failed', null, 400);
            }
        }
    }
    
    error_log('Input validation successful');
} catch (Exception $e) {
    error_log('EXCEPTION in input processing: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Failed to parse request: ' . $e->getMessage(), null, 400);
} catch (Error $e) {
    error_log('FATAL ERROR in input processing: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    sendJsonResponse(false, 'Failed to parse request', null, 400);
}

// Sanitize and validate input
try {
    if (!function_exists('validateString') || !function_exists('validateEnum')) {
        throw new Exception('Validation functions not available');
    }
    
    // Get and validate title (accept both 'title' and 'subject' for backward compatibility)
    $titleRaw = $input['title'] ?? $input['subject'] ?? '';
    if (empty(trim($titleRaw))) {
        sendJsonResponse(false, 'Title (or subject) is required', null, 400);
    }
    $title = validateString($titleRaw, 1, 255);
    
    // Get and validate description
    $descriptionRaw = $input['description'] ?? '';
    if (empty(trim($descriptionRaw))) {
        sendJsonResponse(false, 'Description is required', null, 400);
    }
    $description = validateString($descriptionRaw, 10, 5000);
    
    // Get and validate category
    $categoryRaw = $input['category'] ?? 'General';
    $category = validateString($categoryRaw, 1, 100);
    
    // Get and validate location
    $locationRaw = $input['location'] ?? 'Not specified';
    $location = validateString($locationRaw, 1, 255);
    
    // Get and validate priority level
    $priorityRaw = $input['priority_level'] ?? 'Medium';
    $priorityLevel = validateEnum($priorityRaw, ['Low', 'Medium', 'High', 'Emergency']);

    // Validate required fields - check if validation returned false
    if ($title === false) {
        sendJsonResponse(false, 'Title must be between 1 and 255 characters', null, 400);
    }
    
    if ($description === false) {
        sendJsonResponse(false, 'Description must be between 10 and 5000 characters', null, 400);
    }
    
    if ($category === false) {
        sendJsonResponse(false, 'Category must be between 1 and 100 characters', null, 400);
    }
    
    if ($location === false) {
        sendJsonResponse(false, 'Location must be between 1 and 255 characters', null, 400);
    }

    // Ensure priority is valid - default to Medium if invalid
    if ($priorityLevel === false) {
        $priorityLevel = 'Medium';
    }
    
    // Additional sanitization - ensure strings are properly trimmed
    $title = trim($title);
    $description = trim($description);
    $category = trim($category);
    $location = trim($location);
    
} catch (Exception $e) {
    error_log('Input validation error: ' . $e->getMessage());
    sendJsonResponse(false, 'Input validation failed: ' . $e->getMessage(), null, 400);
} catch (Error $e) {
    error_log('Fatal input validation error: ' . $e->getMessage());
    sendJsonResponse(false, 'Input validation failed', null, 400);
}

// Initialize database connection and start transaction
$db = null;
try {
    error_log('Connecting to database...');
    $db = (new Database())->getConnection();
    if (!$db) {
        error_log('ERROR: Database connection returned null');
        throw new Exception('Database connection failed');
    }
    
    error_log('Database connection established');
    
    // Start transaction for data consistency
    error_log('Starting database transaction...');
    $db->beginTransaction();
    error_log('Transaction started');
    
    // Calculate SLA based on priority
    $slaHours = [
        'Low' => 72,
        'Medium' => 48,
        'High' => 24,
        'Emergency' => 4
    ];
    $slaDueAt = date('Y-m-d H:i:s', strtotime("+{$slaHours[$priorityLevel]} hours"));
    
    // Check which columns exist in the complaints table
    error_log('Checking complaints table structure...');
    $columnsQuery = $db->query("SHOW COLUMNS FROM complaints");
    $existingColumns = [];
    while ($row = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    error_log('Existing columns: ' . implode(', ', $existingColumns));
    
    // Insert complaint - adapt to available columns
    $citizenId = (int) $user['id'];
    if ($citizenId <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Build INSERT statement based on available columns
    $insertColumns = ['citizen_id', 'description', 'category', 'location', 'status'];
    $insertValues = [$citizenId, $description, $category, $location, 'Pending'];
    $placeholders = ['?', '?', '?', '?', '?'];
    
    // Add title or subject column
    if (in_array('title', $existingColumns)) {
        $insertColumns[] = 'title';
        $insertValues[] = $title;
        $placeholders[] = '?';
    } elseif (in_array('subject', $existingColumns)) {
        $insertColumns[] = 'subject';
        $insertValues[] = $title; // Use title value for subject column
        $placeholders[] = '?';
    }
    
    // Add priority_level if column exists
    if (in_array('priority_level', $existingColumns)) {
        $insertColumns[] = 'priority_level';
        $insertValues[] = $priorityLevel;
        $placeholders[] = '?';
    }
    
    // Add SLA columns if they exist
    if (in_array('sla_due_at', $existingColumns)) {
        $insertColumns[] = 'sla_due_at';
        $insertValues[] = $slaDueAt;
        $placeholders[] = '?';
    }
    
    if (in_array('sla_status', $existingColumns)) {
        $insertColumns[] = 'sla_status';
        $insertValues[] = 'On Time';
        $placeholders[] = '?';
    }
    
    // Add created_at if it doesn't have a default
    if (in_array('created_at', $existingColumns)) {
        $insertColumns[] = 'created_at';
        $insertValues[] = date('Y-m-d H:i:s');
        $placeholders[] = '?';
    }
    
    $sql = 'INSERT INTO complaints (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    error_log('SQL Query: ' . $sql);
    error_log('Values: ' . json_encode($insertValues));
    
    $stmt = $db->prepare($sql);
    
    if (!$stmt) {
        $errorInfo = $db->errorInfo();
        throw new Exception('Failed to prepare INSERT statement: ' . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    // Execute with proper error handling
    $executeResult = $stmt->execute($insertValues);
    
    if (!$executeResult) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Failed to execute INSERT: ' . ($errorInfo[2] ?? 'Unknown error'));
    }
    
    $complaintId = (int) $db->lastInsertId();
    
    if ($complaintId <= 0) {
        throw new Exception('Failed to insert complaint - no ID returned. Check database constraints.');
    }
    
    error_log('Complaint inserted successfully with ID: ' . $complaintId);
    
    // Upload images if provided (non-blocking - don't fail complaint creation if image upload fails)
    $uploadedImagePaths = [];
    if (!empty($uploadedImages)) {
        error_log('Processing ' . count($uploadedImages) . ' image uploads...');
        foreach ($uploadedImages as $imageFile) {
            try {
                $uploadResult = uploadComplaintImage($imageFile, $complaintId);
                if ($uploadResult['success']) {
                    $uploadedImagePaths[] = $uploadResult['path'];
                    error_log('Image uploaded successfully: ' . $uploadResult['path']);
                } else {
                    error_log('Image upload failed: ' . ($uploadResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                error_log('Exception during image upload: ' . $e->getMessage());
                // Continue with other images
            }
        }
        error_log('Successfully uploaded ' . count($uploadedImagePaths) . ' images');
    }
    
    // Log audit action (non-blocking - don't fail if this fails)
    try {
        logComplaintCreate($user['id'], $user['role'], $complaintId, [
            'title' => $title,
            'category' => $category,
            'priority_level' => $priorityLevel,
            'status' => 'Pending',
            'location' => $location
        ]);
    } catch (Exception $e) {
        error_log('Audit logging failed in create-complaint.php: ' . $e->getMessage());
        // Continue - audit logging failure shouldn't break complaint creation
    }
    
    // Check if auto-assign is enabled (non-blocking)
    $autoAssignEnabled = false;
    try {
        $stmt = $db->prepare('SELECT setting_value FROM auto_assign_settings WHERE setting_key = ? LIMIT 1');
        if ($stmt) {
            $stmt->execute(['auto_assign_enabled']);
            $autoAssignSetting = $stmt->fetch(PDO::FETCH_ASSOC);
            $autoAssignEnabled = $autoAssignSetting && $autoAssignSetting['setting_value'] == '1';
        }
    } catch (Exception $e) {
        error_log('Auto-assign check failed in create-complaint.php: ' . $e->getMessage());
        // Continue without auto-assign if check fails
    }
    
    // Auto-assign if enabled (non-blocking - wrap in try-catch)
    if ($autoAssignEnabled) {
        try {
            // Get assignment method
            $stmt = $db->prepare('SELECT setting_value FROM auto_assign_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute(['assignment_method']);
            $methodSetting = $stmt->fetch(PDO::FETCH_ASSOC);
            $assignmentMethod = $methodSetting ? $methodSetting['setting_value'] : 'workload';
            
            // Find best staff using staff_assignments table for accurate tracking
            $staffQuery = 'SELECT 
                u.id, 
                u.full_name, 
                u.department_id,
                COUNT(DISTINCT CASE WHEN c.status IN ("Pending", "Assigned", "In Progress") THEN c.id END) as active_cases,
                MAX(sa.assigned_at) as last_assigned_at
            FROM users u
            LEFT JOIN staff_assignments sa ON sa.staff_id = u.id
            LEFT JOIN complaints c ON c.id = sa.complaint_id 
                AND c.status IN ("Pending", "Assigned", "In Progress")
            WHERE u.role = "staff" 
            AND u.status = "active"
            GROUP BY u.id, u.full_name, u.department_id';
            
            if ($assignmentMethod === 'workload' || $priorityLevel === 'Emergency') {
                if ($priorityLevel === 'Emergency') {
                    $staffQuery .= ' ORDER BY 
                        CASE WHEN COUNT(DISTINCT CASE WHEN c.priority_level = "Emergency" AND c.status IN ("Pending", "Assigned", "In Progress") THEN c.id END) = 0 THEN 0 ELSE 1 END,
                        active_cases ASC, 
                        last_assigned_at ASC';
                } else {
                    $staffQuery .= ' ORDER BY active_cases ASC, last_assigned_at ASC';
                }
            } else {
                $staffQuery .= ' ORDER BY last_assigned_at ASC, active_cases ASC';
            }
            
            $staffQuery .= ' LIMIT 1';
            
            $stmt = $db->prepare($staffQuery);
            if ($stmt) {
                $stmt->execute();
                $selectedStaff = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($selectedStaff && isset($selectedStaff['id'])) {
                    $staffId = (int) $selectedStaff['id'];
                    $departmentId = isset($selectedStaff['department_id']) ? (int) $selectedStaff['department_id'] : null;
                    
                    // Create assignment
                    $stmt = $db->prepare('INSERT INTO staff_assignments (complaint_id, staff_id, assigned_by_admin_id, assigned_at) VALUES (?, ?, ?, NOW())');
                    $stmt->execute([$complaintId, $staffId, $user['id']]);
                    
                    // Log assignment in audit log (non-blocking)
                    try {
                        logComplaintAssign($user['id'], $user['role'], $complaintId, $staffId, null, 'auto');
                    } catch (Exception $e) {
                        error_log('Audit logging failed for assignment: ' . $e->getMessage());
                    }
                    
                    // Update complaint
                    $stmt = $db->prepare('UPDATE complaints SET status = ?, staff_id = ?, department_id = ? WHERE id = ?');
                    $stmt->execute(['Assigned', $staffId, $departmentId, $complaintId]);
                    
                    // Update staff active cases
                    $stmt = $db->prepare('UPDATE users SET active_cases = active_cases + 1, last_assigned_at = NOW() WHERE id = ?');
                    $stmt->execute([$staffId]);
                    
                    // Add status update
                    $stmt = $db->prepare('INSERT INTO status_updates (complaint_id, updated_by_user_id, role, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                    $stmt->execute([
                        $complaintId,
                        $user['id'],
                        'citizen',
                        'Assigned',
                        "Auto-assigned to {$selectedStaff['full_name']} on creation"
                    ]);
                    
                    // Send notifications (non-blocking - don't fail if these fail)
                    try {
                        sendComplaintAssignedEmail(
                            $staffId,
                            $complaintId,
                            [
                                'title' => $title,
                                'category' => $category,
                                'priority_level' => $priorityLevel,
                                'status' => 'Assigned'
                            ],
                            'Auto-Assignment System'
                        );
                    } catch (Exception $e) {
                        error_log('Email notification failed: ' . $e->getMessage());
                    }
                    
                    try {
                        sendSMSNotification(
                            $staffId,
                            $complaintId,
                            [
                                'title' => $title,
                                'category' => $category,
                                'priority_level' => $priorityLevel
                            ],
                            'Auto-Assignment System'
                        );
                    } catch (Exception $e) {
                        error_log('SMS notification failed: ' . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Auto-assign failed in create-complaint.php: ' . $e->getMessage());
            // Continue without auto-assignment - don't fail the complaint creation
        }
    }
    
    // Link uploaded files to complaint if file_ids are provided (non-blocking)
    $fileIds = [];
    if (isset($input['file_ids']) && is_array($input['file_ids'])) {
        // Sanitize file IDs - only accept positive integers
        $fileIds = array_filter(
            array_map('intval', $input['file_ids']),
            function($id) { return $id > 0; }
        );
        
        if (count($fileIds) > 0) {
            try {
                // Remove duplicates
                $fileIds = array_unique($fileIds);
                
                // Check if complaint_files table exists before trying to update
                $tableCheck = $db->query("SHOW TABLES LIKE 'complaint_files'");
                if ($tableCheck && $tableCheck->rowCount() > 0) {
                    $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
                    $params = array_merge([$complaintId], $fileIds);
                    $stmtFiles = $db->prepare("UPDATE complaint_files SET complaint_id = ? WHERE id IN ($placeholders)");
                    if ($stmtFiles) {
                        $stmtFiles->execute($params);
                    }
                }
            } catch (Exception $e) {
                error_log('File linking failed in create-complaint.php: ' . $e->getMessage());
                // Continue - file linking failure shouldn't break complaint creation
            } catch (Error $e) {
                error_log('Fatal error in file linking: ' . $e->getMessage());
                // Continue - file linking failure shouldn't break complaint creation
            }
        }
    }
    
    // Commit transaction
    error_log('Committing transaction for complaint ID: ' . $complaintId);
    $db->commit();
    error_log('Transaction committed successfully');
    
    // Get the created complaint with user info
    try {
        error_log('Fetching created complaint details...');
        $stmt = $db->prepare('
            SELECT c.*, u.full_name as citizen_name, u.email 
            FROM complaints c
            JOIN users u ON c.citizen_id = u.id
            WHERE c.id = ?
            LIMIT 1
        ');
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log('Complaint fetched successfully');
    } catch (Exception $e) {
        error_log('Failed to fetch created complaint: ' . $e->getMessage());
        // Create minimal complaint data for response
        $complaint = [
            'id' => $complaintId,
            'title' => $title,
            'status' => 'Pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // Send email notification to citizen (non-blocking)
    try {
        sendComplaintReceivedEmail(
            $user['id'],
            $complaintId,
            [
                'title' => $title,
                'category' => $category,
                'priority_level' => $priorityLevel,
                'status' => $complaint['status'] ?? 'Pending',
                'created_at' => $complaint['created_at'] ?? date('Y-m-d H:i:s')
            ]
        );
    } catch (Exception $e) {
        error_log('Email notification failed: ' . $e->getMessage());
    }
    
    // Emit real-time event to admins (non-blocking)
    try {
        send_realtime_event('new_complaint', [
            'complaint_id' => $complaintId,
            'title' => $title,
            'category' => $category,
            'priority_level' => $priorityLevel,
            'citizen_name' => $complaint['citizen_name'] ?? $user['full_name'] ?? 'Unknown',
            'created_at' => $complaint['created_at'] ?? date('Y-m-d H:i:s'),
            'status' => $complaint['status'] ?? 'Pending',
            'sla_due_at' => $slaDueAt
        ]);
    } catch (Exception $e) {
        error_log('Real-time event failed: ' . $e->getMessage());
    }
    
    error_log('=== COMPLAINT CREATED SUCCESSFULLY ===');
    error_log('Complaint ID: ' . $complaintId);
    error_log('Title: ' . $title);
    error_log('File IDs: ' . implode(', ', $fileIds));
    error_log('Uploaded Images: ' . count($uploadedImagePaths));
    
    // Get uploaded images for response
    $images = [];
    if (!empty($uploadedImagePaths)) {
        foreach ($uploadedImagePaths as $path) {
            $images[] = [
                'path' => $path,
                'url' => '/api/complaints/image.php?file=' . urlencode($path)
            ];
        }
    }
    
    sendJsonResponse(true, 'Complaint submitted successfully', [
        'complaint' => $complaint,
        'file_ids' => $fileIds,
        'images' => $images
    ], 201);
    
} catch (PDOException $e) {
    error_log('=== PDO EXCEPTION CAUGHT ===');
    error_log('Error message: ' . $e->getMessage());
    error_log('Error code: ' . $e->getCode());
    error_log('SQL Error Info: ' . print_r($e->errorInfo ?? [], true));
    error_log('File: ' . $e->getFile());
    error_log('Line: ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Rollback transaction if it was started
    if (isset($db) && $db->inTransaction()) {
        try {
            error_log('Attempting to rollback transaction...');
            $db->rollBack();
            error_log('Transaction rolled back successfully');
        } catch (Exception $rollbackError) {
            error_log('ERROR: Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    
    // Check for specific database errors
    $errorCode = $e->errorInfo[1] ?? null;
    $errorMessage = 'Failed to submit complaint. Please try again later.';
    
    if ($errorCode == 1452) { // Foreign key constraint fails
        $errorMessage = 'Invalid user or department. Please contact support.';
    } elseif ($errorCode == 1062) { // Duplicate entry
        $errorMessage = 'This complaint already exists. Please check your complaints list.';
    } elseif ($errorCode == 1146) { // Table doesn't exist
        $errorMessage = 'Database configuration error. Please contact administrator.';
    }
    
    // Include more details in development
    $errorDetails = [
        'error_code' => 'database_error',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
    
    // Add detailed error in development (check if we're not in production)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorDetails['message'] = $e->getMessage();
        $errorDetails['sql_error_info'] = $e->errorInfo ?? [];
    }
    
    sendJsonResponse(false, $errorMessage, $errorDetails, 500);
} catch (Exception $e) {
    error_log('=== EXCEPTION CAUGHT ===');
    error_log('Error message: ' . $e->getMessage());
    error_log('Error code: ' . $e->getCode());
    error_log('File: ' . $e->getFile());
    error_log('Line: ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Rollback transaction if it was started
    if (isset($db) && $db->inTransaction()) {
        try {
            error_log('Attempting to rollback transaction...');
            $db->rollBack();
            error_log('Transaction rolled back successfully');
        } catch (Exception $rollbackError) {
            error_log('ERROR: Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    
    $errorDetails = [
        'error_code' => 'server_error',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ];
    
    // Include error message in development
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorDetails['message'] = $e->getMessage();
        $errorDetails['trace'] = $e->getTraceAsString();
    }
    
    sendJsonResponse(false, 'An unexpected error occurred: ' . $e->getMessage(), $errorDetails, 500);
} catch (Error $e) {
    error_log('=== FATAL ERROR CAUGHT ===');
    error_log('Error message: ' . $e->getMessage());
    error_log('Error code: ' . $e->getCode());
    error_log('File: ' . $e->getFile());
    error_log('Line: ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Rollback transaction if it was started
    if (isset($db) && $db->inTransaction()) {
        try {
            error_log('Attempting to rollback transaction...');
            $db->rollBack();
            error_log('Transaction rolled back successfully');
        } catch (Exception $rollbackError) {
            error_log('ERROR: Rollback failed: ' . $rollbackError->getMessage());
        }
    }
    
    sendJsonResponse(false, 'A fatal error occurred: ' . $e->getMessage(), [
        'error_code' => 'fatal_error',
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
