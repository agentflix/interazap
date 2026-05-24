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
 * Job that closes expired billing cycles with overage usage by creating
 * overage invoices. Runs idempotently to prevent duplicate invoice creation.
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
