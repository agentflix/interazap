<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Carbon\CarbonImmutable;
use Domain\Ai\Models\AiUsageLog;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

/**
 * Calcula excedente mensal de tokens de IA para um tenant.
 */
final class CalculateAiOverageAction
{
    /**
     * @return array{total_tokens:int,token_limit_monthly:int|null,overage_applied:bool,overage_tokens:int,overage_amount:float}
     */
    public function execute(string $tenantId, string $referenceMonth): array
    {
        $tenant = PlatformTenant::query()
            ->with('plan')
            ->findOrFail($tenantId);

        $plan = $tenant->plan;
        $period = CarbonImmutable::createFromFormat('Y-m-d', $referenceMonth.'-01');
        $start = $period->startOfMonth();
        $end = $period->endOfMonth();

        $totalTokens = (int) AiUsageLog::query()
            ->forTenant($tenantId)
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('input_tokens + output_tokens'));

        $limit = $plan?->token_limit_monthly;
        $excess = $limit === null ? 0 : max(0, $totalTokens - (int) $limit);
        $overageAmount = 0.0;

        if ($plan?->allow_overage === true && $excess > 0) {
            $overageAmount = round(($excess / 1000) * (float) ($plan->overage_price_per_1k ?? 0), 2);
        }

        return [
            'total_tokens' => $totalTokens,
            'token_limit_monthly' => $limit !== null ? (int) $limit : null,
            'overage_applied' => $overageAmount > 0.0,
            'overage_tokens' => $excess,
            'overage_amount' => $overageAmount,
        ];
    }
}
