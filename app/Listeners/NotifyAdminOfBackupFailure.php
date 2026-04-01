<?php

namespace App\Listeners;

use App\Events\BackupFailed;
use Illuminate\Support\Facades\Log;

class NotifyAdminOfBackupFailure
{
    public function handle(BackupFailed $event): void
    {
        // Send notification to admin users
        // Example: Send email, Slack notification, or push notification
        
        Log::critical('backup_failed_notification', [
            'backup_id'    => $event->backup->id,
            'type'         => $event->backup->type,
            'error'        => $event->exception->getMessage(),
            'created_by'   => $event->backup->created_by,
        ]);

        // TODO: Implement actual notification logic
        // Example:
        // Notification::send(
        //     User::where('role', 'admin')->get(),
        //     new BackupFailedNotification($event->backup)
        // );
    }
}
