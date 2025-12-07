<?php
/**
 * Send real-time event to Node.js Socket.io server
 * 
 * @param string $event Event name (e.g., 'new_complaint', 'assignment_created')
 * @param array $data Event payload data
 * @param array|null $target Optional target specification (type: 'admin'|'staff'|'citizen', id: user_id)
 * @return bool True on success, false on failure
 */
function send_realtime_event(string $event, array $data, ?array $target = null): bool
{
    try {
        // Get config from env.php (don't fail if it doesn't exist)
        $config = [];
        $envPath = __DIR__ . '/../config/env.php';
        if (file_exists($envPath)) {
            $config = require $envPath;
        }
        $nodeServerUrl = $config['NODE_SERVER_URL'] ?? 'http://localhost:4000';
    } catch (Exception $e) {
        error_log('Failed to load env.php in send_realtime_event: ' . $e->getMessage());
        $nodeServerUrl = 'http://localhost:4000';
    } catch (Error $e) {
        error_log('Fatal error loading env.php in send_realtime_event: ' . $e->getMessage());
        $nodeServerUrl = 'http://localhost:4000';
    }
    
    $payload = [
        'event' => $event,
        'data' => $data
    ];
    
    if ($target !== null) {
        $payload['target'] = $target;
    }
    
    try {
        $ch = curl_init($nodeServerUrl . '/emit-event');
        
        if ($ch === false) {
            error_log("Real-time event: Failed to initialize cURL");
            return false;
        }
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 2, // 2 second timeout
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("Real-time event error: $error");
            return false;
        }
        
        if ($httpCode !== 200) {
            error_log("Real-time event HTTP error: $httpCode - $response");
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['success']) && $result['success']) {
            return true;
        }
        
        error_log("Real-time event failed: " . ($result['message'] ?? 'Unknown error'));
        return false;
    } catch (Exception $e) {
        error_log("Real-time event exception: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        error_log("Real-time event fatal error: " . $e->getMessage());
        return false;
    }
}

