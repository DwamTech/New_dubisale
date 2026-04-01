# Backup System Troubleshooting Guide

## Recent Fixes Applied

### 1. Full Backup Creation Logic
**Problem**: The full backup was creating separate files and trying to merge them incorrectly.

**Fix**: Refactored `createFullBackup()` to:
- Create database backup first
- Verify database backup file exists
- Create a single ZIP containing both database and storage files
- Add proper validation at each step

### 2. Restore Logic for Full Backups
**Problem**: The restore process didn't properly extract and handle files from the full backup ZIP.

**Fix**: Updated `restoreFullBackup()` to:
- Extract the full backup ZIP to a temporary directory
- Find and restore the database backup from the `database/` folder
- Copy storage files from the `storage/` folder
- Clean up temporary files after restore

### 3. File Validation
**Problem**: Backup files could be created empty or corrupted without detection.

**Fix**: Added validation checks:
- Verify files exist after creation
- Check file size is not zero
- Throw descriptive errors if validation fails

---

## Testing Steps

### Step 1: Test Database Backup (Simplest)
```bash
# Create a database-only backup
POST http://127.0.0.1:8000/api/admin/backups
{
    "type": "db"
}
```

**Expected Result**:
- Status: 201 Created
- Response includes backup ID and file name
- File created at: `storage/app/private/backups/YYYY/MM/backup_db_YYYY_MM_DD_HHMMSS.sql.gz`

**If this fails**:
- Check `storage/logs/laravel.log` for detailed error
- Verify mysqldump is accessible (see Windows Setup section)

### Step 2: Test Files Backup
```bash
# Create a files-only backup
POST http://127.0.0.1:8000/api/admin/backups
{
    "type": "files"
}
```

**Expected Result**:
- Status: 201 Created
- ZIP file created containing storage/app/public contents

**If this fails**:
- Check if `storage/app/public` directory exists
- Verify PHP has ZipArchive extension enabled

### Step 3: Test Full Backup
```bash
# Create a full backup (database + files)
POST http://127.0.0.1:8000/api/admin/backups
{
    "type": "full"
}
```

**Expected Result**:
- Status: 201 Created
- ZIP file created containing:
  - `database/backup_db_*.sql.gz`
  - `storage/` folder with all files

### Step 4: Test Restore
```bash
# Restore a backup
POST http://127.0.0.1:8000/api/admin/backups/{id}/restore
```

**Expected Result**:
- Status: 200 OK
- Database and/or files restored successfully

---

## Common Issues and Solutions

### Issue 1: "Database backup failed"
**Symptoms**: Empty error message or "mysqldump not found"

**Solutions**:
1. **Verify mysqldump is accessible**:
   ```bash
   # In terminal
   C:\xampp\mysql\bin\mysqldump.exe --version
   ```

2. **Check database credentials**:
   - Open `.env` file
   - Verify `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` are correct
   - For XAMPP, password is usually empty

3. **Test mysqldump manually**:
   ```bash
   C:\xampp\mysql\bin\mysqldump.exe --user=root --host=127.0.0.1 nas_dubisale > test_backup.sql
   ```

### Issue 2: "Failed to open zip archive"
**Symptoms**: Error when trying to restore a backup

**Solutions**:
1. **Check if backup file exists**:
   ```bash
   # Navigate to backup directory
   cd storage/app/private/backups/2026/04
   dir
   ```

2. **Verify file is not corrupted**:
   - Try opening the ZIP file with WinRAR or 7-Zip
   - Check file size (should not be 0 bytes)

3. **Check file permissions**:
   - Ensure PHP has read/write access to storage directory
   - Run: `php artisan storage:link`

### Issue 3: "Backup file is empty"
**Symptoms**: Backup created but file size is 0 bytes

**Solutions**:
1. **Check storage directory permissions**:
   ```bash
   # Ensure storage directories are writable
   chmod -R 775 storage
   ```

2. **Verify disk space**:
   - Check if drive has enough space
   - Database backups can be large

3. **Check PHP memory limit**:
   - Edit `php.ini`
   - Increase `memory_limit` to at least 512M

### Issue 4: "Table 'backup_histories' doesn't exist"
**Symptoms**: Error when creating backup

**Solution**:
```bash
# Run migrations
php artisan migrate

# Verify table exists
php artisan tinker
>>> \App\Models\BackupHistory::count()
```

---

## Diagnostics Endpoint

Use the diagnostics endpoint to check system health:

```bash
GET http://127.0.0.1:8000/api/admin/backups/diagnostics
```

**Response includes**:
- PHP version and extensions
- Database connectivity
- Storage permissions
- mysqldump availability
- Disk space
- Recent backup statistics

---

## Windows-Specific Setup

### XAMPP Configuration

1. **Verify MySQL is running**:
   - Open XAMPP Control Panel
   - Ensure MySQL is started (green indicator)

2. **Add mysqldump to PATH** (optional):
   ```bash
   # Add to system PATH
   C:\xampp\mysql\bin
   ```

3. **Test database connection**:
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo()
   ```

### Common XAMPP Paths
- MySQL bin: `C:\xampp\mysql\bin\`
- mysqldump: `C:\xampp\mysql\bin\mysqldump.exe`
- mysql: `C:\xampp\mysql\bin\mysql.exe`

---

## Backup File Structure

### Database Backup (`type: "db"`)
```
storage/app/private/backups/YYYY/MM/
└── backup_db_YYYY_MM_DD_HHMMSS.sql.gz
```

### Files Backup (`type: "files"`)
```
storage/app/private/backups/YYYY/MM/
└── backup_files_YYYY_MM_DD_HHMMSS.zip
    └── storage/
        └── (all files from storage/app/public)
```

### Full Backup (`type: "full"`)
```
storage/app/private/backups/YYYY/MM/
└── backup_full_YYYY_MM_DD_HHMMSS.zip
    ├── database/
    │   └── backup_db_*.sql.gz
    └── storage/
        └── (all files from storage/app/public)
```

---

## Next Steps

1. **Delete old failed backups**:
   ```bash
   # List failed backups
   GET http://127.0.0.1:8000/api/admin/backups?status=failed
   
   # Delete each one
   DELETE http://127.0.0.1:8000/api/admin/backups/{id}
   ```

2. **Try creating a new database backup**:
   ```bash
   POST http://127.0.0.1:8000/api/admin/backups
   {
       "type": "db"
   }
   ```

3. **Check the logs**:
   ```bash
   # View recent logs
   tail -f storage/logs/laravel.log
   ```

4. **Run diagnostics**:
   ```bash
   GET http://127.0.0.1:8000/api/admin/backups/diagnostics
   ```

---

## Support

If issues persist:
1. Check `storage/logs/laravel.log` for detailed errors
2. Run diagnostics endpoint
3. Verify all prerequisites are met
4. Test mysqldump command manually
