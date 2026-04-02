<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Receita e Vendas.
 *
 * Summary, evolução temporal, ranking por responsável e motivos de perda.
 */
final class GetRevenueSalesReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de receita e vendas.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:revenue:%s',
            $dto->tenantId,
            md5(serialize([$dto->startDate->toDateString(), $dto->endDate->toDateString(), $dto->funnelId, $dto->userId, $dto->granularity]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'time_series' => $this->getTimeline($dto),
                'ranking' => $this->getRanking($dto),
                'loss_reasons' => $this->getLossReasons($dto),
            ];
        });
    }

    /**
     * Totais de receita, ticket medio, contagens e win rate.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $base = DB::table('crm_negotiations')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $base->where('crm_negotiation_funnel_id', $dto->funnelId);
        }
        if ($dto->userId !== null) {
            $base->where('auth_user_id', $dto->userId);
        }

        $totalRevenue = (float) (clone $base)->where('status', 'won')->sum('amount');
        $wonCount = (int) (clone $base)->where('status', 'won')->count();
        $lostCount = (int) (clone $base)->where('status', 'lost')->count();
        $openCount = (int) (clone $base)->where('status', 'open')->count();
        $avgTicket = $wonCount > 0 ? round($totalRevenue / $wonCount, 2) : 0.0;
        $totalClosed = $wonCount + $lostCount;
        $winRate = $totalClosed > 0 ? round(($wonCount / $totalClosed) * 100, 2) : 0.0;

        return [
            'total_revenue' => $totalRevenue,
            'avg_ticket' => $avgTicket,
            'won_count' => $wonCount,
            'lost_count' => $lostCount,
            'open_count' => $openCount,
            'win_rate' => $winRate,
        ];
    }

    /**
     * Serie temporal de receita e negocios ganhos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getTimeline(ReportsFilterDTO $dto): array
    {
        $truncExpr = match ($dto->granularity) {
            'week' => "DATE_TRUNC('week', closed_at)",
            'month' => "DATE_TRUNC('month', closed_at)",
            default => "DATE_TRUNC('day', closed_at)",
        };

        $query = DB::table('crm_negotiations')
            ->where('tenant_id', $dto->tenantId)
            ->whereNull('deleted_at')
            ->where('status', 'won')
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('crm_negotiation_funnel_id', $dto->funnelId);
        }
        if ($dto->userId !== null) {
            $query->where('auth_user_id', $dto->userId);
        }

        return $query
            ->selectRaw("{$truncExpr} as date, COALESCE(SUM(amount), 0) as revenue, COUNT(*) as won_count")
            ->groupByRaw($truncExpr)
            ->orderBy('date')
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'revenue' => round((float) $row->revenue, 2),
                'won_count' => (int) $row->won_count,
            ])
            ->all();
    }

    /**
     * Ranking de responsaveis por receita e win rate.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRanking(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('auth_users as u', 'u.id', '=', 'n.auth_user_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        return $query
            ->selectRaw("
                u.name as user_name,
                COALESCE(SUM(CASE WHEN n.status = 'won' THEN n.amount ELSE 0 END), 0) as revenue,
                COUNT(CASE WHEN n.status = 'won' THEN 1 END) as won_count,
                CASE WHEN (COUNT(CASE WHEN n.status = 'won' THEN 1 END) + COUNT(CASE WHEN n.status = 'lost' THEN 1 END)) > 0
                    THEN ROUND(COUNT(CASE WHEN n.status = 'won' THEN 1 END)::numeric / (COUNT(CASE WHEN n.status = 'won' THEN 1 END) + COUNT(CASE WHEN n.status = 'lost' THEN 1 END)) * 100, 2)
                    ELSE 0 END as win_rate
            ")
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn (object $row): array => [
                'user_name' => (string) $row->user_name,
                'revenue' => round((float) $row->revenue, 2),
                'won_count' => (int) $row->won_count,
                'win_rate' => round((float) $row->win_rate, 2),
            ])
            ->all();
    }

    /**
     * Motivos de perda com contagem e valor.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getLossReasons(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('crm_reason_losses as r', 'r.id', '=', 'n.crm_reason_loss_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->where('n.status', 'lost')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }

        return $query
            ->selectRaw('r.name as reason_name, COUNT(n.id) as lost_count, COALESCE(SUM(n.amount), 0) as lost_amount')
            ->groupBy('r.id', 'r.name')
            ->orderByDesc('lost_count')
            ->get()
            ->map(fn (object $row): array => [
                'reason_name' => (string) $row->reason_name,
                'lost_count' => (int) $row->lost_count,
                'lost_amount' => round((float) $row->lost_amount, 2),
            ])
            ->all();
    }
}
