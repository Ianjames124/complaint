<?php
/**
 * Image Upload Utility
 * Handles secure image uploads for complaints
 */

require_once __DIR__ . '/../config/Database.php';

/**
 * Validate uploaded image file
 * 
 * @param array $file $_FILES array element
 * @return array ['valid' => bool, 'error' => string|null]
 */
function validateImageFile(array $file): array
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return [
            'valid' => false,
            'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'
        ];
    }

    // Check file size (max 5MB per image)
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        return [
            'valid' => false,
            'error' => 'File size exceeds 5MB limit'
        ];
    }

    // Check file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return [
            'valid' => false,
            'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed'
        ];
    }

    // Additional validation: check if it's actually an image
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return [
            'valid' => false,
            'error' => 'File is not a valid image'
        ];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * Upload complaint image
 * 
 * @param array $file $_FILES array element
 * @param int $complaintId Complaint ID
 * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
 */
function uploadComplaintImage(array $file, int $complaintId): array
{
    try {
        // Validate file
        $validation = validateImageFile($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'path' => null,
                'error' => $validation['error']
            ];
        }

        // Create upload directory structure: /uploads/complaints/{year}/{complaint_id}/
        $year = date('Y');
        $uploadBaseDir = __DIR__ . '/../../uploads/complaints/' . $year . '/' . $complaintId;
        
        if (!is_dir($uploadBaseDir)) {
            if (!mkdir($uploadBaseDir, 0755, true)) {
                return [
                    'success' => false,
                    'path' => null,
                    'error' => 'Failed to create upload directory'
                ];
            }
        }

        // Generate secure filename (hash + timestamp + extension)
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $hash = md5($file['name'] . time() . uniqid());
        $fileName = $hash . '_' . time() . '.' . strtolower($extension);
        $filePath = $uploadBaseDir . '/' . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => false,
                'path' => null,
                'error' => 'Failed to move uploaded file'
            ];
        }

        // Get file info
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Save to database
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('
            INSERT INTO complaint_images (complaint_id, image_path, file_name, file_size, mime_type, uploaded_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        
        // Store relative path from backend/uploads/
        $relativePath = 'complaints/' . $year . '/' . $complaintId . '/' . $fileName;
        
        $stmt->execute([
            $complaintId,
            $relativePath,
            $originalName . '.' . $extension,
            $file['size'],
            $mimeType
        ]);

        return [
            'success' => true,
            'path' => $relativePath,
            'error' => null,
            'id' => $db->lastInsertId()
        ];
    } catch (Exception $e) {
        error_log('Image upload error: ' . $e->getMessage());
        return [
            'success' => false,
            'path' => null,
            'error' => 'Failed to upload image: ' . $e->getMessage()
        ];
    }
}

/**
 * Get complaint images
 * 
 * @param int $complaintId Complaint ID
 * @return array Array of image data
 */
function getComplaintImages(int $complaintId): array
{
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('
            SELECT id, image_path, file_name, file_size, mime_type, uploaded_at
            FROM complaint_images
            WHERE complaint_id = ?
            ORDER BY uploaded_at ASC
        ');
        $stmt->execute([$complaintId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Error fetching complaint images: ' . $e->getMessage());
        return [];
    }
}

/**
 * Delete complaint image
 * 
 * @param int $imageId Image ID
 * @return bool Success status
 */
function deleteComplaintImage(int $imageId): bool
{
    try {
        $db = (new Database())->getConnection();
        
        // Get image path before deleting
        $stmt = $db->prepare('SELECT image_path FROM complaint_images WHERE id = ?');
        $stmt->execute([$imageId]);
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($image) {
            // Delete from database
            $stmt = $db->prepare('DELETE FROM complaint_images WHERE id = ?');
            $stmt->execute([$imageId]);
            
            // Delete physical file
            $filePath = __DIR__ . '/../../uploads/' . $image['image_path'];
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log('Error deleting complaint image: ' . $e->getMessage());
        return false;
    }
}

