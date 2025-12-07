<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/role_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get settings
    $admin = requireAdmin();
    $db = (new Database())->getConnection();
    
    try {
        $stmt = $db->prepare('SELECT setting_key, setting_value FROM auto_assign_settings');
        $stmt->execute();
        $settings = $stmt->fetchAll();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = $setting['setting_value'];
        }
        
        sendJsonResponse(true, 'Auto-assign settings fetched', [
            'settings' => $result
        ]);
    } catch (PDOException $e) {
        error_log('Database error in auto-assign-settings.php: ' . $e->getMessage());
        sendJsonResponse(false, 'Failed to fetch settings', null, 500);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update settings
    $admin = requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!is_array($input)) {
        sendJsonResponse(false, 'Invalid JSON body');
    }
    
    $db = (new Database())->getConnection();
    
    try {
        $db->beginTransaction();
        
        foreach ($input as $key => $value) {
            $stmt = $db->prepare('INSERT INTO auto_assign_settings (setting_key, setting_value, updated_by_admin_id, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by_admin_id = ?, updated_at = NOW()');
            $stmt->execute([$key, $value, $admin['id'], $value, $admin['id']]);
        }
        
        $db->commit();
        
        sendJsonResponse(true, 'Settings updated successfully', [
            'settings' => $input
        ]);
    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Database error in auto-assign-settings.php: ' . $e->getMessage());
        sendJsonResponse(false, 'Failed to update settings', null, 500);
    }
} else {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

