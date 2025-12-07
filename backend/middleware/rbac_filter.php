<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/Database.php';

/**
 * RBAC Data Filtering Helpers
 * These functions help filter database queries based on user roles
 */

/**
 * Get SQL WHERE clause and parameters for filtering complaints based on user role
 * 
 * @param array $user User payload from authorize() function
 * @param string $tableAlias Table alias for complaints (default: 'c')
 * @return array ['where' => string, 'params' => array]
 */
function getComplaintFilterByRole(array $user, string $tableAlias = 'c'): array
{
    $role = $user['role'] ?? null;
    $userId = $user['id'] ?? null;
    
    $where = [];
    $params = [];
    
    switch ($role) {
        case 'admin':
            // Admin: No restrictions - can see all complaints
            // Return empty filter (no WHERE clause needed)
            break;
            
        case 'staff':
            // Staff: Only see complaints assigned to them
            if ($userId) {
                $where[] = "EXISTS (
                    SELECT 1 FROM staff_assignments sa 
                    WHERE sa.complaint_id = {$tableAlias}.id 
                    AND sa.staff_id = ?
                )";
                $params[] = $userId;
            } else {
                // If no user ID, return empty result filter
                $where[] = "1 = 0"; // Always false
            }
            break;
            
        case 'citizen':
            // Citizen: Only see their own complaints
            if ($userId) {
                $where[] = "{$tableAlias}.citizen_id = ?";
                $params[] = $userId;
            } else {
                // If no user ID, return empty result filter
                $where[] = "1 = 0"; // Always false
            }
            break;
            
        default:
            // Unknown role: deny access
            $where[] = "1 = 0"; // Always false
            break;
    }
    
    return [
        'where' => !empty($where) ? implode(' AND ', $where) : '',
        'params' => $params
    ];
}

/**
 * Get SQL WHERE clause and parameters for filtering requests based on user role
 * Requests are stored in the same complaints table but with category starting with "Request:"
 * 
 * @param array $user User payload from authorize() function
 * @param string $tableAlias Table alias for complaints (default: 'c')
 * @return array ['where' => string, 'params' => array]
 */
function getRequestFilterByRole(array $user, string $tableAlias = 'c'): array
{
    $role = $user['role'] ?? null;
    $userId = $user['id'] ?? null;
    
    $where = [];
    $params = [];
    
    // First, filter to only requests (category starts with "Request:")
    $where[] = "{$tableAlias}.category LIKE 'Request:%'";
    
    switch ($role) {
        case 'admin':
            // Admin: No restrictions - can see all requests
            break;
            
        case 'staff':
            // Staff: Only see requests assigned to them
            if ($userId) {
                $where[] = "EXISTS (
                    SELECT 1 FROM staff_assignments sa 
                    WHERE sa.complaint_id = {$tableAlias}.id 
                    AND sa.staff_id = ?
                )";
                $params[] = $userId;
            } else {
                $where[] = "1 = 0"; // Always false
            }
            break;
            
        case 'citizen':
            // Citizen: Only see their own requests
            if ($userId) {
                $where[] = "{$tableAlias}.citizen_id = ?";
                $params[] = $userId;
            } else {
                $where[] = "1 = 0"; // Always false
            }
            break;
            
        default:
            $where[] = "1 = 0"; // Always false
            break;
    }
    
    return [
        'where' => !empty($where) ? implode(' AND ', $where) : '',
        'params' => $params
    ];
}

/**
 * Check if user can access a specific complaint
 * 
 * @param array $user User payload from authorize() function
 * @param int $complaintId Complaint ID to check
 * @param PDO $db Database connection
 * @return bool True if user can access, false otherwise
 */
function canAccessComplaint(array $user, int $complaintId, PDO $db): bool
{
    $role = $user['role'] ?? null;
    $userId = $user['id'] ?? null;
    
    if (!$userId) {
        return false;
    }
    
    switch ($role) {
        case 'admin':
            // Admin can access any complaint
            $stmt = $db->prepare('SELECT id FROM complaints WHERE id = ?');
            $stmt->execute([$complaintId]);
            return (bool) $stmt->fetch();
            
        case 'staff':
            // Staff can only access assigned complaints
            $stmt = $db->prepare('
                SELECT c.id 
                FROM complaints c
                INNER JOIN staff_assignments sa ON sa.complaint_id = c.id
                WHERE c.id = ? AND sa.staff_id = ?
                LIMIT 1
            ');
            $stmt->execute([$complaintId, $userId]);
            return (bool) $stmt->fetch();
            
        case 'citizen':
            // Citizen can only access their own complaints
            $stmt = $db->prepare('SELECT id FROM complaints WHERE id = ? AND citizen_id = ?');
            $stmt->execute([$complaintId, $userId]);
            return (bool) $stmt->fetch();
            
        default:
            return false;
    }
}

/**
 * Check if user can access a specific request
 * 
 * @param array $user User payload from authorize() function
 * @param int $requestId Request ID (complaint ID with category starting with "Request:")
 * @param PDO $db Database connection
 * @return bool True if user can access, false otherwise
 */
function canAccessRequest(array $user, int $requestId, PDO $db): bool
{
    $role = $user['role'] ?? null;
    $userId = $user['id'] ?? null;
    
    if (!$userId) {
        return false;
    }
    
    switch ($role) {
        case 'admin':
            // Admin can access any request
            $stmt = $db->prepare('SELECT id FROM complaints WHERE id = ? AND category LIKE "Request:%"');
            $stmt->execute([$requestId]);
            return (bool) $stmt->fetch();
            
        case 'staff':
            // Staff can only access assigned requests
            $stmt = $db->prepare('
                SELECT c.id 
                FROM complaints c
                INNER JOIN staff_assignments sa ON sa.complaint_id = c.id
                WHERE c.id = ? AND c.category LIKE "Request:%" AND sa.staff_id = ?
                LIMIT 1
            ');
            $stmt->execute([$requestId, $userId]);
            return (bool) $stmt->fetch();
            
        case 'citizen':
            // Citizen can only access their own requests
            $stmt = $db->prepare('SELECT id FROM complaints WHERE id = ? AND category LIKE "Request:%" AND citizen_id = ?');
            $stmt->execute([$requestId, $userId]);
            return (bool) $stmt->fetch();
            
        default:
            return false;
    }
}

/**
 * Apply role-based filtering to a complaint query
 * This is a convenience function that adds the WHERE clause to an existing query
 * 
 * @param string $baseQuery Base SQL query (should end before WHERE clause)
 * @param array $user User payload from authorize() function
 * @param string $tableAlias Table alias for complaints (default: 'c')
 * @param array $existingWhere Existing WHERE conditions (optional)
 * @param array $existingParams Existing parameters (optional)
 * @return array ['query' => string, 'params' => array]
 */
function applyComplaintRoleFilter(
    string $baseQuery, 
    array $user, 
    string $tableAlias = 'c',
    array $existingWhere = [],
    array $existingParams = []
): array {
    $roleFilter = getComplaintFilterByRole($user, $tableAlias);
    
    $allWhere = array_merge($existingWhere, $roleFilter['where'] ? [$roleFilter['where']] : []);
    $allParams = array_merge($existingParams, $roleFilter['params']);
    
    $whereClause = !empty($allWhere) ? 'WHERE ' . implode(' AND ', $allWhere) : '';
    
    return [
        'query' => $baseQuery . ' ' . $whereClause,
        'params' => $allParams
    ];
}

