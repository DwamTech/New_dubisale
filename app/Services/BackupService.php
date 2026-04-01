<?php

namespace App\Services;

use App\Models\BackupHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    private const BACKUP_DISK = 'local';
    private const BACKUP_PATH = 'backups';
    private const CHUNK_SIZE = 524288; // 512KB

    public function __construct()
    {
        $this->ensureBackupDirectoryExists();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function createBackup(array $data = []): BackupHistory
    {
        $type = $data['type'] ?? 'full';
        $createdBy = $data['created_by'] ?? auth()->id();

        $this->validateBackupType($type);

        $backup = $this->initializeBackup($type, $createdBy);

        try {
            $this->logBackupStart($backup);
            $this->executeBackup($backup);
            $this->markBackupSuccess($backup);
            $this->logBackupComplete($backup);

            return $backup->fresh();
        } catch (\Throwable $e) {
            $this->handleBackupFailure($backup, $e);
            throw $e;
        }
    }

    public function listBackups(array $filters = [])
    {
        return BackupHistory::query()
            ->with('creator:id,name')
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(isset($filters['days']), fn($q) => $q->recent((int) $filters['days']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function restoreBackup(int $id): bool
    {
        $backup = BackupHistory::findOrFail($id);

        $this->validateBackupForRestore($backup);

        try {
            Log::info('restore_started', ['backup_id' => $backup->id]);

            $this->createSafetyBackup();
            $this->executeRestore($backup);

            Log::info('restore_completed', ['backup_id' => $backup->id]);

            return true;
        } catch (\Throwable $e) {
            $this->logRestoreFailure($backup, $e);
            throw $e;
        }
    }

    public function deleteBackup(int $id): bool
    {
        $backup = BackupHistory::findOrFail($id);

        try {
            $this->deleteBackupFile($backup);
            $backup->delete();

            Log::info('backup_deleted', [
                'id'        => $id,
                'file_name' => $backup->file_name,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('backup_deletion_failed', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ── Backup Initialization ─────────────────────────────────────────────────

    private function initializeBackup(string $type, ?int $createdBy): BackupHistory
    {
        $fileName = $this->generateBackupFileName($type);

        return BackupHistory::create([
            'file_name'  => $fileName,
            'file_path'  => $this->buildBackupFilePath($fileName, $type),
            'type'       => $type,
            'status'     => 'pending',
            'created_by' => $createdBy,
        ]);
    }

    private function generateBackupFileName(string $type): string
    {
        return sprintf('backup_%s_%s', $type, now()->format('Y_m_d_His'));
    }

    private function buildBackupFilePath(string $fileName, string $type): string
    {
        $extension = $type === 'db' ? 'sql.gz' : 'zip';
        $datePath = now()->format('Y/m');
        return sprintf('%s/%s/%s.%s', self::BACKUP_PATH, $datePath, $fileName, $extension);
    }

    // ── Backup Execution ──────────────────────────────────────────────────────

    private function executeBackup(BackupHistory $backup): void
    {
        match ($backup->type) {
            'db'    => $this->createDatabaseBackup($backup),
            'files' => $this->createFilesBackup($backup),
            'full'  => $this->createFullBackup($backup),
        };
    }

    private function createDatabaseBackup(BackupHistory $backup): void
    {
        $dbConfig = $this->getDatabaseConfig();
        $fullPath = $this->getStoragePath($backup->file_path);

        // Remove .gz extension for initial dump
        $sqlPath = str_replace('.sql.gz', '.sql', $fullPath);

        $this->executeMysqldump($dbConfig, $sqlPath);
        
        // Verify the SQL file was created and has content
        if (!file_exists($sqlPath)) {
            throw new \RuntimeException('Database backup file was not created');
        }
        
        if (filesize($sqlPath) === 0) {
            unlink($sqlPath);
            throw new \RuntimeException('Database backup file is empty');
        }

        $this->compressFile($sqlPath);
        
        // Verify the compressed file exists
        if (!file_exists($fullPath)) {
            throw new \RuntimeException('Failed to compress database backup');
        }
    }

    private function createFilesBackup(BackupHistory $backup): void
    {
        $storagePath = storage_path('app/public');
        $zipPath = $this->getStoragePath($backup->file_path);

        // Check if storage directory exists
        if (!is_dir($storagePath)) {
            throw new \RuntimeException('Storage directory does not exist: ' . $storagePath);
        }

        $zip = $this->openZipArchive($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addDirectoryToZip($zip, $storagePath, 'storage');
        $zip->close();
        
        // Verify the ZIP file was created
        if (!file_exists($zipPath)) {
            throw new \RuntimeException('Files backup ZIP was not created');
        }
        
        if (filesize($zipPath) === 0) {
            unlink($zipPath);
            throw new \RuntimeException('Files backup ZIP is empty');
        }
    }

    private function createFullBackup(BackupHistory $backup): void
    {
        $tempDbBackup = $this->createTemporaryDatabaseBackup($backup->created_by);

        try {
            // Create database backup first
            $this->createDatabaseBackup($tempDbBackup);
            
            // Verify database backup was created
            if (!$tempDbBackup->fresh()->fileExists()) {
                throw new \RuntimeException('Database backup file not found after creation');
            }

            // Create the ZIP for full backup
            $zipPath = $this->getStoragePath($backup->file_path);
            $zip = $this->openZipArchive($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            // Add storage files
            $storagePath = storage_path('app/public');
            if (is_dir($storagePath)) {
                $this->addDirectoryToZip($zip, $storagePath, 'storage');
            }
            
            // Add database backup to ZIP
            $dbPath = $tempDbBackup->getFullPath();
            if (file_exists($dbPath)) {
                $zip->addFile($dbPath, 'database/' . basename($dbPath));
            }
            
            $zip->close();
            
            // Verify the full backup ZIP was created
            if (!file_exists($zipPath)) {
                throw new \RuntimeException('Full backup ZIP was not created');
            }
            
            if (filesize($zipPath) === 0) {
                unlink($zipPath);
                throw new \RuntimeException('Full backup ZIP is empty');
            }

            $this->cleanupTemporaryBackup($tempDbBackup);
        } catch (\Throwable $e) {
            $this->cleanupFailedBackup($tempDbBackup);
            throw $e;
        }
    }

    // ── Restore Execution ─────────────────────────────────────────────────────

    private function executeRestore(BackupHistory $backup): void
    {
        match ($backup->type) {
            'db'    => $this->restoreDatabaseBackup($backup),
            'files' => $this->restoreFilesBackup($backup),
            'full'  => $this->restoreFullBackup($backup),
        };
    }

    private function restoreDatabaseBackup(BackupHistory $backup): void
    {
        $dbConfig = $this->getDatabaseConfig();
        $fullPath = $backup->getFullPath();
        $decompressedPath = $this->decompressFile($fullPath);

        try {
            $this->executeMysqlImport($dbConfig, $decompressedPath);
        } finally {
            $this->deleteFileIfExists($decompressedPath);
        }
    }

    private function restoreFilesBackup(BackupHistory $backup): void
    {
        $zipPath = $backup->getFullPath();
        $tempExtractPath = storage_path('app/temp_restore_' . time());

        try {
            // Create temp directory
            if (!is_dir($tempExtractPath)) {
                mkdir($tempExtractPath, 0755, true);
            }

            // Extract ZIP
            $zip = $this->openZipArchive($zipPath);
            $zip->extractTo($tempExtractPath);
            $zip->close();

            // Copy storage files to target
            $storageSource = $tempExtractPath . '/storage';
            if (is_dir($storageSource)) {
                $storageTarget = storage_path('app/public');
                $this->copyDirectory($storageSource, $storageTarget);
            }
        } finally {
            // Cleanup temp directory
            if (is_dir($tempExtractPath)) {
                $this->deleteDirectory($tempExtractPath);
            }
        }
    }

    private function restoreFullBackup(BackupHistory $backup): void
    {
        $zipPath = $backup->getFullPath();
        $tempExtractPath = storage_path('app/temp_restore_' . time());

        try {
            // Create temp directory
            if (!is_dir($tempExtractPath)) {
                mkdir($tempExtractPath, 0755, true);
            }

            // Extract the full backup ZIP
            $zip = $this->openZipArchive($zipPath);
            $zip->extractTo($tempExtractPath);
            $zip->close();

            // Find and restore database backup
            $dbFiles = glob($tempExtractPath . '/database/*.sql.gz');
            if (!empty($dbFiles)) {
                $dbConfig = $this->getDatabaseConfig();
                $decompressedPath = $this->decompressFile($dbFiles[0]);
                
                try {
                    $this->executeMysqlImport($dbConfig, $decompressedPath);
                } finally {
                    $this->deleteFileIfExists($decompressedPath);
                }
            }

            // Restore files (storage directory)
            $storageSource = $tempExtractPath . '/storage';
            if (is_dir($storageSource)) {
                $storageTarget = storage_path('app/public');
                $this->copyDirectory($storageSource, $storageTarget);
            }
        } finally {
            // Cleanup temp directory
            if (is_dir($tempExtractPath)) {
                $this->deleteDirectory($tempExtractPath);
            }
        }
    }

    // ── Database Operations ───────────────────────────────────────────────────

    private function getDatabaseConfig(): array
    {
        $connection = config('database.default');

        return [
            'host'     => config("database.connections.{$connection}.host"),
            'database' => config("database.connections.{$connection}.database"),
            'username' => config("database.connections.{$connection}.username"),
            'password' => config("database.connections.{$connection}.password"),
        ];
    }

    private function executeMysqldump(array $config, string $outputPath): void
    {
        // Ensure directory exists
        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Build mysqldump command for Windows
        $command = $this->buildMysqldumpCommand($config, $outputPath);

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMessage = implode("\n", $output);
            throw new \RuntimeException('Database backup failed: ' . $errorMessage);
        }

        // Check if file was created
        if (!file_exists($outputPath) || filesize($outputPath) === 0) {
            throw new \RuntimeException('Database backup file was not created or is empty');
        }
    }

    private function buildMysqldumpCommand(array $config, string $outputPath): string
    {
        // Detect OS
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Find mysqldump path
        $mysqldumpPath = $this->findMysqldumpPath($isWindows);

        // Build command based on OS and password
        if (empty($config['password'])) {
            // No password (common in XAMPP/WAMP)
            $command = sprintf(
                '%s --user=%s --host=%s %s > %s 2>&1',
                $mysqldumpPath,
                escapeshellarg($config['username']),
                escapeshellarg($config['host']),
                escapeshellarg($config['database']),
                escapeshellarg($outputPath)
            );
        } else {
            // With password
            $command = sprintf(
                '%s --user=%s --password=%s --host=%s %s > %s 2>&1',
                $mysqldumpPath,
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['host']),
                escapeshellarg($config['database']),
                escapeshellarg($outputPath)
            );
        }

        return $command;
    }

    private function findMysqldumpPath(bool $isWindows): string
    {
        if ($isWindows) {
            // Common paths for Windows (XAMPP, WAMP, Laragon)
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysqldump.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe',
                'mysqldump', // If in PATH
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path) || $path === 'mysqldump') {
                    return $path;
                }
            }

            // Try to find via where command
            exec('where mysqldump', $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return $output[0];
            }

            throw new \RuntimeException('mysqldump not found. Please install MySQL or add mysqldump to PATH');
        }

        // Linux/Mac
        return 'mysqldump';
    }

    private function executeMysqlImport(array $config, string $inputPath): void
    {
        // Detect OS
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        // Find mysql path
        $mysqlPath = $this->findMysqlPath($isWindows);

        // Build command based on password
        if (empty($config['password'])) {
            $command = sprintf(
                '%s --user=%s --host=%s %s < %s 2>&1',
                $mysqlPath,
                escapeshellarg($config['username']),
                escapeshellarg($config['host']),
                escapeshellarg($config['database']),
                escapeshellarg($inputPath)
            );
        } else {
            $command = sprintf(
                '%s --user=%s --password=%s --host=%s %s < %s 2>&1',
                $mysqlPath,
                escapeshellarg($config['username']),
                escapeshellarg($config['password']),
                escapeshellarg($config['host']),
                escapeshellarg($config['database']),
                escapeshellarg($inputPath)
            );
        }

        $this->executeShellCommand($command, 'Database restore failed');
    }

    private function findMysqlPath(bool $isWindows): string
    {
        if ($isWindows) {
            // Common paths for Windows
            $possiblePaths = [
                'C:\\xampp\\mysql\\bin\\mysql.exe',
                'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysql.exe',
                'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe',
                'mysql', // If in PATH
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path) || $path === 'mysql') {
                    return $path;
                }
            }

            // Try to find via where command
            exec('where mysql', $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return $output[0];
            }

            throw new \RuntimeException('mysql not found. Please install MySQL or add mysql to PATH');
        }

        return 'mysql';
    }

    private function executeShellCommand(string $command, string $errorMessage): void
    {
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException($errorMessage . ': ' . implode("\n", $output));
        }
    }

    // ── File Compression ──────────────────────────────────────────────────────

    private function compressFile(string $filePath): void
    {
        $gzPath = $filePath . '.gz';

        $file = fopen($filePath, 'rb');
        $gz = gzopen($gzPath, 'wb9');

        if (!$file || !$gz) {
            throw new \RuntimeException('Failed to open files for compression');
        }

        while (!feof($file)) {
            gzwrite($gz, fread($file, self::CHUNK_SIZE));
        }

        fclose($file);
        gzclose($gz);

        unlink($filePath);
        rename($gzPath, $filePath);
    }

    private function decompressFile(string $gzPath): string
    {
        $sqlPath = str_replace('.gz', '', $gzPath);

        $gz = gzopen($gzPath, 'rb');
        $file = fopen($sqlPath, 'wb');

        if (!$gz || !$file) {
            throw new \RuntimeException('Failed to open files for decompression');
        }

        while (!gzeof($gz)) {
            fwrite($file, gzread($gz, self::CHUNK_SIZE));
        }

        gzclose($gz);
        fclose($file);

        return $sqlPath;
    }

    // ── Zip Operations ────────────────────────────────────────────────────────

    private function openZipArchive(string $path, int $flags = ZipArchive::RDONLY): ZipArchive
    {
        $zip = new ZipArchive();

        if ($zip->open($path, $flags) !== true) {
            throw new \RuntimeException("Failed to open zip archive: {$path}");
        }

        return $zip;
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipPath . '/' . substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    // ── Full Backup Helpers ───────────────────────────────────────────────────

    private function createTemporaryDatabaseBackup(?int $createdBy): BackupHistory
    {
        $tempName = 'temp_db_' . Str::random(8);

        return BackupHistory::create([
            'file_name'  => $tempName,
            'file_path'  => $this->buildBackupFilePath($tempName, 'db'),
            'type'       => 'db',
            'status'     => 'pending',
            'created_by' => $createdBy,
        ]);
    }

    private function cleanupTemporaryBackup(BackupHistory $backup): void
    {
        $this->deleteBackupFile($backup);
        $backup->delete();
    }

    // ── Status Management ─────────────────────────────────────────────────────

    private function markBackupSuccess(BackupHistory $backup): void
    {
        $backup->update([
            'status'       => 'success',
            'size'         => $this->getFileSize($backup->file_path),
            'completed_at' => now(),
        ]);

        \App\Events\BackupCreated::dispatch($backup);
    }

    private function handleBackupFailure(BackupHistory $backup, \Throwable $e): void
    {
        $backup->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at'  => now(),
        ]);

        $this->logBackupFailure($backup, $e);
        $this->cleanupFailedBackup($backup);

        \App\Events\BackupFailed::dispatch($backup, $e);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private function validateBackupType(string $type): void
    {
        if (!in_array($type, ['db', 'files', 'full'])) {
            throw new \InvalidArgumentException("Invalid backup type: {$type}");
        }
    }

    private function validateBackupForRestore(BackupHistory $backup): void
    {
        if (!$backup->isSuccess()) {
            throw new \RuntimeException('Cannot restore a backup that did not complete successfully.');
        }

        if (!$backup->fileExists()) {
            throw new \RuntimeException('Backup file not found on disk.');
        }
    }

    // ── Logging ───────────────────────────────────────────────────────────────

    private function logBackupStart(BackupHistory $backup): void
    {
        Log::info('backup_started', [
            'id'   => $backup->id,
            'type' => $backup->type,
            'user' => $backup->created_by,
        ]);
    }

    private function logBackupComplete(BackupHistory $backup): void
    {
        Log::info('backup_completed', [
            'id'       => $backup->id,
            'size'     => $backup->getHumanReadableSize(),
            'duration' => $backup->getHumanReadableDuration(),
        ]);
    }

    private function logBackupFailure(BackupHistory $backup, \Throwable $e): void
    {
        Log::error('backup_failed', [
            'id'    => $backup->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    private function logRestoreFailure(BackupHistory $backup, \Throwable $e): void
    {
        Log::error('restore_failed', [
            'backup_id' => $backup->id,
            'error'     => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }

    // ── File Management ───────────────────────────────────────────────────────

    private function ensureBackupDirectoryExists(): void
    {
        if (!Storage::disk(self::BACKUP_DISK)->exists(self::BACKUP_PATH)) {
            Storage::disk(self::BACKUP_DISK)->makeDirectory(self::BACKUP_PATH);
        }
    }

    private function getStoragePath(string $relativePath): string
    {
        return Storage::disk(self::BACKUP_DISK)->path($relativePath);
    }

    private function getFileSize(string $path): int
    {
        return Storage::disk(self::BACKUP_DISK)->size($path);
    }

    private function deleteBackupFile(BackupHistory $backup): void
    {
        if ($backup->fileExists()) {
            Storage::disk(self::BACKUP_DISK)->delete($backup->file_path);
        }
    }

    private function deleteFileIfExists(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function cleanupFailedBackup(BackupHistory $backup): void
    {
        try {
            $this->deleteBackupFile($backup);
        } catch (\Throwable $e) {
            Log::warning('failed_backup_cleanup_error', [
                'backup_id' => $backup->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    private function createSafetyBackup(): void
    {
        // Temporarily disabled for testing
        // $this->createBackup(['type' => 'full', 'created_by' => auth()->id()]);
        
        Log::info('Safety backup skipped for testing');
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item, $targetPath);
            }
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
