<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpVerification extends Model
{
    protected $fillable = [
        'phone', 'type', 'code', 'payload',
        'status', 'attempts', 'expires_at',
        'verified_at', 'last_sent_at', 'ip_address',
    ];

    protected $casts = [
        'payload'     => 'array',
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
        'last_sent_at'=> 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasExceededAttempts(int $max): bool
    {
        return $this->attempts >= $max;
    }

    public function markAsVerified(): void
    {
        $this->update(['status' => 'verified', 'verified_at' => now()]);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function canResend(int $cooldownSeconds = 60): bool
    {
        if (!$this->last_sent_at) return true;
        return $this->last_sent_at->addSeconds($cooldownSeconds)->isPast();
    }

    public function remainingCooldown(int $cooldownSeconds = 60): int
    {
        if (!$this->last_sent_at) return 0;
        $remaining = $cooldownSeconds - now()->diffInSeconds($this->last_sent_at, false);
        return max(0, (int) $remaining);
    }
}
