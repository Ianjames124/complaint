<?php
// Suppress warnings/notices to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

// CORS must be first, before any output
require_once __DIR__ . '/../../config/cors.php';
handleCors();

require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../middleware/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(false, 'Method not allowed', null, 405);
}

// Optional: require authentication
$user = getCurrentUser();

$priorityLevels = [
    [
        'value' => 'Low',
        'label' => 'Low Priority',
        'sla_hours' => 72,
        'color' => 'blue',
        'description' => 'Non-urgent issues that can be handled within 72 hours'
    ],
    [
        'value' => 'Medium',
        'label' => 'Medium Priority',
        'sla_hours' => 48,
        'color' => 'yellow',
        'description' => 'Standard issues that should be resolved within 48 hours'
    ],
    [
        'value' => 'High',
        'label' => 'High Priority',
        'sla_hours' => 24,
        'color' => 'orange',
        'description' => 'Important issues requiring attention within 24 hours'
    ],
    [
        'value' => 'Emergency',
        'label' => 'Emergency 🚨',
        'sla_hours' => 4,
        'color' => 'red',
        'description' => 'Critical issues that must be addressed within 4 hours'
    ]
];

sendJsonResponse(true, 'Priority levels fetched', [
    'priority_levels' => $priorityLevels
]);

