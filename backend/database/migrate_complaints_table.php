<?php
/**
 * Migration script to add missing columns to complaints table
 * Run this once to update your database schema
 */

require_once __DIR__ . '/../config/Database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "Starting complaints table migration...\n";
    
    // Check if columns exist and add them if missing
    $columnsQuery = $db->query("SHOW COLUMNS FROM complaints");
    $existingColumns = [];
    while ($row = $columnsQuery->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $row['Field'];
    }
    
    echo "Existing columns: " . implode(', ', $existingColumns) . "\n";
    
    // Add title column if it doesn't exist (migrate from subject if needed)
    if (!in_array('title', $existingColumns)) {
        if (in_array('subject', $existingColumns)) {
            echo "Adding 'title' column and migrating data from 'subject'...\n";
            $db->exec("ALTER TABLE complaints ADD COLUMN title VARCHAR(255) NULL AFTER subject");
            $db->exec("UPDATE complaints SET title = subject WHERE title IS NULL");
            $db->exec("ALTER TABLE complaints MODIFY COLUMN title VARCHAR(255) NOT NULL");
            echo "✓ Title column added and data migrated\n";
        } else {
            echo "Adding 'title' column...\n";
            $db->exec("ALTER TABLE complaints ADD COLUMN title VARCHAR(255) NOT NULL AFTER citizen_id");
            echo "✓ Title column added\n";
        }
    } else {
        echo "✓ Title column already exists\n";
    }
    
    // Add priority_level column if it doesn't exist
    if (!in_array('priority_level', $existingColumns)) {
        echo "Adding 'priority_level' column...\n";
        $db->exec("ALTER TABLE complaints ADD COLUMN priority_level ENUM('Low', 'Medium', 'High', 'Emergency') NOT NULL DEFAULT 'Medium' AFTER status");
        echo "✓ Priority level column added\n";
    } else {
        echo "✓ Priority level column already exists\n";
    }
    
    // Add department_id column if it doesn't exist
    if (!in_array('department_id', $existingColumns)) {
        echo "Adding 'department_id' column...\n";
        $db->exec("ALTER TABLE complaints ADD COLUMN department_id INT NULL AFTER priority_level");
        echo "✓ Department ID column added\n";
    } else {
        echo "✓ Department ID column already exists\n";
    }
    
    // Add staff_id column if it doesn't exist (or use assigned_to if it exists)
    if (!in_array('staff_id', $existingColumns)) {
        if (in_array('assigned_to', $existingColumns)) {
            echo "Adding 'staff_id' column and migrating data from 'assigned_to'...\n";
            $db->exec("ALTER TABLE complaints ADD COLUMN staff_id INT NULL AFTER department_id");
            $db->exec("UPDATE complaints SET staff_id = assigned_to WHERE assigned_to IS NOT NULL");
            echo "✓ Staff ID column added and data migrated\n";
        } else {
            echo "Adding 'staff_id' column...\n";
            $db->exec("ALTER TABLE complaints ADD COLUMN staff_id INT NULL AFTER department_id");
            echo "✓ Staff ID column added\n";
        }
    } else {
        echo "✓ Staff ID column already exists\n";
    }
    
    // Add sla_due_at column if it doesn't exist
    if (!in_array('sla_due_at', $existingColumns)) {
        echo "Adding 'sla_due_at' column...\n";
        $db->exec("ALTER TABLE complaints ADD COLUMN sla_due_at DATETIME NULL AFTER staff_id");
        echo "✓ SLA due at column added\n";
    } else {
        echo "✓ SLA due at column already exists\n";
    }
    
    // Add sla_status column if it doesn't exist
    if (!in_array('sla_status', $existingColumns)) {
        echo "Adding 'sla_status' column...\n";
        $db->exec("ALTER TABLE complaints ADD COLUMN sla_status ENUM('On Time', 'Warning', 'Breached') NOT NULL DEFAULT 'On Time' AFTER sla_due_at");
        echo "✓ SLA status column added\n";
    } else {
        echo "✓ SLA status column already exists\n";
    }
    
    // Update status enum if needed
    echo "Updating status enum values...\n";
    try {
        $db->exec("ALTER TABLE complaints MODIFY COLUMN status ENUM('Pending', 'Assigned', 'In Progress', 'Completed', 'Closed', 'Rejected') NOT NULL DEFAULT 'Pending'");
        echo "✓ Status enum updated\n";
    } catch (PDOException $e) {
        echo "⚠ Status enum update skipped (may already be correct): " . $e->getMessage() . "\n";
    }
    
    // Add indexes if they don't exist
    echo "Adding indexes...\n";
    $indexes = [
        'idx_priority' => 'priority_level',
        'idx_department' => 'department_id',
        'idx_staff' => 'staff_id',
        'idx_sla_due' => 'sla_due_at',
        'idx_sla_status' => 'sla_status'
    ];
    
    $indexQuery = $db->query("SHOW INDEXES FROM complaints");
    $existingIndexes = [];
    while ($row = $indexQuery->fetch(PDO::FETCH_ASSOC)) {
        $existingIndexes[] = $row['Key_name'];
    }
    
    foreach ($indexes as $indexName => $column) {
        if (!in_array($indexName, $existingIndexes)) {
            try {
                $db->exec("CREATE INDEX {$indexName} ON complaints ({$column})");
                echo "✓ Index {$indexName} added\n";
            } catch (PDOException $e) {
                echo "⚠ Index {$indexName} skipped: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✓ Index {$indexName} already exists\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Your complaints table now has all required columns.\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

