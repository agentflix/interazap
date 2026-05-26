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
use Illuminate\Support\Facades\Log;

/**
 * Job que reconcilia entradas com falha de contagem de mensagens IA.
 *
 * Reexecuta o check-and-increment para registros em AiMessageUsageFailedLog
 * que não foram processados. Marcará como reconciliado ao ter sucesso.
 */
final class ReconcileFailedUsageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Reprocessa em lotes os registros de falha de uso das últimas 24 horas.
     */
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
                    } catch (\Throwable $e) {
                        Log::warning('[ReconcileFailedUsageJob] Reconciliation failed', [
                            'log_id' => $log->id,
                            'ai_turn_id' => $log->ai_turn_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
