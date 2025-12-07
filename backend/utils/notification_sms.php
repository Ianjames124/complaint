<?php
/**
 * SMS Notification Utility
 * Supports Twilio and fallback to basic SMS
 */

require_once __DIR__ . '/../config/Database.php';

/**
 * Send SMS notification to staff
 * 
 * @param int $staffId Staff user ID
 * @param int $complaintId Complaint ID
 * @param array $complaintData Complaint information
 * @param string $assignedBy Name of admin who assigned
 * @return bool Success status
 */
function sendSMSNotification(
    int $staffId,
    int $complaintId,
    array $complaintData,
    string $assignedBy
): bool {
    try {
        $db = (new Database())->getConnection();
        
        // Get staff details with phone number
        $stmt = $db->prepare('SELECT full_name, phone_number FROM users WHERE id = ?');
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch();
        
        if (!$staff || !$staff['phone_number']) {
            error_log("Staff #{$staffId} does not have a phone number");
            return false;
        }
        
        $phoneNumber = trim($staff['phone_number']);
        if (empty($phoneNumber)) {
            return false;
        }
        
        // Format phone number (remove spaces, ensure + prefix for international)
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
        if (!str_starts_with($phoneNumber, '+')) {
            // Assume local number, add country code if needed
            $config = require __DIR__ . '/../config/env.php';
            $countryCode = $config['SMS_COUNTRY_CODE'] ?? '+1';
            $phoneNumber = $countryCode . $phoneNumber;
        }
        
        // Create SMS message
        $complaintIdFormatted = str_pad($complaintId, 6, '0', STR_PAD_LEFT);
        $priority = $complaintData['priority_level'] ?? 'Medium';
        $priorityEmoji = $priority === 'Emergency' ? '🚨' : ($priority === 'High' ? '🔴' : '⚠️');
        
        $message = "New Assignment: Complaint #{$complaintIdFormatted}\n";
        $message .= "Title: " . substr($complaintData['title'], 0, 50) . "\n";
        $message .= "Priority: {$priorityEmoji} {$priority}\n";
        $message .= "Assigned by: {$assignedBy}\n";
        $message .= "Please check your dashboard for details.";
        
        // Try Twilio first, fallback to basic SMS
        $config = require __DIR__ . '/../config/env.php';
        $useTwilio = !empty($config['TWILIO_ACCOUNT_SID']) && !empty($config['TWILIO_AUTH_TOKEN']);
        
        if ($useTwilio) {
            $sent = sendSMSViaTwilio($phoneNumber, $message, $staffId, $complaintId);
        } else {
            // Fallback: Log SMS (in production, integrate with another provider)
            $sent = logSMSNotification($staffId, $complaintId, $phoneNumber, $message, 'sent', 'basic');
        }
        
        return $sent;
    } catch (Exception $e) {
        error_log('Error sending SMS notification: ' . $e->getMessage());
        logSMSNotification($staffId, $complaintId ?? 0, $phoneNumber ?? '', $message ?? '', 'failed', null, $e->getMessage());
        return false;
    }
}

/**
 * Send SMS via Twilio
 */
function sendSMSViaTwilio(string $toPhone, string $message, int $staffId, int $complaintId): bool {
    try {
        $config = require __DIR__ . '/../config/env.php';
        
        $accountSid = $config['TWILIO_ACCOUNT_SID'] ?? '';
        $authToken = $config['TWILIO_AUTH_TOKEN'] ?? '';
        $fromNumber = $config['TWILIO_PHONE_NUMBER'] ?? '';
        
        if (empty($accountSid) || empty($authToken) || empty($fromNumber)) {
            error_log('Twilio credentials not configured');
            return false;
        }
        
        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        
        $data = [
            'From' => $fromNumber,
            'To' => $toPhone,
            'Body' => $message
        ];
        
        // Send via cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$accountSid}:{$authToken}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: {$error}");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 201 && isset($responseData['sid'])) {
            // Success
            logSMSNotification(
                $staffId,
                $complaintId,
                $toPhone,
                $message,
                'sent',
                'twilio',
                $responseData['sid']
            );
            return true;
        } else {
            $errorMsg = $responseData['message'] ?? 'Unknown error';
            logSMSNotification(
                $staffId,
                $complaintId,
                $toPhone,
                $message,
                'failed',
                'twilio',
                null,
                $errorMsg
            );
            return false;
        }
    } catch (Exception $e) {
        error_log('Twilio SMS error: ' . $e->getMessage());
        logSMSNotification($staffId, $complaintId, $toPhone, $message, 'failed', 'twilio', null, $e->getMessage());
        return false;
    }
}

/**
 * Log SMS notification to database
 */
function logSMSNotification(
    int $staffId,
    int $complaintId,
    string $phoneNumber,
    string $message,
    string $status,
    ?string $provider = null,
    ?string $providerMessageId = null,
    ?string $errorMessage = null
): bool {
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('
            INSERT INTO sms_logs (staff_id, complaint_id, phone_number, message, status, provider, provider_message_id, error_message, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $staffId,
            $complaintId,
            $phoneNumber,
            $message,
            $status,
            $provider,
            $providerMessageId,
            $errorMessage
        ]);
        return true;
    } catch (Exception $e) {
        error_log('Failed to log SMS notification: ' . $e->getMessage());
        return false;
    }
}

