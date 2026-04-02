<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Funil de Vendas.
 *
 * Retorna dados do funil com conversão por etapa, breakdown por responsável.
 */
final class GetSalesFunnelReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de funil de vendas.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:funnel:%s',
            $dto->tenantId,
            md5((string) json_encode([
                'start_date' => $dto->startDate->toDateString(),
                'end_date' => $dto->endDate->toDateString(),
                'funnel_id' => $dto->funnelId,
                'user_id' => $dto->userId,
                'step_id' => $dto->stepId,
            ], JSON_THROW_ON_ERROR))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            $steps = $this->getSteps($dto);

            return [
                'steps' => $steps,
                'total_negotiations' => (int) array_sum(array_column($steps, 'count')),
                'total_pipeline_value' => (float) array_sum(array_column($steps, 'total_amount')),
                'by_user' => $dto->userId ? $this->getByUser($dto) : [],
            ];
        });
    }

    /**
     * Dados do funil agrupados por etapa.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSteps(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('crm_negotiation_funnel_steps as s', 's.id', '=', 'n.crm_negotiation_funnel_step_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->where('s.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        if ($dto->stepId !== null) {
            $query->where('n.crm_negotiation_funnel_step_id', $dto->stepId);
        }

        if ($dto->userId !== null) {
            $query->where('n.auth_user_id', $dto->userId);
        }

        $rows = $query
            ->selectRaw("
                s.name as step_name,
                COALESCE(s.color, '#94a3b8') as step_color,
                s.\"order\" as step_order,
                COUNT(n.id) as count,
                COALESCE(SUM(n.amount), 0) as total_amount,
                AVG(EXTRACT(EPOCH FROM (COALESCE(n.closed_at, NOW()) - n.created_at)) / 86400) as avg_days_in_step,
                COUNT(CASE WHEN n.expected_close < NOW() AND n.status = 'open' THEN 1 END) as overdue_count
            ")
            ->groupBy('s.id', 's.name', 's.color', 's.order')
            ->orderBy('s.order')
            ->get();

        $totalNegotiations = $rows->sum('count');

        $steps = $rows->map(function (object $row, int $index) use ($rows): array {
            $nextCount = $index + 1 < $rows->count() ? (int) $rows[$index + 1]->count : 0;
            $currentCount = (int) $row->count;
            $conversionRate = $currentCount > 0 ? round(($nextCount / $currentCount) * 100, 2) : 0.0;

            return [
                'step_name' => (string) $row->step_name,
                'step_color' => (string) $row->step_color,
                'step_order' => (int) $row->step_order,
                'count' => $currentCount,
                'total_amount' => round((float) $row->total_amount, 2),
                'avg_days_in_step' => round((float) $row->avg_days_in_step, 1),
                'conversion_rate_to_next' => $conversionRate,
                'overdue_count' => (int) $row->overdue_count,
            ];
        })->all();

        return $steps;
    }

    /**
     * Breakdown do funil por responsável.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByUser(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('auth_users as u', 'u.id', '=', 'n.auth_user_id')
            ->join('crm_negotiation_funnel_steps as s', 's.id', '=', 'n.crm_negotiation_funnel_step_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        if ($dto->userId !== null) {
            $query->where('n.auth_user_id', $dto->userId);
        }

        return $query
            ->selectRaw('
                u.name as user_name,
                s.name as step_name,
                COUNT(n.id) as count,
                COALESCE(SUM(n.amount), 0) as total_amount
            ')
            ->groupBy('u.name', 's.name')
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $row): array => [
                'user_name' => (string) $row->user_name,
                'step_name' => (string) $row->step_name,
                'count' => (int) $row->count,
                'total_amount' => round((float) $row->total_amount, 2),
            ])
            ->all();
    }
}
