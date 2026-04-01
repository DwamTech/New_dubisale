<?php

namespace App\Providers;

use App\Events\BackupCreated;
use App\Events\BackupDeleted;
use App\Events\BackupFailed;
use App\Events\BackupRestored;
use App\Listeners\LogBackupActivity;
use App\Listeners\NotifyAdminOfBackupFailure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Rate limiters
        RateLimiter::for('auth-resend', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('auth-verify', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Backup event listeners
        Event::subscribe(LogBackupActivity::class);
        Event::listen(BackupFailed::class, NotifyAdminOfBackupFailure::class);
    }
}
