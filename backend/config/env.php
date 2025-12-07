<?php
// Simple .env-style configuration loader
// You can either edit values here directly or load from an external .env file.

// OPTIONAL: load from real .env file if present
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// Default configuration (can be overridden by .env)
return [
    'DB_HOST'        => $_ENV['DB_HOST']        ?? 'localhost',
    'DB_NAME'        => $_ENV['DB_NAME']        ?? 'complaint_db',
    'DB_USER'        => $_ENV['DB_USER']        ?? 'root',
    'DB_PASS'        => $_ENV['DB_PASS']        ?? '',
    'JWT_SECRET'     => $_ENV['JWT_SECRET']     ?? 'default_jwt_secret_change_in_production_' . bin2hex(random_bytes(16)),
    'JWT_ISSUER'     => $_ENV['JWT_ISSUER']     ?? 'http://localhost',
    'JWT_EXPIRES_IN' => (int)($_ENV['JWT_EXPIRES_IN'] ?? 86400), // 24 hours in seconds
    'ALLOWED_ORIGIN' => $_ENV['ALLOWED_ORIGIN'] ?? 'http://localhost:3000',
    // If true, APIs will also accept Authorization token from cookie
    'AUTH_COOKIE_ENABLED' => filter_var($_ENV['AUTH_COOKIE_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    'AUTH_COOKIE_NAME'    => $_ENV['AUTH_COOKIE_NAME']    ?? 'auth_token',
    // Node.js Socket.io server URL
    'NODE_SERVER_URL'     => $_ENV['NODE_SERVER_URL']     ?? 'http://localhost:4000',
    // Email configuration
    'EMAIL_FROM'          => $_ENV['EMAIL_FROM']          ?? 'noreply@complaint-system.local',
    'EMAIL_FROM_NAME'     => $_ENV['EMAIL_FROM_NAME']     ?? 'E-Complaint System',
    // SMS/Twilio configuration
    'TWILIO_ACCOUNT_SID'  => $_ENV['TWILIO_ACCOUNT_SID']  ?? '',
    'TWILIO_AUTH_TOKEN'   => $_ENV['TWILIO_AUTH_TOKEN']   ?? '',
    'TWILIO_PHONE_NUMBER' => $_ENV['TWILIO_PHONE_NUMBER']  ?? '',
    'SMS_COUNTRY_CODE'    => $_ENV['SMS_COUNTRY_CODE']    ?? '+1',
];

