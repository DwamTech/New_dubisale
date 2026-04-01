# Backup System Diagnostics

This document explains the diagnostics feature of the Laravel Backup System.

## Overview

The diagnostics system helps detect and fix common issues with backups, including:
- Missing backup files
- Orphaned database records
- Storage permission issues
- Stuck backups (pending for too long)
- Failed backup patterns
- Disk space issues
- Database connectivity
- mysqldump availability

---

## API Endpoints

### 1. Run Full Diagnostics

**Endpoint:** `GET /admin/backups/diagnostics`

**Authentication:** Required (admin only)

**Response:**
```json
{
  "ok": true,
  "message": "Backup diagnostics completed successfully.",
  "data": {
    "status": "healthy|warning|critical",
    "timestamp": "2026-03-31T12:00:00Z",
    "summary": {
      "issues": 0,
      "warnings": 1,
      "info": 5
    },
    "issues": [
      {
        "code": "storage_not_writable",
        "message": "Cannot write to backup directory",
        "details": {
          "path": "backups",
          "fix": "Run: chmod -R 775 storage/app/backups"
        }
      }
    ],
    "warnings": [
      {
        "code": "orphaned_records",
        "message": "Backup records exist but files are missing",
        "details": {
          "count": 3,
          "ids": [12, 15, 18],
          "fix": "Delete orphaned records or restore files"
        }
      }
    ],
    "info": [
      {
        "code": "storage_ok",
        "message": "Backup directory is writable"
      }
    ],
    "statistics": {
      "total_backups": 45,
      "successful": 42,
      "failed": 2,
      "pending": 1,
      "total_size": "2.5 GB",
      "oldest_backup": "2026-01-15T10:30:00Z",
      "newest_backup": "2026-03-31T08:00:00Z",
      "by_type": {
        "db": 20,
        "files": 15,
        "full": 7
      }
    }
  }
}
```

**Status Levels:**
- `healthy`: No issues or warnings
- `warning`: Some warnings detected but system is operational
- `critical`: Critical issues detected that need immediate attention

---

### 2. Get Statistics Only

**Endpoint:** `GET /admin/backups/statistics`

**Authentication:** Required (admin only)

**Response:**
```json
{
  "ok": true,
  "message": "Backup statistics retrieved successfully.",
  "data": {
    "total_backups": 45,
    "successful": 42,
    "failed": 2,
    "pending": 1,
    "total_size": "2.5 GB",
    "oldest_backup": "2026-01-15T10:30:00Z",
    "newest_backup": "2026-03-31T08:00:00Z",
    "by_type": {
      "db": 20,
      "files": 15,
      "full": 7
    }
  }
}
```

---

### 3. Auto-Fix Common Issues

**Endpoint:** `POST /admin/backups/diagnostics/fix`

**Authentication:** Required (admin only)

**What it fixes:**
- Orphaned records (database records without files)
- Stuck backups (pending for more than 2 hours)

**Response:**
```json
{
  "ok": true,
  "message": "Auto-fix completed successfully.",
  "data": {
    "orphaned_records_fixed": 3,
    "stuck_backups_fixed": 1,
    "total_fixed": 4
  }
}
```

---

## CLI Command

### Run Diagnostics from Terminal

```bash
# Run full diagnostics
php artisan backup:diagnose

# Show statistics only
php artisan backup:diagnose --stats

# Run diagnostics and auto-fix issues
php artisan backup:diagnose --fix
```

**Output Example:**
```
Running backup system diagnostics...

Status: HEALTHY

INFO (5):
  • [storage_ok] Backup directory is writable
  • [database_ok] Database connection successful
  • [mysqldump_ok] mysqldump is available
    - path: /usr/bin/mysqldump
  • [disk_space_ok] Sufficient disk space available
    - free: 45.2 GB
  • [backup_health_ok] Recent successful backup exists
    - last_backup: 2026-03-31 08:00:00
    - hours_ago: 4

STATISTICS:
+----------------+------------------------+
| Metric         | Value                  |
+----------------+------------------------+
| Total Backups  | 45                     |
| Successful     | 42                     |
| Failed         | 2                      |
| Pending        | 1                      |
| Total Size     | 2.5 GB                 |
| Oldest Backup  | 2026-01-15 10:30:00    |
| Newest Backup  | 2026-03-31 08:00:00    |
+----------------+------------------------+

BY TYPE:
+----------+-------+
| Type     | Count |
+----------+-------+
| Database | 20    |
| Files    | 15    |
| Full     | 7     |
+----------+-------+
```

---

## Diagnostic Checks

### 1. Storage Permissions
- Checks if backup directory exists
- Tests write permissions
- **Fix:** `chmod -R 775 storage/app/backups`

### 2. Database Connection
- Verifies database connectivity
- **Fix:** Check `DB_*` credentials in `.env`

### 3. mysqldump Availability
- Checks if mysqldump command is available
- **Fix:** `apt-get install mysql-client` (Ubuntu/Debian)

### 4. Disk Space
- **Critical:** Less than 1GB free
- **Warning:** Less than 5GB free
- **Fix:** Free up disk space or expand storage

### 5. Orphaned Records
- Database records without corresponding files
- **Auto-fix:** Deletes orphaned records
- **Manual fix:** Restore missing files or delete records

### 6. Unrecorded Files
- Backup files without database records
- **Fix:** Delete unrecorded files to free space

### 7. Stuck Backups
- Backups pending for more than 2 hours
- **Auto-fix:** Marks them as failed
- **Manual fix:** Investigate and restart backup process

### 8. Failed Backups
- **Warning:** More than 5 failures in last 7 days
- **Fix:** Check logs for recurring errors

### 9. Backup Health
- **Warning:** Last successful backup is over 7 days old
- **Fix:** Create a new backup

---

## Integration Examples

### Frontend Dashboard

```javascript
// Fetch diagnostics
const response = await fetch('/admin/backups/diagnostics', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'x-lang': 'en'
  }
});

const { data } = await response.json();

// Display status badge
const statusColor = {
  healthy: 'green',
  warning: 'yellow',
  critical: 'red'
}[data.status];

// Show issues count
console.log(`Issues: ${data.summary.issues}`);
console.log(`Warnings: ${data.summary.warnings}`);

// Auto-fix button
if (data.summary.issues > 0 || data.summary.warnings > 0) {
  // Show "Fix Issues" button
  await fetch('/admin/backups/diagnostics/fix', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'x-lang': 'en'
    }
  });
}
```

### Scheduled Monitoring

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Run diagnostics daily and log results
    $schedule->command('backup:diagnose')
        ->daily()
        ->at('06:00');
    
    // Auto-fix issues weekly
    $schedule->command('backup:diagnose --fix')
        ->weekly()
        ->sundays()
        ->at('03:00');
}
```

### Slack/Email Notifications

Create a listener for critical issues:

```php
// In AppServiceProvider or EventServiceProvider
Event::listen(function () {
    $diagnostics = app(BackupDiagnosticsService::class);
    $report = $diagnostics->runDiagnostics();
    
    if ($report['status'] === 'critical') {
        // Send notification
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new BackupCriticalIssue($report));
    }
});
```

---

## Best Practices

1. **Regular Monitoring:** Run diagnostics daily via cron
2. **Auto-fix Safely:** Review auto-fix results before relying on them
3. **Alert on Critical:** Set up notifications for critical status
4. **Disk Space:** Monitor disk space proactively
5. **Test Restores:** Periodically test backup restoration
6. **Log Review:** Check logs when failures are detected

---

## Troubleshooting

### Issue: "mysqldump command not found"
**Solution:** Install MySQL client tools
```bash
# Ubuntu/Debian
sudo apt-get install mysql-client

# CentOS/RHEL
sudo yum install mysql
```

### Issue: "Cannot write to backup directory"
**Solution:** Fix permissions
```bash
sudo chmod -R 775 storage/app/backups
sudo chown -R www-data:www-data storage/app/backups
```

### Issue: "Disk space critical"
**Solution:** Clean old backups or expand storage
```bash
# Delete backups older than 30 days
find storage/app/backups -type f -mtime +30 -delete
```

### Issue: "High failure rate"
**Solution:** Check Laravel logs
```bash
tail -f storage/logs/laravel.log
```

---

## Security Considerations

- Diagnostics endpoint is admin-only
- Auto-fix only performs safe operations (no data deletion without confirmation)
- Sensitive information (credentials, paths) is not exposed in API responses
- All operations are logged for audit trail

---

## Related Documentation

- [BACKUP_AUTHORIZATION.md](BACKUP_AUTHORIZATION.md) - Authorization strategy
- [BACKUP_LOGGING_EVENTS.md](BACKUP_LOGGING_EVENTS.md) - Logging and events
- [BACKWARD_COMPATIBILITY.md](BACKWARD_COMPATIBILITY.md) - Compatibility notes
