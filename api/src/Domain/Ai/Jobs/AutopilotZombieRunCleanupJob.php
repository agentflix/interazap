<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Events\AiRunFailed;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class AutopilotZombieRunCleanupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $thresholdMinutes = max(1, (int) config('ai.stale_run_threshold_minutes', 6));
        $threshold = now()->subMinutes($thresholdMinutes);
        $processedRuns = 0;

        AiAutopilotRun::query()
            ->where('status', 'running')
            ->where('started_at', '<', $threshold)
            ->orderBy('started_at')
            ->chunkById(100, function ($runs) use (&$processedRuns): void {
                foreach ($runs as $run) {
                    $output = is_array($run->output) ? $run->output : [];
                    $output['error'] = 'Run marked as failed by watchdog zombie cleanup.';
                    $output['error_code'] = 'watchdog_zombie_cleanup';

                    $run->status = 'failed';
                    $run->output = $output;
                    $run->completed_at = now();
                    $run->save();

                    $inputContext = is_array($run->input_context) ? $run->input_context : [];
                    $ticketId = (string) ($inputContext['ticket_id'] ?? '');
                    $correlationId = (string) ($inputContext['correlation_id'] ?? $run->id);

                    Log::warning('[AutopilotZombieRunCleanupJob] Marked stale run as failed', [
                        'run_id' => (string) $run->id,
                        'tenant_id' => (string) $run->tenant_id,
                        'correlation_id' => $correlationId,
                        'started_at' => $run->started_at?->toIso8601String(),
                    ]);

                    AiRunFailed::dispatch(
                        (string) $run->tenant_id,
                        $ticketId,
                        (string) $run->id,
                        $correlationId,
                        'watchdog_zombie_cleanup',
                        'watchdog_zombie_cleanup',
                    );

                    $processedRuns++;
                }
            }, 'id');

        Log::info('[AutopilotZombieRunCleanupJob] Cleanup cycle completed', [
            'threshold_minutes' => $thresholdMinutes,
            'processed_runs' => $processedRuns,
        ]);
    }
}
