<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Carbon\CarbonImmutable;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Billing\Models\TenantMessageUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Job que fecha ciclos de billing expirados com uso excedente, gerando faturas de overage.
 *
 * Executa de forma idempotente para evitar criação duplicada de faturas.
 * Cada ciclo expirado com overage_count > 0 gera uma fatura do tipo 'overage'
 * com vencimento 5 dias após o fim do ciclo.
 */
final class CloseExpiredCyclesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $today = CarbonImmutable::today()->toDateString();

        TenantMessageUsage::query()
            ->where('cycle_end', '<', $today)
            ->where('overage_count', '>', 0)
            ->with('tenant.plan')
            ->chunkById(100, function ($usages): void {
                foreach ($usages as $usage) {
                    $this->processExpiredCycle($usage);
                }
            });
    }

    /**
     * Processa um ciclo expirado: calcula o valor do excedente e cria a fatura correspondente.
     *
     * @param  TenantMessageUsage  $usage  Linha de uso com ciclo expirado e overage_count > 0
     */
    private function processExpiredCycle(TenantMessageUsage $usage): void
    {
        $tenant = $usage->tenant;
        $plan = $tenant->plan;

        if ($plan === null) {
            return;
        }

        $pricePerMsg = (float) ($plan->overage_price_per_message ?? 0);
        if ($pricePerMsg <= 0.0) {
            return;
        }

        $cycleStart = $usage->cycle_start->toDateString();
        $referenceMonth = $usage->cycle_end->format('Y-m');

        // Idempotency: check if overage invoice already exists for this cycle
        $exists = BillingInvoice::query()
            ->where('tenant_id', $usage->tenant_id)
            ->where('reference_month', $referenceMonth)
            ->whereJsonContains('metadata->kind', 'overage')
            ->whereJsonContains('metadata->cycle_start', $cycleStart)
            ->exists();

        if ($exists) {
            return;
        }

        $amount = round($usage->overage_count * $pricePerMsg, 2);

        DB::transaction(function () use ($usage, $tenant, $amount, $referenceMonth, $cycleStart, $pricePerMsg): void {
            BillingInvoice::create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $usage->tenant_id,
                'plan_id' => $tenant->plan_id,
                'reference_month' => $referenceMonth,
                'amount' => $amount,
                'status' => BillingInvoiceStatus::PENDING,
                'due_date' => $usage->cycle_end->addDays(5)->toDateString(),
                'metadata' => [
                    'kind' => 'overage',
                    'cycle_start' => $cycleStart,
                    'cycle_end' => $usage->cycle_end->toDateString(),
                    'overage_count' => $usage->overage_count,
                    'price_per_message' => $pricePerMsg,
                ],
            ]);
        });
    }
}
