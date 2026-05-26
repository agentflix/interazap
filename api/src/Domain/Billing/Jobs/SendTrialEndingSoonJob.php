<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Carbon\CarbonImmutable;
use Domain\Billing\Mail\SendTrialEndingSoonMail;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job que envia lembrete para tenants em trial com ≤2 dias restantes.
 *
 * Idempotente: usa `alert_80_sent_at` como flag de controle até que
 * coluna `trial_ending_soon_sent_at` seja adicionada.
 *
 * Agendamento: diário às 09:00 UTC via `billing:send-trial-ending-soon`.
 */
final class SendTrialEndingSoonJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $twoDaysFromNow = CarbonImmutable::tomorrow()->addDay()->toDateString();
        $today = CarbonImmutable::today()->toDateString();

        PlatformTenant::query()
            ->with('plan')
            ->whereHas('plan', function ($q): void {
                $q->where('is_trial', true);
            })
            ->chunkById(100, function ($tenants) use ($today, $twoDaysFromNow): void {
                foreach ($tenants as $tenant) {
                    $this->processTrialEndingSoon($tenant, $today, $twoDaysFromNow);
                }
            });
    }

    /**
     * Envia lembrete para um tenant cujo trial expira em até 2 dias.
     *
     * @param  PlatformTenant  $tenant  Tenant em plano trial
     * @param  string  $today  Data de hoje no formato YYYY-MM-DD
     * @param  string  $twoDaysFromNow  Data limite de dois dias à frente no formato YYYY-MM-DD
     */
    private function processTrialEndingSoon(PlatformTenant $tenant, string $today, string $twoDaysFromNow): void
    {
        $usage = TenantMessageUsage::query()
            ->where('tenant_id', $tenant->id)
            ->where('cycle_end', '>=', $today)
            ->where('cycle_end', '<=', $twoDaysFromNow)
            ->orderBy('cycle_start', 'desc')
            ->first();

        if (! $usage) {
            return;
        }

        // Idempotency: skip if already sent
        if ($usage->trial_ending_soon_notified_at !== null) {
            return;
        }

        // Mark as notified
        $usage->forceFill(['trial_ending_soon_notified_at' => now()])->save();

        Mail::to($tenant->primary_email)->send(new SendTrialEndingSoonMail($tenant));

        Log::info('billing.trial.ending_soon_email.sent', [
            'tenant_id' => $tenant->id,
            'cycle_end' => $usage->cycle_end,
        ]);
    }
}
