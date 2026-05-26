<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Carbon\CarbonImmutable;
use Domain\Ai\Models\AiUsageLog;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\DB;

/**
 * Calcula o excedente mensal de tokens de IA para um tenant.
 *
 * Mantido por compatibilidade. A bilhetagem migrou de tokens para mensagens (FEAT-003):
 * o excedente real agora é calculado por ciclo via CloseExpiredCyclesJob.
 */
final class CalculateAiOverageAction
{
    /**
     * Calcula os totais de tokens consumidos no mês de referência.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $referenceMonth  Mês no formato YYYY-MM
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
