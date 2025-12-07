<?php
/**
 * Email Notification Utility
 * Sends HTML emails using PHP mail() function
 * For production, consider using PHPMailer with SMTP
 */

require_once __DIR__ . '/../config/Database.php';

/**
 * Send email notification
 * 
 * @param string $toEmail Recipient email
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param int|null $userId User ID for logging
 * @param int|null $complaintId Complaint ID for logging
 * @param string $emailType Type of email (complaint_received, complaint_assigned, complaint_resolved)
 * @return bool Success status
 */
function sendEmailNotification(
    string $toEmail,
    string $subject,
    string $htmlBody,
    ?int $userId = null,
    ?int $complaintId = null,
    string $emailType = 'general'
): bool {
    try {
        // Try to load config, but don't fail if it doesn't exist
        $config = [];
        $envPath = __DIR__ . '/../config/env.php';
        if (file_exists($envPath)) {
            $config = require $envPath;
        }
        $fromEmail = $config['EMAIL_FROM'] ?? 'noreply@complaint-system.local';
        $fromName = $config['EMAIL_FROM_NAME'] ?? 'E-Complaint System';
        
        // Prepare headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $headersString = implode("\r\n", $headers);
        
        // Send email
        $sent = @mail($toEmail, $subject, $htmlBody, $headersString);
        
        // Log email attempt
        if ($userId || $complaintId) {
            logEmailNotification($toEmail, $subject, $emailType, $sent ? 'sent' : 'failed', $userId, $complaintId);
        }
        
        return $sent;
    } catch (Exception $e) {
        error_log('Email sending error: ' . $e->getMessage());
        if ($userId || $complaintId) {
            logEmailNotification($toEmail, $subject, $emailType, 'failed', $userId, $complaintId, $e->getMessage());
        }
        return false;
    }
}

/**
 * Log email notification to database
 */
function logEmailNotification(
    string $recipientEmail,
    string $subject,
    string $emailType,
    string $status,
    ?int $userId = null,
    ?int $complaintId = null,
    ?string $errorMessage = null
): void {
    try {
        $db = (new Database())->getConnection();
        
        // Check if email_logs table exists before trying to insert
        $checkTable = $db->query("SHOW TABLES LIKE 'email_logs'");
        if ($checkTable && $checkTable->rowCount() > 0) {
            $stmt = $db->prepare('
                INSERT INTO email_logs (user_id, complaint_id, recipient_email, subject, email_type, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $userId,
                $complaintId,
                $recipientEmail,
                $subject,
                $emailType,
                $status,
                $errorMessage
            ]);
        }
        // Silently skip if table doesn't exist - this is not critical
    } catch (Exception $e) {
        // Silently fail - email logging is not critical for complaint creation
        error_log('Failed to log email notification: ' . $e->getMessage());
    } catch (Error $e) {
        // Silently fail for fatal errors too
        error_log('Fatal error logging email notification: ' . $e->getMessage());
    }
}

/**
 * Create HTML email template with Tailwind inline styles
 */
function createEmailTemplate(string $title, string $content, array $data = []): string {
    $appName = $data['app_name'] ?? 'E-Complaint System';
    $footerText = $data['footer_text'] ?? 'This is an automated notification from the E-Complaint System.';
    
    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f3f4f6; padding: 20px;">
        <tr>
            <td align="center">
                <table role="presentation" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); overflow: hidden;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">' . htmlspecialchars($appName) . '</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #1f2937; font-size: 20px; font-weight: 600;">' . htmlspecialchars($title) . '</h2>
                            <div style="color: #4b5563; font-size: 16px; line-height: 1.6;">
                                ' . $content . '
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; background-color: #f9fafb; border-top: 1px solid #e5e7eb; text-align: center;">
                            <p style="margin: 0; color: #6b7280; font-size: 14px;">' . htmlspecialchars($footerText) . '</p>
                            <p style="margin: 10px 0 0 0; color: #9ca3af; font-size: 12px;">© ' . date('Y') . ' ' . htmlspecialchars($appName) . '. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Send "Complaint Received" email to citizen
 */
function sendComplaintReceivedEmail(int $citizenId, int $complaintId, array $complaintData): bool {
    try {
        $db = (new Database())->getConnection();
        
        // Get citizen details
        $stmt = $db->prepare('SELECT full_name, email FROM users WHERE id = ?');
        $stmt->execute([$citizenId]);
        $citizen = $stmt->fetch();
        
        if (!$citizen || !$citizen['email']) {
            return false;
        }
        
        $complaintIdFormatted = str_pad($complaintId, 6, '0', STR_PAD_LEFT);
        $subject = "Complaint #{$complaintIdFormatted} Received - " . htmlspecialchars($complaintData['title']);
        
        $content = '
            <p>Dear ' . htmlspecialchars($citizen['full_name']) . ',</p>
            <p>Thank you for submitting your complaint. We have received it and it is now being processed.</p>
            
            <div style="background-color: #f9fafb; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #1f2937;">Complaint Details:</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Complaint ID:</strong> #' . $complaintIdFormatted . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Title:</strong> ' . htmlspecialchars($complaintData['title']) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Category:</strong> ' . htmlspecialchars($complaintData['category']) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Priority:</strong> ' . htmlspecialchars($complaintData['priority_level'] ?? 'Medium') . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Status:</strong> ' . htmlspecialchars($complaintData['status'] ?? 'Pending') . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Submitted:</strong> ' . date('F j, Y g:i A', strtotime($complaintData['created_at'])) . '</p>
            </div>
            
            <p>We will keep you updated on the progress of your complaint. You can track its status by logging into your account.</p>
            <p>If you have any questions, please don\'t hesitate to contact our support team.</p>
        ';
        
        $htmlBody = createEmailTemplate('Complaint Received', $content, [
            'app_name' => 'E-Complaint System'
        ]);
        
        $sent = sendEmailNotification(
            $citizen['email'],
            $subject,
            $htmlBody,
            $citizenId,
            $complaintId,
            'complaint_received'
        );
        
        // Create in-app notification
        if ($sent) {
            createInAppNotification(
                $citizenId,
                'Complaint Received',
                "Your complaint #{$complaintIdFormatted} has been received and is being processed.",
                $complaintId,
                'complaint_received'
            );
        }
        
        return $sent;
    } catch (Exception $e) {
        error_log('Error sending complaint received email: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send "Complaint Assigned" email to staff
 */
function sendComplaintAssignedEmail(int $staffId, int $complaintId, array $complaintData, string $assignedBy): bool {
    try {
        $db = (new Database())->getConnection();
        
        // Get staff details
        $stmt = $db->prepare('SELECT full_name, email FROM users WHERE id = ?');
        $stmt->execute([$staffId]);
        $staff = $stmt->fetch();
        
        if (!$staff || !$staff['email']) {
            return false;
        }
        
        $complaintIdFormatted = str_pad($complaintId, 6, '0', STR_PAD_LEFT);
        $subject = "New Assignment: Complaint #{$complaintIdFormatted} - " . htmlspecialchars($complaintData['title']);
        
        $priorityBadge = getPriorityBadgeHtml($complaintData['priority_level'] ?? 'Medium');
        
        $content = '
            <p>Dear ' . htmlspecialchars($staff['full_name']) . ',</p>
            <p>A new complaint has been assigned to you for resolution.</p>
            
            <div style="background-color: #f9fafb; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #1f2937;">Assignment Details:</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Complaint ID:</strong> #' . $complaintIdFormatted . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Title:</strong> ' . htmlspecialchars($complaintData['title']) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Category:</strong> ' . htmlspecialchars($complaintData['category']) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Priority:</strong> ' . $priorityBadge . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Assigned By:</strong> ' . htmlspecialchars($assignedBy) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Assigned Date:</strong> ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <p style="background-color: #fef3c7; border: 1px solid #fbbf24; padding: 12px; border-radius: 6px; color: #92400e;">
                <strong>⚠️ Action Required:</strong> Please review and begin working on this complaint as soon as possible.
            </p>
            
            <p>You can access the complaint details by logging into your staff dashboard.</p>
        ';
        
        $htmlBody = createEmailTemplate('New Complaint Assignment', $content, [
            'app_name' => 'E-Complaint System'
        ]);
        
        $sent = sendEmailNotification(
            $staff['email'],
            $subject,
            $htmlBody,
            $staffId,
            $complaintId,
            'complaint_assigned'
        );
        
        // Create in-app notification
        if ($sent) {
            createInAppNotification(
                $staffId,
                'New Assignment',
                "Complaint #{$complaintIdFormatted} has been assigned to you: " . htmlspecialchars($complaintData['title']),
                $complaintId,
                'complaint_assigned'
            );
        }
        
        return $sent;
    } catch (Exception $e) {
        error_log('Error sending complaint assigned email: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send "Complaint Resolved" email to citizen
 */
function sendComplaintResolvedEmail(int $citizenId, int $complaintId, array $complaintData, string $resolvedBy): bool {
    try {
        $db = (new Database())->getConnection();
        
        // Get citizen details
        $stmt = $db->prepare('SELECT full_name, email FROM users WHERE id = ?');
        $stmt->execute([$citizenId]);
        $citizen = $stmt->fetch();
        
        if (!$citizen || !$citizen['email']) {
            return false;
        }
        
        $complaintIdFormatted = str_pad($complaintId, 6, '0', STR_PAD_LEFT);
        $subject = "Complaint #{$complaintIdFormatted} Resolved - " . htmlspecialchars($complaintData['title']);
        
        $content = '
            <p>Dear ' . htmlspecialchars($citizen['full_name']) . ',</p>
            <p>Great news! Your complaint has been resolved.</p>
            
            <div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; font-weight: 600; color: #1f2937;">Complaint Details:</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Complaint ID:</strong> #' . $complaintIdFormatted . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Title:</strong> ' . htmlspecialchars($complaintData['title']) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Status:</strong> <span style="color: #10b981; font-weight: 600;">' . htmlspecialchars($complaintData['status']) . '</span></p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Resolved By:</strong> ' . htmlspecialchars($resolvedBy) . '</p>
                <p style="margin: 5px 0; color: #4b5563;"><strong>Resolved Date:</strong> ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <p>Thank you for using our complaint system. We hope this resolution meets your expectations.</p>
            <p>If you have any feedback or concerns, please don\'t hesitate to contact us.</p>
        ';
        
        $htmlBody = createEmailTemplate('Complaint Resolved', $content, [
            'app_name' => 'E-Complaint System'
        ]);
        
        $sent = sendEmailNotification(
            $citizen['email'],
            $subject,
            $htmlBody,
            $citizenId,
            $complaintId,
            'complaint_resolved'
        );
        
        // Create in-app notification
        if ($sent) {
            createInAppNotification(
                $citizenId,
                'Complaint Resolved',
                "Your complaint #{$complaintIdFormatted} has been resolved: " . htmlspecialchars($complaintData['title']),
                $complaintId,
                'complaint_resolved'
            );
        }
        
        return $sent;
    } catch (Exception $e) {
        error_log('Error sending complaint resolved email: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create in-app notification in database
 */
function createInAppNotification(
    int $userId,
    string $title,
    string $message,
    ?int $complaintId = null,
    ?string $relatedType = null
): bool {
    try {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('
            INSERT INTO notifications (user_id, type, title, message, status, related_complaint_id, related_type, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $userId,
            'in_app',
            $title,
            $message,
            'sent',
            $complaintId,
            $relatedType
        ]);
        return true;
    } catch (Exception $e) {
        error_log('Error creating in-app notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get priority badge HTML for email
 */
function getPriorityBadgeHtml(string $priority): string {
    $colors = [
        'Low' => '#3b82f6',
        'Medium' => '#f59e0b',
        'High' => '#f97316',
        'Emergency' => '#ef4444'
    ];
    $color = $colors[$priority] ?? '#6b7280';
    
    return '<span style="display: inline-block; padding: 4px 12px; background-color: ' . $color . '; color: #ffffff; border-radius: 12px; font-size: 12px; font-weight: 600;">' . htmlspecialchars($priority) . '</span>';
}

