<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // 3 resend attempts per minute per IP
        RateLimiter::for('auth-resend', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // 5 verify attempts per minute per IP
        RateLimiter::for('auth-verify', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
