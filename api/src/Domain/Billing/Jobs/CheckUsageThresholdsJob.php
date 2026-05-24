<?php

declare(strict_types=1);

namespace Domain\Billing\Jobs;

use Domain\Billing\Models\TenantMessageUsage;
use Domain\Billing\Services\ThresholdChecker;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Job that checks message usage thresholds and dispatches alert notifications.
 *
 * Dispatched after each successful usage increment to fire 80%/100% alerts.
 */
final class CheckUsageThresholdsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $cycleStart,
    ) {}

    public function handle(ThresholdChecker $checker): void
    {
        $thresholds = DB::transaction(function () use ($checker): array {
            $usage = TenantMessageUsage::lockForUpdate()
                ->where('tenant_id', $this->tenantId)
                ->where('cycle_start', $this->cycleStart)
                ->first();

            if ($usage === null) {
                return [];
            }

            $tenant = PlatformTenant::with('plan')->find($this->tenantId);
            $plan = $tenant?->plan;
            $limit = $plan !== null ? ($plan->message_limit_monthly ?? 0) : 0;

            return $checker->check($usage, $limit);
        });

        if (empty($thresholds)) {
            return;
        }

        $tenant = PlatformTenant::with('plan')->find($this->tenantId);
        $plan = $tenant?->plan;
        $limit = $plan !== null ? ($plan->message_limit_monthly ?? 0) : 0;

        $usage = TenantMessageUsage::query()
            ->where('tenant_id', $this->tenantId)
            ->where('cycle_start', $this->cycleStart)
            ->first();

        if ($usage === null) {
            return;
        }

        foreach ($thresholds as $threshold) {
            SendUsageAlertJob::dispatch(
                $this->tenantId,
                $threshold,
                $usage->message_count,
                $limit,
                $usage->tenant->effectiveOverageMode()->value,
                $plan !== null && $plan->overage_price_per_message !== null
                    ? (string) $plan->overage_price_per_message
                    : null,
            );
        }
    }
}
