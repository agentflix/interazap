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

        // Token-based overage replaced by message-based billing (FEAT-003).
        // Overage is now handled by CloseExpiredCyclesJob per billing cycle.
        return [
            'total_tokens' => $totalTokens,
            'token_limit_monthly' => null,
            'overage_applied' => false,
            'overage_tokens' => 0,
            'overage_amount' => 0.0,
        ];
    }
}
