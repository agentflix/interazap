<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Actions para métricas de uso de IA.
 */
final class AiUsageActions
{
    /**
     * Estatísticas diárias dos últimos N dias.
     */
    public function daily(string $tenantId, int $days): Collection
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        return AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as requests,
                COALESCE(SUM(input_tokens + output_tokens), 0) as tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as cost
            ')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();
    }

    /**
     * Top agentes por consumo do mês.
     */
    public function topAgents(string $tenantId, int $limit): Collection
    {
        $startDate = Carbon::now()->startOfMonth();

        return AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('feature')
            ->selectRaw('
                feature as agent_name,
                COUNT(*) as total_requests,
                COALESCE(SUM(input_tokens + output_tokens), 0) as total_tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as total_cost
            ')
            ->groupBy('feature')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get();
    }

    /**
     * Histórico mensal de consumo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthlyHistory(string $tenantId, int $months): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        return AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("
                DATE_TRUNC('month', created_at) as month,
                COUNT(*) as requests,
                COALESCE(SUM(input_tokens + output_tokens), 0) as tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as cost
            ")
            ->groupByRaw("DATE_TRUNC('month', created_at)")
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::parse($row->month)->format('Y-m'),
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 4),
            ])
            ->all();
    }
}
