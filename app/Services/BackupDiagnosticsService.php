<?php

namespace App\Services;

use App\Models\BackupHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupDiagnosticsService
{
    private const BACKUP_DISK = 'local';
    private const BACKUP_PATH = 'backups';

    private array $issues = [];
    private array $warnings = [];
    private array $info = [];

    /**
     * Run full diagnostics and return report
     */
    public function runDiagnostics(): array
    {
        $this->reset();

        $this->checkPhpExtensions();
        $this->checkStoragePermissions();
        $this->checkDatabaseConnection();
        $this->checkMysqldumpAvailability();
        $this->checkDiskSpace();
        $this->checkOrphanedRecords();
        $this->checkMissingFiles();
        $this->checkStuckBackups();
        $this->checkFailedBackups();
        $this->checkBackupHealth();

        return $this->generateReport();
    }

    /**
     * Check required PHP extensions
     */
    private function checkPhpExtensions(): void
    {
        $required = ['zip', 'zlib', 'pdo_mysql'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (!empty($missing)) {
            $this->addIssue('php_extensions_missing', 'Required PHP extensions are missing', [
                'missing' => $missing,
                'fix' => 'Install missing extensions: ' . implode(', ', $missing),
            ]);
        } else {
            $this->addInfo('php_extensions_ok', 'All required PHP extensions are loaded', [
                'extensions' => $required,
            ]);
        }
    }

    /**
     * Check storage directory permissions
     */
    private function checkStoragePermissions(): void
    {
        $disk = Storage::disk(self::BACKUP_DISK);
        $path = self::BACKUP_PATH;

        try {
            if (!$disk->exists($path)) {
                $this->addIssue('storage_missing', 'Backup directory does not exist', [
                    'path' => $path,
                    'fix' => 'Run: mkdir -p ' . storage_path('app/' . $path),
                ]);
                return;
            }

            // Test write permissions
            $testFile = $path . '/.diagnostic_test';
            $disk->put($testFile, 'test');
            
            if (!$disk->exists($testFile)) {
                $this->addIssue('storage_not_writable', 'Cannot write to backup directory', [
                    'path' => $path,
                    'fix' => 'Run: chmod -R 775 ' . storage_path('app/' . $path),
                ]);
            } else {
                $disk->delete($testFile);
                $this->addInfo('storage_ok', 'Backup directory is writable');
            }
        } catch (\Throwable $e) {
            $this->addIssue('storage_error', 'Storage check failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
            $this->addInfo('database_ok', 'Database connection successful');
        } catch (\Throwable $e) {
            $this->addIssue('database_connection', 'Cannot connect to database', [
                'error' => $e->getMessage(),
                'fix' => 'Check DB_* credentials in .env file',
            ]);
        }
    }

    /**
     * Check if mysqldump is available
     */
    private function checkMysqldumpAvailability(): void
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
            // Check common Windows paths
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
            ];

            $found = false;
            $foundPath = null;

            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $found = true;
                    $foundPath = $path;
                    break;
                }
            }

            if (!$found) {
                // Try 'where' command
                exec('where mysqldump 2>nul', $output, $returnCode);
                if ($returnCode === 0 && !empty($output[0])) {
                    $found = true;
                    $foundPath = $output[0];
                }
            }

            if (!$found) {
                $this->addIssue('mysqldump_missing', 'mysqldump command not found', [
                    'fix' => 'Install MySQL or add mysqldump.exe to system PATH',
                    'checked_paths' => $possiblePaths,
                ]);
            } else {
                $this->addInfo('mysqldump_ok', 'mysqldump is available', [
                    'path' => $foundPath,
                ]);
            }
        } else {
            // Linux/Mac
            exec('which mysqldump', $output, $returnCode);

            if ($returnCode !== 0) {
                $this->addIssue('mysqldump_missing', 'mysqldump command not found', [
                    'fix' => 'Install MySQL client: apt-get install mysql-client',
                ]);
            } else {
                $this->addInfo('mysqldump_ok', 'mysqldump is available', [
                    'path' => $output[0] ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Check available disk space
     */
    private function checkDiskSpace(): void
    {
        $path = Storage::disk(self::BACKUP_DISK)->path(self::BACKUP_PATH);
        $freeSpace = disk_free_space($path);
        $totalSpace = disk_total_space($path);
        $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        $freeSpaceGB = round($freeSpace / 1024 / 1024 / 1024, 2);

        if ($freeSpaceGB < 1) {
            $this->addIssue('disk_space_critical', 'Less than 1GB free space', [
                'free' => $freeSpaceGB . ' GB',
                'used_percent' => round($usedPercent, 2) . '%',
            ]);
        } elseif ($freeSpaceGB < 5) {
            $this->addWarning('disk_space_low', 'Less than 5GB free space', [
                'free' => $freeSpaceGB . ' GB',
                'used_percent' => round($usedPercent, 2) . '%',
            ]);
        } else {
            $this->addInfo('disk_space_ok', 'Sufficient disk space available', [
                'free' => $freeSpaceGB . ' GB',
            ]);
        }
    }

    /**
     * Check for orphaned database records (no file on disk)
     */
    private function checkOrphanedRecords(): void
    {
        $orphaned = BackupHistory::successful()
            ->get()
            ->filter(fn($backup) => !$backup->fileExists());

        if ($orphaned->isNotEmpty()) {
            $this->addWarning('orphaned_records', 'Backup records exist but files are missing', [
                'count' => $orphaned->count(),
                'ids' => $orphaned->pluck('id')->toArray(),
                'fix' => 'Delete orphaned records or restore files',
            ]);
        } else {
            $this->addInfo('no_orphaned_records', 'All backup records have corresponding files');
        }
    }

    /**
     * Check for missing database records (files without records)
     */
    private function checkMissingFiles(): void
    {
        $disk = Storage::disk(self::BACKUP_DISK);
        
        if (!$disk->exists(self::BACKUP_PATH)) {
            return;
        }

        $allFiles = $disk->allFiles(self::BACKUP_PATH);
        $backupFiles = array_filter($allFiles, function($file) {
            return preg_match('/backup_.*\.(zip|sql\.gz)$/', $file);
        });

        $recordedPaths = BackupHistory::pluck('file_path')->toArray();
        $unrecordedFiles = array_diff($backupFiles, $recordedPaths);

        if (!empty($unrecordedFiles)) {
            $totalSize = 0;
            foreach ($unrecordedFiles as $file) {
                $totalSize += $disk->size($file);
            }

            $this->addWarning('unrecorded_files', 'Backup files exist without database records', [
                'count' => count($unrecordedFiles),
                'size' => $this->formatBytes($totalSize),
                'files' => array_slice($unrecordedFiles, 0, 5),
                'fix' => 'Delete unrecorded files to free space',
            ]);
        }
    }

    /**
     * Check for stuck backups (pending for too long)
     */
    private function checkStuckBackups(): void
    {
        $stuckBackups = BackupHistory::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(2))
            ->get();

        if ($stuckBackups->isNotEmpty()) {
            $this->addIssue('stuck_backups', 'Backups stuck in pending status', [
                'count' => $stuckBackups->count(),
                'ids' => $stuckBackups->pluck('id')->toArray(),
                'oldest' => $stuckBackups->min('created_at'),
                'fix' => 'Mark as failed or delete stuck records',
            ]);
        }
    }

    /**
     * Check recent failed backups
     */
    private function checkFailedBackups(): void
    {
        $recentFailed = BackupHistory::where('status', 'failed')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        if ($recentFailed > 5) {
            $this->addWarning('high_failure_rate', 'High number of failed backups in last 7 days', [
                'count' => $recentFailed,
                'fix' => 'Check logs for recurring errors',
            ]);
        } elseif ($recentFailed > 0) {
            $this->addInfo('some_failures', 'Some backups failed recently', [
                'count' => $recentFailed,
            ]);
        }
    }

    /**
     * Check overall backup health
     */
    private function checkBackupHealth(): void
    {
        $lastSuccessful = BackupHistory::successful()->latest()->first();

        if (!$lastSuccessful) {
            $this->addIssue('no_successful_backups', 'No successful backups found', [
                'fix' => 'Create your first backup',
            ]);
            return;
        }

        $hoursSinceLastBackup = $lastSuccessful->created_at->diffInHours(now());

        if ($hoursSinceLastBackup > 168) { // 7 days
            $this->addWarning('backup_outdated', 'Last successful backup is over 7 days old', [
                'last_backup' => $lastSuccessful->created_at->toDateTimeString(),
                'hours_ago' => $hoursSinceLastBackup,
            ]);
        } else {
            $this->addInfo('backup_health_ok', 'Recent successful backup exists', [
                'last_backup' => $lastSuccessful->created_at->toDateTimeString(),
                'hours_ago' => $hoursSinceLastBackup,
            ]);
        }
    }

    /**
     * Get backup statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_backups' => BackupHistory::count(),
            'successful' => BackupHistory::successful()->count(),
            'failed' => BackupHistory::failed()->count(),
            'pending' => BackupHistory::pending()->count(),
            'total_size' => $this->getTotalBackupSize(),
            'oldest_backup' => BackupHistory::successful()->oldest()->first()?->created_at,
            'newest_backup' => BackupHistory::successful()->latest()->first()?->created_at,
            'by_type' => [
                'db' => BackupHistory::byType('db')->successful()->count(),
                'files' => BackupHistory::byType('files')->successful()->count(),
                'full' => BackupHistory::byType('full')->successful()->count(),
            ],
        ];
    }

    /**
     * Fix orphaned records
     */
    public function fixOrphanedRecords(): array
    {
        $orphaned = BackupHistory::successful()
            ->get()
            ->filter(fn($backup) => !$backup->fileExists());

        $deleted = [];
        foreach ($orphaned as $backup) {
            $deleted[] = $backup->id;
            $backup->delete();
        }

        return [
            'fixed' => count($deleted),
            'ids' => $deleted,
        ];
    }

    /**
     * Fix stuck backups
     */
    public function fixStuckBackups(): array
    {
        $stuck = BackupHistory::where('status', 'pending')
            ->where('created_at', '<', now()->subHours(2))
            ->get();

        $fixed = [];
        foreach ($stuck as $backup) {
            $backup->update([
                'status' => 'failed',
                'error_message' => 'Marked as failed by diagnostics (stuck in pending)',
                'completed_at' => now(),
            ]);
            $fixed[] = $backup->id;
        }

        return [
            'fixed' => count($fixed),
            'ids' => $fixed,
        ];
    }

    // ── Helper Methods ────────────────────────────────────────────────────────

    private function reset(): void
    {
        $this->issues = [];
        $this->warnings = [];
        $this->info = [];
    }

    private function addIssue(string $code, string $message, array $details = []): void
    {
        $this->issues[] = compact('code', 'message', 'details');
    }

    private function addWarning(string $code, string $message, array $details = []): void
    {
        $this->warnings[] = compact('code', 'message', 'details');
    }

    private function addInfo(string $code, string $message, array $details = []): void
    {
        $this->info[] = compact('code', 'message', 'details');
    }

    private function generateReport(): array
    {
        $status = 'healthy';
        if (!empty($this->issues)) {
            $status = 'critical';
        } elseif (!empty($this->warnings)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
            'summary' => [
                'issues' => count($this->issues),
                'warnings' => count($this->warnings),
                'info' => count($this->info),
            ],
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'info' => $this->info,
            'statistics' => $this->getStatistics(),
        ];
    }

    private function getTotalBackupSize(): string
    {
        $totalBytes = BackupHistory::successful()->sum('size');
        return $this->formatBytes($totalBytes);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
