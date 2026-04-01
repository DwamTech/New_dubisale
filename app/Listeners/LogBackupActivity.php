<?php

namespace App\Listeners;

use App\Events\BackupCreated;
use App\Events\BackupDeleted;
use App\Events\BackupFailed;
use App\Events\BackupRestored;
use Illuminate\Support\Facades\Log;

class LogBackupActivity
{
    public function handleCreated(BackupCreated $event): void
    {
        Log::info('backup_created_event', [
            'backup_id'  => $event->backup->id,
            'type'       => $event->backup->type,
            'status'     => $event->backup->status,
            'size'       => $event->backup->getHumanReadableSize(),
            'created_by' => $event->backup->creator?->name,
        ]);
    }

    public function handleRestored(BackupRestored $event): void
    {
        Log::warning('backup_restored_event', [
            'backup_id'   => $event->backup->id,
            'type'        => $event->backup->type,
            'restored_by' => $event->restoredBy->name,
            'restored_at' => now()->toIso8601String(),
        ]);
    }

    public function handleDeleted(BackupDeleted $event): void
    {
        Log::info('backup_deleted_event', [
            'backup_id'  => $event->backup->id,
            'file_name'  => $event->backup->file_name,
            'deleted_by' => $event->deletedBy->name,
            'deleted_at' => now()->toIso8601String(),
        ]);
    }

    public function handleFailed(BackupFailed $event): void
    {
        Log::error('backup_failed_event', [
            'backup_id'    => $event->backup->id,
            'type'         => $event->backup->type,
            'error'        => $event->exception->getMessage(),
            'trace'        => $event->exception->getTraceAsString(),
        ]);
    }

    public function subscribe($events): array
    {
        return [
            BackupCreated::class  => 'handleCreated',
            BackupRestored::class => 'handleRestored',
            BackupDeleted::class  => 'handleDeleted',
            BackupFailed::class   => 'handleFailed',
        ];
    }
}
