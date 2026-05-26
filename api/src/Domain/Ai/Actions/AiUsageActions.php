<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Casos de uso para consulta de métricas de consumo de IA.
 *
 * Contexto: agrega dados do model AiUsageLog para relatórios de uso diário,
 * ranking de agentes e histórico mensal. Todas as queries agrupam por tenant.
 */
final class AiUsageActions
{
    /**
     * Retorna estatísticas diárias de uso dos últimos N dias.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  int  $days  Quantidade de dias retroativos.
     * @return \Illuminate\Database\Eloquent\Collection<int, AiUsageLog> Coleção com date, requests, tokens e cost.
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
     * Retorna os N agentes com maior custo de IA no mês corrente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  int  $limit  Número máximo de agentes retornados.
     * @return \Illuminate\Database\Eloquent\Collection<int, AiUsageLog> Coleção com agent_name, total_requests, total_tokens e total_cost.
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
