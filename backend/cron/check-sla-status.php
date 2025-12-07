<?php
/**
 * SLA Status Checker Cron Job
 * 
 * This script should be run periodically (every 5-15 minutes) via cron
 * to check and update SLA statuses for all complaints.
 * 
 * Example cron entry (every 10 minutes):
 * */10 * * * * /usr/bin/php /path/to/backend/cron/check-sla-status.php
 */

require_once __DIR__ . '/../config/Database.php';

$db = (new Database())->getConnection();

try {
    // Get all active complaints with SLA
    $stmt = $db->prepare('
        SELECT id, priority_level, sla_due_at, sla_status, status
        FROM complaints
        WHERE status IN ("Pending", "Assigned", "In Progress")
        AND sla_due_at IS NOT NULL
    ');
    $stmt->execute();
    $complaints = $stmt->fetchAll();
    
    $updated = 0;
    $now = new DateTime();
    
    foreach ($complaints as $complaint) {
        $slaDueAt = new DateTime($complaint['sla_due_at']);
        $currentStatus = $complaint['sla_status'];
        $newStatus = null;
        
        // Calculate time remaining
        $diff = $now->diff($slaDueAt);
        $hoursRemaining = ($diff->days * 24) + $diff->h;
        
        // Determine new SLA status
        if ($now > $slaDueAt) {
            $newStatus = 'Breached';
        } elseif ($hoursRemaining <= 4) {
            $newStatus = 'Warning';
        } else {
            $newStatus = 'On Time';
        }
        
        // Update if status changed
        if ($newStatus !== $currentStatus) {
            $updateStmt = $db->prepare('UPDATE complaints SET sla_status = ? WHERE id = ?');
            $updateStmt->execute([$newStatus, $complaint['id']]);
            
            // Log the change
            $logStmt = $db->prepare('INSERT INTO sla_logs (complaint_id, old_status, new_status, timestamp, notes) VALUES (?, ?, ?, NOW(), ?)');
            $logStmt->execute([
                $complaint['id'],
                $currentStatus,
                $newStatus,
                "SLA status automatically updated by cron job. Hours remaining: {$hoursRemaining}"
            ]);
            
            $updated++;
            
            // If breached, emit alert (you can add real-time event here)
            if ($newStatus === 'Breached') {
                error_log("SLA BREACHED: Complaint #{$complaint['id']} - Priority: {$complaint['priority_level']}");
            }
        }
    }
    
    echo "SLA check completed. Updated {$updated} complaints.\n";
    
} catch (PDOException $e) {
    error_log('Database error in check-sla-status.php: ' . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    error_log('Error in check-sla-status.php: ' . $e->getMessage());
    exit(1);
}

