# Backup System - Command Reference

Quick reference for all backup system commands and API calls.

---

## API Endpoints

Base URL: `http://127.0.0.1:8000/api/admin/backups`

All endpoints require:
- Authentication: `Authorization: Bearer {token}`
- Admin role: User must have `isAdmin()` return true

---

## 1. Run Diagnostics

Check system health before creating backups.

```bash
GET /api/admin/backups/diagnostics
```

**Response**:
```json
{
    "ok": true,
    "message": "Backup diagnostics completed successfully",
    "data": {
        "status": "healthy|warning|critical",
        "summary": {
            "issues": 0,
            "warnings": 0,
            "info": 5
        },
        "issues": [],
        "warnings": [],
        "info": [
            {
                "code": "mysqldump_ok",
                "message": "mysqldump is available",
                "details": { "path": "C:\\xampp\\mysql\\bin\\mysqldump.exe" }
            }
        ]
    }
}
```

---

## 2. Create Database Backup

Backup only the database.

```bash
POST /api/admin/backups
Content-Type: application/json

{
    "type": "db"
}
```

**Response**:
```json
{
    "ok": true,
    "message": "Backup created successfully",
    "data": {
        "id": 1,
        "file_name": "backup_db_2026_04_01_120000",
        "type": "db",
        "status": "success",
        "size": "2.5 MB",
        "duration": "5s",
        "created_by": "Admin User",
        "created_at": "2026-04-01T12:00:00+00:00",
        "completed_at": "2026-04-01T12:00:05+00:00"
    }
}
```

---

## 3. Create Files Backup

Backup only storage files.

```bash
POST /api/admin/backups
Content-Type: application/json

{
    "type": "files"
}
```

---

## 4. Create Full Backup

Backup both database and files.

```bash
POST /api/admin/backups
Content-Type: application/json

{
    "type": "full"
}
```

---

## 5. List All Backups

Get paginated list of all backups.

```bash
GET /api/admin/backups
```

**Optional Query Parameters**:
- `status` - Filter by status: `success`, `failed`, `pending`
- `type` - Filter by type: `db`, `files`, `full`
- `days` - Show backups from last N days
- `per_page` - Items per page (default: 15)

**Example**:
```bash
GET /api/admin/backups?status=success&type=full&per_page=20
```

**Response**:
```json
{
    "ok": true,
    "message": "Backups fetched successfully",
    "data": [
        {
            "id": 1,
            "file_name": "backup_full_2026_04_01_120000",
            "type": "full",
            "status": "success",
            "size": 15728640,
            "created_by": 1,
            "created_at": "2026-04-01T12:00:00+00:00",
            "completed_at": "2026-04-01T12:00:30+00:00",
            "creator": {
                "id": 1,
                "name": "Admin User"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 10,
        "last_page": 1
    }
}
```

---

## 6. Get Backup History

Get detailed backup history with pagination.

```bash
GET /api/admin/backups/history?per_page=50
```

---

## 7. Restore Backup

Restore a backup by ID.

⚠️ **WARNING**: This will overwrite current data!

```bash
POST /api/admin/backups/{id}/restore
```

**Example**:
```bash
POST /api/admin/backups/1/restore
```

**Response**:
```json
{
    "ok": true,
    "message": "Backup restored successfully"
}
```

---

## 8. Download Backup

Download a backup file.

```bash
GET /api/admin/backups/{id}/download
```

**Example**:
```bash
GET /api/admin/backups/1/download
```

**Response**: Binary file download

---

## 9. Delete Backup

Delete a backup by ID.

```bash
DELETE /api/admin/backups/{id}
```

**Example**:
```bash
DELETE /api/admin/backups/1
```

**Response**:
```json
{
    "ok": true,
    "message": "Backup deleted successfully"
}
```

---

## 10. Get Statistics

Get backup system statistics.

```bash
GET /api/admin/backups/statistics
```

**Response**:
```json
{
    "ok": true,
    "message": "Backup statistics fetched successfully",
    "data": {
        "total_backups": 25,
        "successful": 23,
        "failed": 2,
        "pending": 0,
        "total_size": "150.5 MB",
        "oldest_backup": "2026-03-01T00:00:00+00:00",
        "newest_backup": "2026-04-01T12:00:00+00:00",
        "by_type": {
            "db": 10,
            "files": 8,
            "full": 5
        }
    }
}
```

---

## 11. Auto-Fix Issues

Automatically fix common issues (orphaned records, stuck backups).

```bash
POST /api/admin/backups/diagnostics/fix
```

**Response**:
```json
{
    "ok": true,
    "message": "Auto-fix completed successfully",
    "data": {
        "orphaned_records_fixed": 2,
        "stuck_backups_fixed": 1,
        "total_fixed": 3
    }
}
```

---

## Laravel Artisan Commands

### Run Migrations

```bash
php artisan migrate
```

### Check Database Connection

```bash
php artisan tinker
>>> DB::connection()->getPdo()
>>> \App\Models\BackupHistory::count()
```

### Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### View Logs

```bash
# Windows
type storage\logs\laravel.log

# Linux/Mac
tail -f storage/logs/laravel.log
```

---

## Manual mysqldump Commands

### Test mysqldump

```bash
# Windows (XAMPP)
C:\xampp\mysql\bin\mysqldump.exe --version

# Test backup
C:\xampp\mysql\bin\mysqldump.exe --user=root --host=127.0.0.1 nas_dubisale > test_backup.sql
```

### Test mysql Import

```bash
# Windows (XAMPP)
C:\xampp\mysql\bin\mysql.exe --user=root --host=127.0.0.1 nas_dubisale < test_backup.sql
```

---

## File Locations

### Backup Storage
```
storage/app/private/backups/YYYY/MM/
```

### Logs
```
storage/logs/laravel.log
```

### Configuration
```
.env
config/database.php
config/filesystems.php
```

---

## Testing Workflow

### 1. Initial Setup
```bash
# Run migrations
php artisan migrate

# Check diagnostics
GET /api/admin/backups/diagnostics
```

### 2. Create Test Backups
```bash
# Database backup
POST /api/admin/backups
{ "type": "db" }

# Files backup
POST /api/admin/backups
{ "type": "files" }

# Full backup
POST /api/admin/backups
{ "type": "full" }
```

### 3. Verify Backups
```bash
# List all backups
GET /api/admin/backups

# Check statistics
GET /api/admin/backups/statistics

# Verify files exist
dir storage\app\private\backups\2026\04
```

### 4. Test Restore (Development Only!)
```bash
# Restore a backup
POST /api/admin/backups/1/restore
```

### 5. Cleanup
```bash
# Delete test backups
DELETE /api/admin/backups/1
DELETE /api/admin/backups/2
DELETE /api/admin/backups/3
```

---

## Error Responses

### 400 Bad Request
```json
{
    "ok": false,
    "message": "Invalid request",
    "error": "Validation error details"
}
```

### 404 Not Found
```json
{
    "ok": false,
    "message": "Backup not found"
}
```

### 422 Unprocessable Entity
```json
{
    "ok": false,
    "message": "Invalid backup type",
    "error": "Type must be one of: db, files, full"
}
```

### 500 Internal Server Error
```json
{
    "ok": false,
    "message": "Failed to create backup",
    "error": "Database backup failed: mysqldump not found"
}
```

---

## Quick Reference

| Action | Method | Endpoint |
|--------|--------|----------|
| List backups | GET | `/api/admin/backups` |
| Create backup | POST | `/api/admin/backups` |
| Restore backup | POST | `/api/admin/backups/{id}/restore` |
| Delete backup | DELETE | `/api/admin/backups/{id}` |
| Download backup | GET | `/api/admin/backups/{id}/download` |
| Get history | GET | `/api/admin/backups/history` |
| Run diagnostics | GET | `/api/admin/backups/diagnostics` |
| Get statistics | GET | `/api/admin/backups/statistics` |
| Auto-fix issues | POST | `/api/admin/backups/diagnostics/fix` |

---

## Postman Collection

Import this JSON into Postman for easy testing:

```json
{
    "info": {
        "name": "Laravel Backup System",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "item": [
        {
            "name": "Diagnostics",
            "request": {
                "method": "GET",
                "url": "{{base_url}}/api/admin/backups/diagnostics"
            }
        },
        {
            "name": "Create Database Backup",
            "request": {
                "method": "POST",
                "url": "{{base_url}}/api/admin/backups",
                "body": {
                    "mode": "raw",
                    "raw": "{\"type\": \"db\"}"
                }
            }
        },
        {
            "name": "Create Full Backup",
            "request": {
                "method": "POST",
                "url": "{{base_url}}/api/admin/backups",
                "body": {
                    "mode": "raw",
                    "raw": "{\"type\": \"full\"}"
                }
            }
        },
        {
            "name": "List Backups",
            "request": {
                "method": "GET",
                "url": "{{base_url}}/api/admin/backups"
            }
        },
        {
            "name": "Restore Backup",
            "request": {
                "method": "POST",
                "url": "{{base_url}}/api/admin/backups/1/restore"
            }
        }
    ],
    "variable": [
        {
            "key": "base_url",
            "value": "http://127.0.0.1:8000"
        }
    ]
}
```

---

## Need Help?

1. **Check logs**: `storage/logs/laravel.log`
2. **Run diagnostics**: `GET /api/admin/backups/diagnostics`
3. **Read guides**:
   - `BACKUP_QUICK_FIX.md` - Step-by-step testing
   - `BACKUP_TROUBLESHOOTING.md` - Common issues
   - `BACKUP_FIXES_SUMMARY.md` - What was fixed
