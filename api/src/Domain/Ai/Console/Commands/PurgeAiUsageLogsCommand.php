<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Console\Command;

/**
 * Comando para purgar logs de uso de IA antigos.
 *
 * LGPD: Logs devem ser removidos após período de retenção (padrão: 90 dias).
 */
class PurgeAiUsageLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:purge-usage-logs
                            {--days=90 : Number of days to retain logs}
                            {--chunk=1000 : Number of records to delete per batch}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge AI usage logs older than the retention period (LGPD compliance)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $chunk = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("AI Usage Logs Purge - Retention: {$days} days");

        $cutoffDate = now()->subDays($days);
        $query = AiUsageLog::where('created_at', '<', $cutoffDate);

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No logs to purge.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$totalCount} logs older than {$cutoffDate->toDateTimeString()}");

            return self::SUCCESS;
        }

        $deletedCount = 0;

        // Delete in chunks to avoid memory issues and long locks
        while (true) {
            $deleted = AiUsageLog::where('created_at', '<', $cutoffDate)
                ->limit($chunk)
                ->delete();

            if ($deleted === 0) {
                break;
            }

            $deletedCount += $deleted;
            $this->line("Deleted {$deleted} logs (total: {$deletedCount}/{$totalCount})");
        }

        $this->info("Purge complete: {$deletedCount} logs deleted.");

        return self::SUCCESS;
    }
}
