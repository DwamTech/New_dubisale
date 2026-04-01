<?php

namespace App\Console\Commands;

use App\Services\BackupDiagnosticsService;
use Illuminate\Console\Command;

class BackupDiagnoseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:diagnose 
                            {--fix : Automatically fix common issues}
                            {--stats : Show statistics only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run diagnostics on the backup system and detect common issues';

    /**
     * Execute the console command.
     */
    public function handle(BackupDiagnosticsService $diagnosticsService): int
    {
        $this->info('Running backup system diagnostics...');
        $this->newLine();

        // Show statistics only
        if ($this->option('stats')) {
            return $this->showStatistics($diagnosticsService);
        }

        // Run full diagnostics
        $report = $diagnosticsService->runDiagnostics();

        // Display status
        $statusColor = match ($report['status']) {
            'healthy' => 'green',
            'warning' => 'yellow',
            'critical' => 'red',
            default => 'white',
        };

        $this->line("Status: <fg={$statusColor}>" . strtoupper($report['status']) . '</fg>');
        $this->newLine();

        // Display issues
        if (!empty($report['issues'])) {
            $this->error('CRITICAL ISSUES (' . count($report['issues']) . '):');
            foreach ($report['issues'] as $issue) {
                $this->line("  • [{$issue['code']}] {$issue['message']}");
                if (!empty($issue['details'])) {
                    foreach ($issue['details'] as $key => $value) {
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        $this->line("    - {$key}: {$value}");
                    }
                }
            }
            $this->newLine();
        }

        // Display warnings
        if (!empty($report['warnings'])) {
            $this->warn('WARNINGS (' . count($report['warnings']) . '):');
            foreach ($report['warnings'] as $warning) {
                $this->line("  • [{$warning['code']}] {$warning['message']}");
                if (!empty($warning['details'])) {
                    foreach ($warning['details'] as $key => $value) {
                        if (is_array($value)) {
                            $value = json_encode($value);
                        }
                        $this->line("    - {$key}: {$value}");
                    }
                }
            }
            $this->newLine();
        }

        // Display info
        if (!empty($report['info'])) {
            $this->info('INFO (' . count($report['info']) . '):');
            foreach ($report['info'] as $info) {
                $this->line("  • [{$info['code']}] {$info['message']}");
            }
            $this->newLine();
        }

        // Display statistics
        $this->displayStatistics($report['statistics']);

        // Auto-fix if requested
        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Running auto-fix...');
            
            $orphanedResult = $diagnosticsService->fixOrphanedRecords();
            $stuckResult = $diagnosticsService->fixStuckBackups();

            $this->line("  • Fixed {$orphanedResult['fixed']} orphaned records");
            $this->line("  • Fixed {$stuckResult['fixed']} stuck backups");
            
            $totalFixed = $orphanedResult['fixed'] + $stuckResult['fixed'];
            
            if ($totalFixed > 0) {
                $this->newLine();
                $this->info("Total issues fixed: {$totalFixed}");
            } else {
                $this->newLine();
                $this->comment('No issues to fix.');
            }
        }

        // Return appropriate exit code
        return match ($report['status']) {
            'healthy' => self::SUCCESS,
            'warning' => self::SUCCESS,
            'critical' => self::FAILURE,
            default => self::SUCCESS,
        };
    }

    /**
     * Show statistics only
     */
    private function showStatistics(BackupDiagnosticsService $diagnosticsService): int
    {
        $stats = $diagnosticsService->getStatistics();
        $this->displayStatistics($stats);
        return self::SUCCESS;
    }

    /**
     * Display statistics in a formatted table
     */
    private function displayStatistics(array $stats): void
    {
        $this->info('STATISTICS:');
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Backups', $stats['total_backups']],
                ['Successful', $stats['successful']],
                ['Failed', $stats['failed']],
                ['Pending', $stats['pending']],
                ['Total Size', $stats['total_size']],
                ['Oldest Backup', $stats['oldest_backup'] ?? 'N/A'],
                ['Newest Backup', $stats['newest_backup'] ?? 'N/A'],
            ]
        );

        $this->newLine();
        $this->info('BY TYPE:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Database', $stats['by_type']['db']],
                ['Files', $stats['by_type']['files']],
                ['Full', $stats['by_type']['full']],
            ]
        );
    }
}
