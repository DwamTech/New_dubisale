# Backup System - Logging & Events

## Overview

The Backup System uses a **dual approach** for observability:
1. **Direct Logging**: Immediate logs in `BackupService` for debugging
2. **Event System**: Decoupled events for notifications, webhooks, and side effects

---

## Logging Strategy

### Existing Logs (Already in BackupService)

| Log Level | Event | Location | Context |
|-----------|-------|----------|---------|
| `info` | Backup started | `BackupService::logBackupStart()` | id, type, user |
| `info` | Backup completed | `BackupService::logBackupComplete()` | id, size, duration |
| `error` | Backup failed | `BackupService::logBackupFailure()` | id, error, trace |
| `info` | Restore started | `BackupService::restoreBackup()` | backup_id |
| `info` | Restore completed | `BackupService::restoreBackup()` | backup_id |
| `error` | Restore failed | `BackupService::logRestoreFailure()` | backup_id, error, trace |
| `info` | Backup deleted | `BackupService::deleteBackup()` | id, file_name |
| `error` | Deletion failed | `BackupService::deleteBackup()` | id, error |
| `warning` | Cleanup error | `BackupService::cleanupFailedBackup()` | backup_id, error |

### Log Examples

#### Successful Backup
```
[2026-03-31 14:30:22] local.INFO: backup_started {"id":5,"type":"full","user":1}
[2026-03-31 14:35:10] local.INFO: backup_completed {"id":5,"size":"125.5 MB","duration":"4m 48s"}
```

#### Failed Backup
```
[2026-03-31 14:30:22] local.INFO: backup_started {"id":6,"type":"db","user":1}
[2026-03-31 14:30:45] local.ERROR: backup_failed {"id":6,"error":"mysqldump: command not found","trace":"..."}
```

#### Restore Operation
```
[2026-03-31 15:00:00] local.INFO: restore_started {"backup_id":5}
[2026-03-31 15:05:30] local.INFO: restore_completed {"backup_id":5}
```

---

## Event System

### Events Created

| Event | Dispatched When | Payload |
|-------|----------------|---------|
| `BackupCreated` | Backup completes successfully | `BackupHistory $backup` |
| `BackupFailed` | Backup fails | `BackupHistory $backup`, `Throwable $exception` |
| `BackupRestored` | Backup restored successfully | `BackupHistory $backup`, `User $restoredBy` |
| `BackupDeleted` | Backup deleted | `BackupHistory $backup`, `User $deletedBy` |

### Event Dispatch Locations

```php
// BackupService::markBackupSuccess()
\App\Events\BackupCreated::dispatch($backup);

// BackupService::handleBackupFailure()
\App\Events\BackupFailed::dispatch($backup, $e);

// BackupController::restore()
\App\Events\BackupRestored::dispatch($backup, auth()->user());

// BackupController::destroy()
\App\Events\BackupDeleted::dispatch($backup, auth()->user());
```

---

## Listeners

### 1. LogBackupActivity (Event Subscriber)

**Purpose**: Additional structured logging for all backup events

**Registered in**: `AppServiceProvider::boot()`

**Methods**:
- `handleCreated()` → Logs backup creation with metadata
- `handleRestored()` → Logs restore operation with user info
- `handleDeleted()` → Logs deletion with user info
- `handleFailed()` → Logs failure with exception details

**Example Log Output**:
```
[2026-03-31 14:35:10] local.INFO: backup_created_event {
    "backup_id": 5,
    "type": "full",
    "status": "success",
    "size": "125.5 MB",
    "created_by": "Admin User"
}
```

### 2. NotifyAdminOfBackupFailure

**Purpose**: Send notifications when backups fail

**Registered in**: `AppServiceProvider::boot()`

**Current Implementation**: Logs critical error (placeholder for actual notifications)

**TODO**: Implement actual notification logic:
```php
// Email
Mail::to($admins)->send(new BackupFailedMail($event->backup));

// Slack
Notification::route('slack', config('services.slack.webhook'))
    ->notify(new BackupFailedNotification($event->backup));

// Database notification
Notification::send(
    User::where('role', 'admin')->get(),
    new BackupFailedNotification($event->backup)
);
```

---

## Event Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ BackupService::createBackup()                               │
└─────────────────────────────────────────────────────────────┘
                            ↓
                    ┌───────────────┐
                    │ Success?      │
                    └───────────────┘
                     ↓           ↓
              ┌──────┘           └──────┐
              │                         │
         ✓ Success                  ✗ Failure
              │                         │
              ↓                         ↓
    ┌─────────────────┐       ┌─────────────────┐
    │ BackupCreated   │       │ BackupFailed    │
    │ event           │       │ event           │
    └─────────────────┘       └─────────────────┘
              ↓                         ↓
    ┌─────────────────┐       ┌─────────────────┐
    │ LogBackupActivity│      │ LogBackupActivity│
    │ →handleCreated() │      │ →handleFailed()  │
    └─────────────────┘       └─────────────────┘
                                        ↓
                              ┌─────────────────┐
                              │ NotifyAdminOf   │
                              │ BackupFailure   │
                              └─────────────────┘
```

---

## Extending the System

### Add Email Notifications

1. Create notification class:
```bash
php artisan make:notification BackupFailedNotification
```

2. Update `NotifyAdminOfBackupFailure`:
```php
use Illuminate\Support\Facades\Notification;

public function handle(BackupFailed $event): void
{
    $admins = User::where('role', 'admin')->get();
    Notification::send($admins, new BackupFailedNotification($event->backup));
}
```

### Add Slack Webhook

```php
// config/services.php
'slack' => [
    'webhook' => env('SLACK_BACKUP_WEBHOOK'),
],

// Listener
use Illuminate\Support\Facades\Http;

Http::post(config('services.slack.webhook'), [
    'text' => "⚠️ Backup #{$event->backup->id} failed: {$event->exception->getMessage()}"
]);
```

### Add Database Activity Log

Create a separate `backup_activity_logs` table:
```php
Schema::create('backup_activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('backup_id')->constrained('backup_histories');
    $table->string('action'); // created, restored, deleted, failed
    $table->foreignId('user_id')->nullable()->constrained('users');
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

---

## Monitoring & Alerting

### Log Aggregation

Use Laravel's log channels to send backup logs to external services:

```php
// config/logging.php
'channels' => [
    'backup' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
        'ignore_exceptions' => false,
    ],
],
```

### Metrics

Track backup metrics using events:
```php
// In listener
use Illuminate\Support\Facades\Cache;

Cache::increment('backups.created.count');
Cache::put('backups.last_success', now());
```

### Health Checks

Create a health check endpoint:
```php
Route::get('/health/backups', function () {
    $lastBackup = BackupHistory::successful()->latest()->first();
    $isHealthy = $lastBackup && $lastBackup->created_at->isAfter(now()->subDay());
    
    return response()->json([
        'healthy' => $isHealthy,
        'last_backup' => $lastBackup?->created_at,
    ], $isHealthy ? 200 : 503);
});
```

---

## Summary

✅ **Logging**: Already implemented in `BackupService`  
✅ **Events**: Created for all major operations  
✅ **Listeners**: Registered in `AppServiceProvider`  
✅ **Extensible**: Easy to add notifications, webhooks, metrics  

**Next Steps**:
1. Implement actual notification logic in `NotifyAdminOfBackupFailure`
2. Add Slack/email notifications as needed
3. Set up log aggregation (Papertrail, Logtail, etc.)
4. Create monitoring dashboard for backup health
