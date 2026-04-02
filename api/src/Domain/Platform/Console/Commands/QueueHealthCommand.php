<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Commands;

use Domain\Platform\Services\QueueHealthService;
use Illuminate\Console\Command;

/**
 * Command to check queue health status.
 *
 * Useful for monitoring, alerting, and debugging queue issues.
 */
final class QueueHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:health
                            {--queue= : Check specific queue only}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check queue health status';

    /**
     * Execute the console command.
     */
    public function handle(QueueHealthService $healthService): int
    {
        $queueFilter = $this->option('queue');
        $jsonOutput = $this->option('json');

        if ($queueFilter) {
            $healthService->setQueues([$queueFilter]);
        }

        $status = $healthService->getHealthStatus();

        if ($jsonOutput) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return $status['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->displayStatus($status);

        return $status['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Display the health status in a human-readable format.
     *
     * @param  array<string, mixed>  $status
     */
    protected function displayStatus(array $status): void
    {
        $this->newLine();

        if ($status['healthy']) {
            $this->info('✓ Queue system is healthy');
        } else {
            $this->error('✗ Queue system has issues');
        }

        $this->newLine();

        // Display issues
        if (! empty($status['issues'])) {
            $this->warn('Issues:');
            foreach ($status['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        // Display queue stats
        $this->info('Queue Statistics:');
        $this->table(
            ['Queue', 'Size', 'Delayed'],
            array_map(
                fn ($q) => [$q['name'], $q['size'], $q['delayed']],
                $status['queues']
            )
        );

        $this->newLine();

        // Display summary
        $this->line("Workers: {$status['workers']}");
        $this->line("Stuck Jobs: {$status['stuck_jobs']}");
        $this->line("Checked At: {$status['checked_at']}");
    }
}
