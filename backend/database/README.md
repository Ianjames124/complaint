# Database Schema Files

This directory contains the complete database schema and migration files for the E-Complaint & Request System.

## Files Overview

### 1. `complete_schema.sql` ⭐ **START HERE FOR NEW INSTALLATIONS**
Complete database schema for fresh installations. Creates all 15 tables with proper relationships, indexes, and constraints.

**Use when:** Setting up a new database from scratch.

**Run:**
```bash
mysql -u root -p complaint_db < backend/database/complete_schema.sql
```

---

### 2. `migration_safe.sql` ⭐ **USE FOR EXISTING DATABASES**
Safe migration script that updates existing tables and adds missing ones. Uses stored procedures to check for existing columns/indexes before adding them.

**Use when:** You already have a database with some tables and need to add missing columns/tables.

**Run:**
```bash
mysql -u root -p complaint_db < backend/database/migration_safe.sql
```

**Note:** This script includes table creation statements. For full table definitions, also reference `complete_schema.sql`.

---

### 3. `migration_to_complete_schema.sql`
Alternative migration script (may have compatibility issues with older MySQL versions).

**Use when:** `migration_safe.sql` doesn't work (rare).

---

### 4. `seed_data.sql`
Sample data for testing and development.

**Use when:** You need sample data for testing.

**Run:**
```bash
mysql -u root -p complaint_db < backend/database/seed_data.sql
```

**Warning:** Only use in development/test environments!

---

### 5. `SCHEMA_DOCUMENTATION.md`
Complete documentation of all tables, columns, relationships, and indexes.

**Use when:** You need to understand the database structure.

---

### 6. `INSTALLATION_GUIDE.md`
Step-by-step installation and migration instructions.

**Use when:** You need detailed installation steps or troubleshooting help.

---

## Quick Start Guide

### For New Installation

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS complaint_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Run complete schema
mysql -u root -p complaint_db < backend/database/complete_schema.sql

# 3. (Optional) Add sample data
mysql -u root -p complaint_db < backend/database/seed_data.sql
```

### For Existing Database

```bash
# Run safe migration
mysql -u root -p complaint_db < backend/database/migration_safe.sql
```

---

## Database Structure

### Core Tables (5)
1. `departments` - Department information
2. `users` - All system users (admin, staff, citizens)
3. `complaints` - Complaints and requests
4. `complaint_files` - File attachments
5. `staff_assignments` - Staff assignment history

### Tracking Tables (4)
6. `status_updates` - Status change history
7. `assignment_logs` - Detailed assignment logs
8. `priority_change_logs` - Priority change history
9. `sla_logs` - SLA status change history

### Notification Tables (3)
10. `notifications` - User notifications (email, SMS, in-app, real-time)
11. `sms_logs` - SMS notification logs
12. `email_logs` - Email notification logs

### System Tables (3)
13. `audit_logs` - System-wide audit log
14. `auto_assign_settings` - Auto-assignment configuration
15. `rate_limits` - API rate limiting records

**Total: 15 tables**

---

## Features Supported

✅ User management (admin, staff, citizen roles)  
✅ Department organization  
✅ Complaint/request submission and tracking  
✅ Priority levels (Low, Medium, High, Emergency)  
✅ SLA management with status tracking  
✅ Staff assignment (auto/manual)  
✅ Status updates and history  
✅ File attachments  
✅ Audit logging  
✅ Notifications (email, SMS, in-app, real-time)  
✅ Rate limiting  
✅ Analytics support (indexed for performance)  

---

## Requirements

- **MySQL Version:** 5.7+ (for JSON support) or MariaDB 10.2+
- **Character Set:** utf8mb4
- **Collation:** utf8mb4_unicode_ci
- **Storage Engine:** InnoDB (required for foreign keys)

---

## Verification

After installation, verify the schema:

```sql
-- Check all tables exist
SHOW TABLES;
-- Should show 15 tables

-- Check users table
DESCRIBE users;
-- Should show all columns including department_id, phone_number, etc.

-- Check complaints table
DESCRIBE complaints;
-- Should show priority_level, department_id, staff_id, sla_due_at, sla_status

-- Check foreign keys
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'complaint_db'
    AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;
```

---

## Troubleshooting

### Common Issues

1. **"Column already exists"** - Column was already added. Safe to ignore or skip that statement.

2. **"Foreign key constraint fails"** - Check if referenced table/column exists and data types match.

3. **"Unknown column"** - Column doesn't exist. Use `migration_safe.sql` which adds it safely.

4. **"Duplicate key name"** - Index already exists. Safe to ignore.

5. **"JSON type not supported"** - Upgrade to MySQL 5.7+ or MariaDB 10.2+

### Getting Help

1. Check `INSTALLATION_GUIDE.md` for detailed steps
2. Review `SCHEMA_DOCUMENTATION.md` for table structures
3. Check MySQL error logs
4. Verify MySQL version: `SELECT VERSION();`

---

## Maintenance

### Regular Cleanup

```sql
-- Clean old rate limit records (run hourly via cron)
DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);

-- Archive old audit logs (run monthly)
-- Consider moving logs older than 1 year to archive table
```

### Backup

```bash
# Full database backup
mysqldump -u root -p complaint_db > backup_$(date +%Y%m%d).sql

# Restore
mysql -u root -p complaint_db < backup_20250115.sql
```

---

## Support

For database-related issues:
1. Review the documentation files
2. Check MySQL error logs
3. Verify schema matches documentation
4. Test with sample data from `seed_data.sql`

---

**Last Updated:** January 2025  
**Schema Version:** 1.0  
**Status:** Production-Ready ✅

