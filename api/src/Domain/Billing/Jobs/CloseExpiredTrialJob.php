<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Carbon\CarbonImmutable;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job que verifica trials expirados e envia email de encerramento.
 *
 * Idempotente: usa flag `trial_expired_email_sent_at` (quando existir na tabela)
 * para não duplicar emails no mesmo ciclo. Atualmente usa um check simples
 * em `alert_100_sent_at` como flag de controle até que a coluna dedicada exista.
 *
 * Agendamento: diário às 03:00 UTC via `billing:close-expired-trials`.
 */
final class CloseExpiredTrialJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $today = CarbonImmutable::today()->toDateString();

        // Find tenants in trial plan with expired cycle
        PlatformTenant::query()
            ->with('plan')
            ->whereHas('plan', function ($q): void {
                $q->where('is_trial', true);
            })
            ->chunkById(100, function ($tenants) use ($today): void {
                foreach ($tenants as $tenant) {
                    $this->processExpiredTrialTenant($tenant, $today);
                }
            });
    }

    /**
     * Verifica e notifica um tenant cujo trial expirou, evitando reenvio com flag de idempotência.
     *
     * @param  PlatformTenant  $tenant  Tenant em plano trial
     * @param  string  $today  Data de hoje no formato YYYY-MM-DD
     */
    private function processExpiredTrialTenant(PlatformTenant $tenant, string $today): void
    {
        $usage = TenantMessageUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('cycle_end', '<', $today)
            ->orderBy('cycle_start', 'desc')
            ->first();

        if (! $usage) {
            return;
        }

        // Idempotency: skip if already marked
        if ($usage->trial_expired_notified_at !== null) {
            return;
        }

        // Mark as notified to prevent duplicate sends
        $usage->forceFill(['trial_expired_notified_at' => now()])->save();

        // Dispatch email notification job
        SendTrialExpiredEmailJob::dispatch($tenant->id);

        Log::info('billing.trial.expired', [
            'tenant_id' => $tenant->id,
            'cycle_end' => $usage->cycle_end,
        ]);
    }
}
