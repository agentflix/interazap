<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Models\AiAutopilotApproval;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Shared\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job que expira aprovações pendentes do Autopilot que ultrapassaram o prazo.
 *
 * Processa em lotes (100 por vez) as aprovações com status 'pending' cujo
 * expires_at já passou, marcando-as como 'expired' e o run associado como 'failed'.
 * Registra o tempo de espera em métricas para monitoramento.
 */
final class AutopilotApprovalExpiryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Processa as aprovações expiradas em lote e marca os runs associados como falhos.
     */
    public function handle(): void
    {
        $processedApprovals = 0;

        AiAutopilotApproval::query()
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($approvals) use (&$processedApprovals): void {
                foreach ($approvals as $approval) {
                    DB::transaction(function () use ($approval, &$processedApprovals): void {
                        $lockedApproval = AiAutopilotApproval::query()
                            ->where('tenant_id', (string) $approval->tenant_id)
                            ->where('id', (string) $approval->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedApproval instanceof AiAutopilotApproval || $lockedApproval->status !== 'pending') {
                            return;
                        }

                        $now = now();
                        $lockedApproval->status = 'expired';
                        $lockedApproval->expired_at = $now;
                        $lockedApproval->save();
                        $this->recordApprovalWaitTime($lockedApproval);

                        $run = AiAutopilotRun::query()
                            ->where('tenant_id', (string) $lockedApproval->tenant_id)
                            ->where('id', (string) $lockedApproval->run_id)
                            ->whereIn('status', ['queued', 'running', 'paused'])
                            ->first();

                        if ($run instanceof AiAutopilotRun) {
                            $run->status = 'failed';
                            $run->completed_at = $now;
                            $run->save();
                        }

                        Log::warning('[AutopilotApprovalExpiryJob] Expired autopilot approval', [
                            'approval_id' => (string) $lockedApproval->id,
                            'run_id' => (string) $lockedApproval->run_id,
                            'tenant_id' => (string) $lockedApproval->tenant_id,
                        ]);

                        $processedApprovals++;
                    });
                }
            }, 'id');

        Log::info('[AutopilotApprovalExpiryJob] Expiry cycle completed', [
            'processed_approvals' => $processedApprovals,
        ]);
    }

    private function recordApprovalWaitTime(AiAutopilotApproval $approval): void
    {
        try {
            $createdAt = $approval->created_at;
            $expiredAt = $approval->expired_at;
            if ($createdAt === null || $expiredAt === null) {
                return;
            }

            /** @var MetricsService $metrics */
            $metrics = app(MetricsService::class);
            $metrics->recordAutopilotApprovalWaitTime(
                max(0.0, $expiredAt->floatDiffInSeconds($createdAt)),
                [
                    'tenant_id' => (string) $approval->tenant_id,
                    'status' => 'expired',
                ]
            );
        } catch (\Throwable) {
            // Best effort metrics only.
        }
    }
}
