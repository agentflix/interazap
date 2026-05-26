<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Support\Carbon;

/**
 * Action responsável por resumir métricas de uso de IA do mês corrente com comparação mensal.
 *
 * Calcula total de requisições, tokens, custos, latência média e variação
 * percentual de custo em relação ao mês anterior.
 */
final class AiUsageSummaryAction
{
    /**
     * Calcula o resumo de consumo de IA do mês corrente com comparação ao mês anterior.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return array<string, mixed> Resumo com requests, tokens, cost, avg_latency_ms e cost_change_percent.
     */
    public function execute(string $tenantId): array
    {
        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now()->endOfMonth();

        $stats = AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_requests,
                COALESCE(SUM(input_tokens), 0) as total_input_tokens,
                COALESCE(SUM(output_tokens), 0) as total_output_tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as total_cost,
                COALESCE(AVG(latency_ms), 0) as avg_latency_ms
            ')
            ->first();

        $prevStart = Carbon::now()->subMonth()->startOfMonth();
        $prevEnd = Carbon::now()->subMonth()->endOfMonth();

        $prevStats = AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->selectRaw('COALESCE(SUM(input_cost + output_cost), 0) as total_cost')
            ->first();

        $prevCost = (float) ($prevStats->total_cost ?? 0);
        $currentCost = (float) ($stats->total_cost ?? 0);
        $costChange = $prevCost > 0
            ? round((($currentCost - $prevCost) / $prevCost) * 100, 2)
            : 0;

        return [
            'period_start' => $startDate->toIso8601String(),
            'period_end' => $endDate->toIso8601String(),
            'total_requests' => (int) $stats->total_requests,
            'total_input_tokens' => (int) $stats->total_input_tokens,
            'total_output_tokens' => (int) $stats->total_output_tokens,
            'total_tokens' => (int) $stats->total_input_tokens + (int) $stats->total_output_tokens,
            'total_cost' => round($currentCost, 4),
            'avg_latency_ms' => round((float) $stats->avg_latency_ms, 2),
            'cost_change_percent' => $costChange,
        ];
    }
}
