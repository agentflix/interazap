<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Carbon\CarbonImmutable;
use Domain\Billing\DTOs\CheckAndIncrementResult;
use Domain\Billing\Enums\OverageMode;
use Domain\Billing\Models\AiMessageUsageFailedLog;
use Domain\Billing\Models\TenantMessageUsage;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

final class UsageCounterService
{
    public function __construct(
        private readonly BillingCycleCalculator $cycleCalculator,
    ) {}

    public function checkAndIncrement(
        string $tenantId,
        string $channel,
        string $aiTurnId,
    ): CheckAndIncrementResult {
        return DB::transaction(function () use ($tenantId, $aiTurnId): CheckAndIncrementResult {
            $tenant = PlatformTenant::with('plan')->findOrFail($tenantId);
            $plan = $tenant->plan;
            $limit = $plan !== null ? ($plan->message_limit_monthly ?? 0) : 0;
            $mode = $tenant->effectiveOverageMode();

            $anchorDay = $tenant->billing_cycle_anchor_day ?? 1;
            $now = CarbonImmutable::now();
            $cycle = $this->cycleCalculator->calculate($anchorDay, $now);
            $cycleStart = $cycle['cycle_start']->toDateString();
            $cycleEnd = $cycle['cycle_end']->toDateString();

            // Ensure usage row exists, then lock it — serializes concurrent requests for same tenant
            TenantMessageUsage::firstOrCreate(
                ['tenant_id' => $tenantId, 'cycle_start' => $cycleStart],
                [
                    'cycle_end' => $cycleEnd,
                    'message_count' => 0,
                    'overage_count' => 0,
                ]
            );

            $usage = TenantMessageUsage::lockForUpdate()
                ->where('tenant_id', $tenantId)
                ->where('cycle_start', $cycleStart)
                ->firstOrFail();

            // Idempotency check: after acquiring the lock we see committed state from any prior request
            $existing = DB::table('ai_usage_idempotency_keys')
                ->where('ai_turn_id', $aiTurnId)
                ->first();

            if ($existing !== null) {
                /** @var array<string, mixed> $cached */
                $cached = json_decode((string) $existing->result, true);

                return new CheckAndIncrementResult(
                    allowed: (bool) $cached['allowed'],
                    current: (int) $cached['current'],
                    limit: (int) $cached['limit'],
                    mode: OverageMode::from((string) $cached['mode']),
                    isOverage: (bool) $cached['is_overage'],
                    cycleStart: $cycleStart,
                );
            }

            // Also skip if already reconciled via the failed-log replay path
            $alreadyReconciled = AiMessageUsageFailedLog::query()
                ->where('ai_turn_id', $aiTurnId)
                ->whereNotNull('reconciled_at')
                ->exists();

            if ($alreadyReconciled) {
                return new CheckAndIncrementResult(
                    allowed: true,
                    current: $usage->message_count,
                    limit: $limit,
                    mode: $mode,
                    isOverage: $limit > 0 && $usage->message_count > $limit,
                    cycleStart: $cycleStart,
                );
            }

            $current = $usage->message_count;

            if ($current < $limit) {
                $usage->increment('message_count');

                $result = new CheckAndIncrementResult(
                    allowed: true,
                    current: $current + 1,
                    limit: $limit,
                    mode: $mode,
                    isOverage: false,
                    cycleStart: $cycleStart,
                );
            } elseif ($mode === OverageMode::OVERAGE) {
                $usage->increment('overage_count');

                $result = new CheckAndIncrementResult(
                    allowed: true,
                    current: $current,
                    limit: $limit,
                    mode: $mode,
                    isOverage: true,
                    cycleStart: $cycleStart,
                );
            } else {
                $result = new CheckAndIncrementResult(
                    allowed: false,
                    current: $current,
                    limit: $limit,
                    mode: $mode,
                    isOverage: false,
                    cycleStart: $cycleStart,
                );
            }

            DB::table('ai_usage_idempotency_keys')->insertOrIgnore([
                'ai_turn_id' => $aiTurnId,
                'tenant_id' => $tenantId,
                'cycle_start' => $cycleStart,
                'result' => json_encode($result->toArray()),
                'created_at' => $now->toDateTimeString(),
            ]);

            return $result;
        });
    }
}
