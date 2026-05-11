<?php

declare(strict_types=1);

namespace Domain\Ai\Console\Commands;

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Chat\Services\ChatAiActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Command to detect and mark stale AI autopilot runs as failed.
 */
final class DetectStaleRunsCommand extends Command
{
    protected $signature = 'ai:detect-stale-runs {--threshold= : Minutes before a run is considered stale (default from env AI_STALE_RUN_THRESHOLD_MINUTES)}';

    protected $description = 'Mark stale queued/running AI runs as failed and emit chat lifecycle failure events.';

    private const int DEFAULT_THRESHOLD_MINUTES = 2;

    public function __construct(private readonly ChatAiActivityService $chatAiActivity)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $optionValue = $this->option('threshold');
        $thresholdMinutes = $optionValue !== null && $optionValue !== ''
            ? (int) $optionValue
            : (int) config('ai.stale_run_threshold_minutes', self::DEFAULT_THRESHOLD_MINUTES);

        if ($thresholdMinutes < 1) {
            $thresholdMinutes = self::DEFAULT_THRESHOLD_MINUTES;
        }

        $threshold = now()->subMinutes($thresholdMinutes);
        $processed = 0;

        // Mark queued runs that have been waiting too long without being picked up
        AiAutopilotRun::query()
            ->where('status', 'queued')
            ->where('created_at', '<=', $threshold)
            ->orderBy('created_at')
            ->chunkById(100, function ($runs) use (&$processed): void {
                foreach ($runs as $run) {
                    $output = [
                        'error' => 'Run marked as failed: queued for too long without worker pickup.',
                        'error_code' => 'orphaned_queued_run',
                    ];

                    $run->status = 'failed';
                    $run->output = $output;
                    $run->completed_at = now();
                    $run->save();

                    $this->publishFailedEvent($run, 'orphaned_queued_run');
                    $processed++;
                }
            }, 'id');

        // Mark running runs that have stale heartbeat
        AiAutopilotRun::query()
            ->where('status', 'running')
            ->where('updated_at', '<=', $threshold)
            ->orderBy('updated_at')
            ->chunkById(100, function ($runs) use (&$processed): void {
                foreach ($runs as $run) {
                    $output = is_array($run->output) ? $run->output : [];
                    $output['error'] = 'Run marked as failed due to stale heartbeat.';
                    $output['error_code'] = 'stale_run_timeout';

                    $run->status = 'failed';
                    $run->output = $output;
                    $run->completed_at = now();
                    $run->save();

                    $inputContext = is_array($run->input_context) ? $run->input_context : [];
                    $ticketId = (string) ($inputContext['ticket_id'] ?? '');
                    if ($ticketId !== '') {
                        $this->chatAiActivity->emitProcessingFailed(
                            (string) $run->tenant_id,
                            $ticketId,
                            (string) $run->id,
                            'stale_run_timeout',
                            (string) ($inputContext['message_id'] ?? ''),
                        );
                    }

                    $this->publishFailedEvent($run, 'stale_run_timeout');
                    $processed++;
                }
            }, 'id');

        $this->info(sprintf('Stale run detector processed %d run(s).', $processed));

        return self::SUCCESS;
    }

    private function publishFailedEvent(AiAutopilotRun $run, string $errorCode): void
    {
        try {
            Redis::connection('gateway')->publish('ws.events', json_encode([
                'event' => 'ai.run.failed',
                'version' => '1.0',
                'data' => [
                    'event_id' => (string) Str::uuid(),
                    'tenant_id' => $run->tenant_id,
                    'run_id' => $run->id,
                    'status' => 'failed',
                    'error' => $run->output['error'] ?? 'Unknown error',
                    'error_code' => $errorCode,
                    'timestamp' => now()->toIso8601String(),
                ],
            ], JSON_THROW_ON_ERROR));
        } catch (\Throwable $exception) {
            // Log but don't fail the command for Redis publishing errors
            $this->warn("Failed to publish ai.run.failed event for run {$run->id}: {$exception->getMessage()}");
        }
    }
}
