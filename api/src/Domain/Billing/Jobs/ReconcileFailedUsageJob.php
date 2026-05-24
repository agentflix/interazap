<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Billing\Services\UsageCounterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Job that reconciles failed AI message usage log entries by re-running
 * the check-and-increment counter. Marks successfully reconciled logs.
 */
final class ReconcileFailedUsageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(UsageCounterService $counter): void
    {
        AiMessageUsageFailedLog::query()
            ->whereNull('reconciled_at')
            ->where('attempted_at', '>=', Carbon::now()->subDay())
            ->chunkById(100, function ($logs) use ($counter): void {
                foreach ($logs as $log) {
                    try {
                        $counter->checkAndIncrement($log->tenant_id, $log->channel, $log->ai_turn_id);
                        $log->update(['reconciled_at' => Carbon::now()]);
                    } catch (\Throwable) {
                        // Skip — retry on next scheduled run
                    }
                }
            });
    }
}
