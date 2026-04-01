<?php

namespace App\Events;

use App\Models\BackupHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupHistory $backup,
        public \Throwable $exception
    ) {}
}
