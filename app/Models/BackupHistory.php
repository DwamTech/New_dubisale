<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BackupHistory extends Model
{
    protected $fillable = [
        'file_name',
        'file_path',
        'type',
        'status',
        'size',
        'created_by',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'size'         => 'integer',
        'completed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Status Helpers ────────────────────────────────────────────────────────

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // ── Type Helpers ──────────────────────────────────────────────────────────

    public function isFull(): bool
    {
        return $this->type === 'full';
    }

    public function isDatabase(): bool
    {
        return $this->type === 'db';
    }

    public function isFiles(): bool
    {
        return $this->type === 'files';
    }

    // ── File Helpers ──────────────────────────────────────────────────────────

    public function fileExists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }

    public function getFullPath(): string
    {
        return Storage::disk('local')->path($this->file_path);
    }

    public function getDownloadUrl(): string
    {
        return route('admin.backups.download', $this->id);
    }

    public function getHumanReadableSize(): string
    {
        if (!$this->size) {
            return 'N/A';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getDuration(): ?int
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->created_at->diffInSeconds($this->completed_at);
    }

    public function getHumanReadableDuration(): string
    {
        $duration = $this->getDuration();

        if ($duration === null) {
            return 'N/A';
        }

        if ($duration < 60) {
            return $duration . 's';
        }

        $minutes = floor($duration / 60);
        $seconds = $duration % 60;

        return $minutes . 'm ' . $seconds . 's';
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}
