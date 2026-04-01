<?php

namespace App\Events;

use App\Models\BackupHistory;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupRestored
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BackupHistory $backup,
        public User $restoredBy
    ) {}
}
