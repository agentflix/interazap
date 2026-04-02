<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Performance de Vendedores.
 *
 * Métricas individuais de cada vendedor: negociações, receita, tarefas, propostas.
 */
final class GetSalespersonPerformanceReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de performance de vendedores.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:salesperson:%s',
            $dto->tenantId,
            md5(serialize([$dto->startDate->toDateString(), $dto->endDate->toDateString(), $dto->funnelId, $dto->userId]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return ['salespersons' => $this->getSalespersons($dto)];
        });
    }

    /**
     * Estatisticas consolidades por vendedor (negociacoes, tarefas, propostas).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSalespersons(ReportsFilterDTO $dto): array
    {
        $query = DB::table('crm_negotiations as n')
            ->join('auth_users as u', 'u.id', '=', 'n.auth_user_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->funnelId !== null) {
            $query->where('n.crm_negotiation_funnel_id', $dto->funnelId);
        }
        if ($dto->userId !== null) {
            $query->where('n.auth_user_id', $dto->userId);
        }

        $negotiations = $query
            ->selectRaw("
                u.id as user_id,
                u.name as user_name,
                COUNT(CASE WHEN n.status = 'won' THEN 1 END) as won_count,
                COUNT(CASE WHEN n.status = 'lost' THEN 1 END) as lost_count,
                COUNT(CASE WHEN n.status = 'open' THEN 1 END) as open_count,
                COALESCE(SUM(CASE WHEN n.status = 'won' THEN n.amount ELSE 0 END), 0) as total_revenue,
                AVG(n.lead_score) as avg_lead_score,
                AVG(CASE WHEN n.status = 'won' AND n.closed_at IS NOT NULL
                    THEN EXTRACT(EPOCH FROM (n.closed_at - n.created_at)) / 86400
                    ELSE NULL END) as avg_close_days
            ")
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_revenue')
            ->get();

        $userIds = $negotiations->pluck('user_id')->all();

        $tasks = $this->getTaskStats($dto, $userIds);
        $proposals = $this->getProposalStats($dto, $userIds);

        return $negotiations->map(function (object $row) use ($tasks, $proposals): array {
            $userId = (string) $row->user_id;
            $wonCount = (int) $row->won_count;
            $lostCount = (int) $row->lost_count;
            $totalClosed = $wonCount + $lostCount;
            $totalRevenue = round((float) $row->total_revenue, 2);
            $winRate = $totalClosed > 0 ? round(($wonCount / $totalClosed) * 100, 2) : 0.0;
            $avgTicket = $wonCount > 0 ? round($totalRevenue / $wonCount, 2) : 0.0;
            $taskData = $tasks[$userId] ?? ['done' => 0, 'overdue' => 0];
            $proposalData = $proposals[$userId] ?? ['sent' => 0, 'accepted' => 0];

            return [
                'user_name' => (string) $row->user_name,
                'won_count' => $wonCount,
                'lost_count' => $lostCount,
                'open_count' => (int) $row->open_count,
                'win_rate' => $winRate,
                'total_revenue' => $totalRevenue,
                'avg_ticket' => $avgTicket,
                'avg_lead_score' => $row->avg_lead_score !== null ? round((float) $row->avg_lead_score, 1) : 0.0,
                'avg_close_days' => $row->avg_close_days !== null ? round((float) $row->avg_close_days, 1) : 0.0,
                'tasks_done' => (int) $taskData['done'],
                'tasks_overdue' => (int) $taskData['overdue'],
                'proposals_sent' => (int) $proposalData['sent'],
                'proposals_accepted' => (int) $proposalData['accepted'],
            ];
        })->all();
    }

    /**
     * Estatisticas de tarefas por vendedor (concluidas e atrasadas).
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<string, int>>
     */
    private function getTaskStats(ReportsFilterDTO $dto, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = DB::table('crm_negotiation_tasks')
            ->where('tenant_id', $dto->tenantId)
            ->whereIn('auth_user_id', $userIds)
            ->whereBetween('created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw("
                auth_user_id,
                COUNT(CASE WHEN status = 'done' THEN 1 END) as done,
                COUNT(CASE WHEN status != 'done' AND due_date < NOW() THEN 1 END) as overdue
            ")
            ->groupBy('auth_user_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->auth_user_id] = [
                'done' => (int) $row->done,
                'overdue' => (int) $row->overdue,
            ];
        }

        return $result;
    }

    /**
     * Estatisticas de propostas por vendedor (enviadas e aceitas).
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<string, int>>
     */
    private function getProposalStats(ReportsFilterDTO $dto, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = DB::table('crm_proposals as p')
            ->join('crm_negotiations as n', 'n.id', '=', 'p.crm_negotiation_id')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereIn('n.auth_user_id', $userIds)
            ->whereBetween('p.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw('
                n.auth_user_id,
                COUNT(CASE WHEN p.sent_at IS NOT NULL THEN 1 END) as sent,
                COUNT(CASE WHEN p.accepted_at IS NOT NULL THEN 1 END) as accepted
            ')
            ->groupBy('n.auth_user_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row->auth_user_id] = [
                'sent' => (int) $row->sent,
                'accepted' => (int) $row->accepted,
            ];
        }

        return $result;
    }
}
