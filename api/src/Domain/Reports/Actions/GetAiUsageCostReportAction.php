<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Uso e Custo de IA.
 *
 * Retorna tokens consumidos, custos por feature/modelo/provider, top usuários e evolução diária.
 */
final class GetAiUsageCostReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de uso e custo de IA.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:ai_usage:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->userId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'by_feature' => $this->getByFeature($dto),
                'by_model' => $this->getByModel($dto),
                'top_users' => $this->getTopUsers($dto),
                'daily_cost' => $this->getDailyCost($dto),
            ];
        });
    }

    /**
     * Totais de tokens e custo.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $row = $this->baseQuery($dto)
            ->selectRaw('
                COALESCE(SUM(l.input_tokens), 0) as total_input_tokens,
                COALESCE(SUM(l.output_tokens), 0) as total_output_tokens,
                COALESCE(SUM(l.input_tokens + l.output_tokens), 0) as total_tokens,
                COALESCE(SUM(l.input_cost + l.output_cost), 0) as total_cost,
                AVG(l.latency_ms) as avg_latency_ms,
                COUNT(l.id) as call_count
            ')
            ->first();

        return [
            'total_input_tokens' => (int) ($row->total_input_tokens ?? 0),
            'total_output_tokens' => (int) ($row->total_output_tokens ?? 0),
            'total_tokens' => (int) ($row->total_tokens ?? 0),
            'total_cost' => round((float) ($row->total_cost ?? 0), 6),
            'avg_latency_ms' => round((float) ($row->avg_latency_ms ?? 0), 1),
            'call_count' => (int) ($row->call_count ?? 0),
        ];
    }

    /**
     * Custo por feature.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByFeature(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->whereNotNull('l.feature')
            ->selectRaw('
                l.feature,
                COALESCE(SUM(l.input_tokens + l.output_tokens), 0) as total_tokens,
                COALESCE(SUM(l.input_cost + l.output_cost), 0) as total_cost,
                AVG(l.latency_ms) as avg_latency_ms,
                COUNT(l.id) as call_count
            ')
            ->groupBy('l.feature')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn (object $row): array => [
                'feature' => (string) $row->feature,
                'total_tokens' => (int) $row->total_tokens,
                'total_cost' => round((float) $row->total_cost, 6),
                'avg_latency_ms' => round((float) ($row->avg_latency_ms ?? 0), 1),
                'call_count' => (int) $row->call_count,
            ])->all();
    }

    /**
     * Custo comparativo por modelo/provider.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByModel(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw('
                l.model_name,
                l.provider,
                COALESCE(SUM(l.input_tokens + l.output_tokens), 0) as total_tokens,
                COALESCE(SUM(l.input_cost + l.output_cost), 0) as total_cost,
                COUNT(l.id) as call_count
            ')
            ->groupBy('l.model_name', 'l.provider')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn (object $row): array => [
                'model_name' => (string) $row->model_name,
                'provider' => (string) ($row->provider ?? ''),
                'total_tokens' => (int) $row->total_tokens,
                'total_cost' => round((float) $row->total_cost, 6),
                'call_count' => (int) $row->call_count,
            ])->all();
    }

    /**
     * Top 10 usuários por consumo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTopUsers(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->join('auth_users as u', 'u.id', '=', 'l.user_id')
            ->selectRaw('
                u.name as user_name,
                COALESCE(SUM(l.input_tokens + l.output_tokens), 0) as total_tokens,
                COALESCE(SUM(l.input_cost + l.output_cost), 0) as total_cost,
                COUNT(l.id) as call_count
            ')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_cost')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'user_name' => (string) $row->user_name,
                'total_tokens' => (int) $row->total_tokens,
                'total_cost' => round((float) $row->total_cost, 6),
                'call_count' => (int) $row->call_count,
            ])->all();
    }

    /**
     * Evolução diária de custo.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDailyCost(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw("DATE_TRUNC('day', l.created_at) as period, COALESCE(SUM(l.input_cost + l.output_cost), 0) as total_cost, COUNT(l.id) as call_count")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn (object $row): array => [
                'period' => (string) $row->period,
                'total_cost' => round((float) $row->total_cost, 6),
                'call_count' => (int) $row->call_count,
            ])->all();
    }

    /**
     * Query base para ai_usage_logs.
     */
    private function baseQuery(ReportsFilterDTO $dto): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('ai_usage_logs as l')
            ->where('l.tenant_id', $dto->tenantId)
            ->whereBetween('l.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->userId !== null) {
            $query->where('l.user_id', $dto->userId);
        }

        return $query;
    }
}
