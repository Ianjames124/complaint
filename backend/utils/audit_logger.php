<?php

require_once __DIR__ . '/../config/Database.php';

// Ensure getClientIpAddress() is available (defined in helpers.php)
if (!function_exists('getClientIpAddress')) {
    require_once __DIR__ . '/helpers.php';
}

/**
 * Audit Logger Utility
 * Logs all critical actions for security and compliance
 */

/**
 * Log an action to the audit_logs table
 * 
 * @param int $userId User ID who performed the action
 * @param string $role User role (admin, staff, citizen)
 * @param string $actionType Action type (e.g., 'complaint_create', 'complaint_assign', 'status_update', 'user_update')
 * @param array $details Additional details about the action (will be JSON encoded)
 * @param int|null $relatedComplaintId Related complaint ID if applicable
 * @param int|null $relatedRequestId Related request ID if applicable
 * @param int|null $relatedUserId Related user ID if applicable
 * @return bool True on success, false on failure
 */
function logAuditAction(
    int $userId,
    string $role,
    string $actionType,
    array $details = [],
    ?int $relatedComplaintId = null,
    ?int $relatedRequestId = null,
    ?int $relatedUserId = null
): bool {
    try {
        $db = (new Database())->getConnection();
        
        // Get client IP address
        $ipAddress = getClientIpAddress();
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($userAgent && strlen($userAgent) > 500) {
            $userAgent = substr($userAgent, 0, 500); // Limit length
        }
        
        // Prepare details JSON
        $detailsJson = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $db->prepare('
            INSERT INTO audit_logs (
                user_id,
                role,
                action_type,
                related_complaint_id,
                related_request_id,
                related_user_id,
                details,
                ip_address,
                user_agent,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        
        $stmt->execute([
            $userId,
            $role,
            $actionType,
            $relatedComplaintId,
            $relatedRequestId,
            $relatedUserId,
            $detailsJson,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
    } catch (Exception $e) {
        // Log error but don't break the main flow
        error_log('Audit logging failed: ' . $e->getMessage());
        return false;
    }
}

// Note: getClientIpAddress() function is defined in utils/helpers.php
// It's included before this file, so we don't need to redeclare it here

/**
 * Convenience function to log complaint creation
 */
function logComplaintCreate(int $userId, string $role, int $complaintId, array $complaintData): bool {
    return logAuditAction(
        $userId,
        $role,
        'complaint_create',
        [
            'complaint_id' => $complaintId,
            'title' => $complaintData['title'] ?? null,
            'category' => $complaintData['category'] ?? null,
            'priority_level' => $complaintData['priority_level'] ?? null,
            'status' => $complaintData['status'] ?? 'Pending'
        ],
        $complaintId
    );
}

/**
 * Convenience function to log complaint assignment
 */
function logComplaintAssign(
    int $userId,
    string $role,
    int $complaintId,
    int $assignedToStaffId,
    ?int $previousStaffId = null,
    string $assignmentType = 'manual'
): bool {
    return logAuditAction(
        $userId,
        $role,
        'complaint_assign',
        [
            'complaint_id' => $complaintId,
            'assigned_to_staff_id' => $assignedToStaffId,
            'previous_staff_id' => $previousStaffId,
            'assignment_type' => $assignmentType
        ],
        $complaintId
    );
}

/**
 * Convenience function to log status update
 */
function logStatusUpdate(
    int $userId,
    string $role,
    int $complaintId,
    string $oldStatus,
    string $newStatus,
    ?string $notes = null
): bool {
    return logAuditAction(
        $userId,
        $role,
        'status_update',
        [
            'complaint_id' => $complaintId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes
        ],
        $complaintId
    );
}

/**
 * Convenience function to log user profile update
 */
function logUserUpdate(int $userId, string $role, int $targetUserId, array $updatedFields): bool {
    return logAuditAction(
        $userId,
        $role,
        'user_update',
        [
            'target_user_id' => $targetUserId,
            'updated_fields' => array_keys($updatedFields),
            'changes' => $updatedFields
        ],
        null,
        null,
        $targetUserId
    );
}

/**
 * Convenience function to log request creation
 */
function logRequestCreate(int $userId, string $role, int $requestId, array $requestData): bool {
    return logAuditAction(
        $userId,
        $role,
        'request_create',
        [
            'request_id' => $requestId,
            'title' => $requestData['title'] ?? null,
            'category' => $requestData['category'] ?? null,
            'priority_level' => $requestData['priority_level'] ?? null,
            'status' => $requestData['status'] ?? 'Pending'
        ],
        $requestId,
        $requestId
    );
}

