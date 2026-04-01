<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RestoreBackupRequest;
use App\Http\Requests\Admin\StoreBackupRequest;
use App\Services\BackupService;
use App\Services\BackupDiagnosticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(
        private BackupService $backupService,
        private BackupDiagnosticsService $diagnosticsService
    ) {}

    /**
     * List all backups with optional filters.
     * GET /admin/backups
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'type', 'days', 'per_page']);
            $backups = $this->backupService->listBackups($filters);

            return response()->json([
                'ok'      => true,
                'message' => __('api.backups_fetched'),
                'data'    => $backups->items(),
                'meta'    => [
                    'current_page' => $backups->currentPage(),
                    'per_page'     => $backups->perPage(),
                    'total'        => $backups->total(),
                    'last_page'    => $backups->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backups_fetch_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new backup.
     * POST /admin/backups
     */
    public function store(StoreBackupRequest $request): JsonResponse
    {
        try {
            $backup = $this->backupService->createBackup([
                'type'       => $request->validated('type'),
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_created'),
                'data'    => [
                    'id'           => $backup->id,
                    'file_name'    => $backup->file_name,
                    'type'         => $backup->type,
                    'status'       => $backup->status,
                    'size'         => $backup->getHumanReadableSize(),
                    'duration'     => $backup->getHumanReadableDuration(),
                    'created_by'   => $backup->creator?->name,
                    'created_at'   => $backup->created_at->toIso8601String(),
                    'completed_at' => $backup->completed_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_invalid_type'),
                'error'   => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_creation_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restore a backup by ID.
     * POST /admin/backups/{id}/restore
     */
    public function restore(RestoreBackupRequest $request, int $id): JsonResponse
    {
        try {
            $this->backupService->restoreBackup($id);

            $backup = \App\Models\BackupHistory::findOrFail($id);
            \App\Events\BackupRestored::dispatch($backup, auth()->user());

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_restored'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_not_found'),
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_restore_failed'),
                'error'   => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_restore_error'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a backup by ID.
     * DELETE /admin/backups/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $backup = \App\Models\BackupHistory::findOrFail($id);
            $this->backupService->deleteBackup($id);

            \App\Events\BackupDeleted::dispatch($backup, auth()->user());

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_deleted'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_not_found'),
            ], 404);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_deletion_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run diagnostics on backup system.
     * GET /admin/backups/diagnostics
     */
    public function diagnostics(): JsonResponse
    {
        try {
            $report = $this->diagnosticsService->runDiagnostics();

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_diagnostics_completed'),
                'data'    => $report,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_diagnostics_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get backup statistics.
     * GET /admin/backups/statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->diagnosticsService->getStatistics();

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_statistics_fetched'),
                'data'    => $stats,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_statistics_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-fix common backup issues.
     * POST /admin/backups/diagnostics/fix
     */
    public function autoFix(): JsonResponse
    {
        try {
            $orphanedResult = $this->diagnosticsService->fixOrphanedRecords();
            $stuckResult = $this->diagnosticsService->fixStuckBackups();

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_auto_fix_completed'),
                'data'    => [
                    'orphaned_records_fixed' => $orphanedResult['fixed'],
                    'stuck_backups_fixed'    => $stuckResult['fixed'],
                    'total_fixed'            => $orphanedResult['fixed'] + $stuckResult['fixed'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_auto_fix_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a backup file.
     * GET /admin/backups/{id}/download
     */
    public function download(int $id): BinaryFileResponse
    {
        try {
            $backup = \App\Models\BackupHistory::findOrFail($id);

            if (!$backup->isSuccess()) {
                abort(404, __('api.backup_not_found'));
            }

            if (!$backup->fileExists()) {
                abort(404, __('api.backup_file_not_found'));
            }

            return response()->download(
                $backup->getFullPath(),
                $backup->file_name,
                ['Content-Type' => 'application/octet-stream']
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            abort(404, __('api.backup_not_found'));
        } catch (\Throwable $e) {
            abort(500, __('api.backup_download_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * Get backup history with pagination.
     * GET /admin/backups/history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 50);
            $history = \App\Models\BackupHistory::with('creator:id,name')
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_history_fetched'),
                'data'    => $history->items(),
                'meta'    => [
                    'current_page' => $history->currentPage(),
                    'per_page'     => $history->perPage(),
                    'total'        => $history->total(),
                    'last_page'    => $history->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_history_fetch_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a backup file.
     * POST /admin/backups/upload
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            // Validate the uploaded file
            $request->validate([
                'file' => ['required', 'file', 'mimes:zip,gz', 'max:512000'], // Max 500MB
                'type' => ['required', 'in:db,files,full'],
            ]);

            $file = $request->file('file');
            $type = $request->input('type');

            // Generate file name
            $fileName = 'backup_' . $type . '_' . now()->format('Y_m_d_His');
            $extension = $type === 'db' ? 'sql.gz' : 'zip';
            $datePath = now()->format('Y/m');
            $relativePath = "backups/{$datePath}/{$fileName}.{$extension}";

            // Store the file
            $storedPath = $file->storeAs(
                "backups/{$datePath}",
                "{$fileName}.{$extension}",
                'local'
            );

            if (!$storedPath) {
                throw new \RuntimeException('Failed to store uploaded file');
            }

            // Create backup record
            $backup = \App\Models\BackupHistory::create([
                'file_name'    => $fileName,
                'file_path'    => $relativePath,
                'type'         => $type,
                'status'       => 'success',
                'size'         => $file->getSize(),
                'created_by'   => auth()->id(),
                'completed_at' => now(),
            ]);

            return response()->json([
                'ok'      => true,
                'message' => __('api.backup_uploaded'),
                'data'    => [
                    'id'           => $backup->id,
                    'file_name'    => $backup->file_name,
                    'type'         => $backup->type,
                    'status'       => $backup->status,
                    'size'         => $backup->getHumanReadableSize(),
                    'created_by'   => $backup->creator?->name,
                    'created_at'   => $backup->created_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.validation_failed'),
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('api.backup_upload_failed'),
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
