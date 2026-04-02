<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de SLA e Tempo de Resolução.
 *
 * Retorna métricas de SLA, tempos de resposta/resolução por prioridade e agente.
 */
final class GetSlaResolutionReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de SLA e tempo de resolução.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:sla:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->channel,
                $dto->instanceId,
                $dto->userId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            return [
                'summary' => $this->getSummary($dto),
                'by_priority' => $this->getByPriority($dto),
                'by_agent' => $this->getByAgent($dto),
                'overdue_tickets' => $this->getOverdueTickets($dto),
            ];
        });
    }

    /**
     * Summary com taxas de SLA e tempos médios.
     *
     * @return array<string, mixed>
     */
    private function getSummary(ReportsFilterDTO $dto): array
    {
        $query = $this->baseQuery($dto);

        $row = $query
            ->selectRaw('
                COUNT(t.id) as total,
                COUNT(CASE WHEN cte.sla_first_response_breached = false AND t.first_response_at IS NOT NULL THEN 1 END) as sla_first_ok,
                COUNT(CASE WHEN t.first_response_at IS NOT NULL THEN 1 END) as responded,
                COUNT(CASE WHEN cte.sla_resolution_breached = false AND t.closed_at IS NOT NULL THEN 1 END) as sla_resolution_ok,
                COUNT(CASE WHEN t.closed_at IS NOT NULL THEN 1 END) as resolved,
                AVG(EXTRACT(EPOCH FROM (t.first_response_at - t.created_at)) / 60) as avg_first_response_min,
                AVG(EXTRACT(EPOCH FROM (t.closed_at - t.created_at)) / 3600) as avg_resolution_hours
            ')
            ->first();

        if ($row === null || (int) $row->total === 0) {
            return [
                'sla_first_response_rate' => 0.0,
                'sla_resolution_rate' => 0.0,
                'avg_first_response_minutes' => 0.0,
                'avg_resolution_hours' => 0.0,
                'total_tickets' => 0,
            ];
        }

        $responded = (int) $row->responded;
        $resolved = (int) $row->resolved;

        return [
            'sla_first_response_rate' => $responded > 0
                ? round(((int) $row->sla_first_ok / $responded) * 100, 2)
                : 0.0,
            'sla_resolution_rate' => $resolved > 0
                ? round(((int) $row->sla_resolution_ok / $resolved) * 100, 2)
                : 0.0,
            'avg_first_response_minutes' => round((float) ($row->avg_first_response_min ?? 0), 1),
            'avg_resolution_hours' => round((float) ($row->avg_resolution_hours ?? 0), 1),
            'total_tickets' => (int) $row->total,
        ];
    }

    /**
     * Métricas por prioridade.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByPriority(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->selectRaw('
                t.priority,
                COUNT(t.id) as count,
                AVG(EXTRACT(EPOCH FROM (t.first_response_at - t.created_at)) / 60) as avg_first_response_min,
                AVG(EXTRACT(EPOCH FROM (t.closed_at - t.created_at)) / 3600) as avg_resolution_hours,
                COUNT(CASE WHEN cte.sla_first_response_breached = true THEN 1 END) as first_response_breached,
                COUNT(CASE WHEN cte.sla_resolution_breached = true THEN 1 END) as resolution_breached
            ')
            ->groupBy('t.priority')
            ->orderBy('t.priority')
            ->get()
            ->map(fn (object $row): array => [
                'priority' => (string) $row->priority,
                'count' => (int) $row->count,
                'avg_first_response_minutes' => round((float) ($row->avg_first_response_min ?? 0), 1),
                'avg_resolution_hours' => round((float) ($row->avg_resolution_hours ?? 0), 1),
                'first_response_breached' => (int) $row->first_response_breached,
                'resolution_breached' => (int) $row->resolution_breached,
            ])->all();
    }

    /**
     * SLA compliance e tempos por agente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getByAgent(ReportsFilterDTO $dto): array
    {
        return $this->baseQuery($dto)
            ->join('auth_users as u', 'u.id', '=', 't.assigned_to')
            ->selectRaw('
                u.name as agent_name,
                COUNT(t.id) as count,
                COUNT(CASE WHEN cte.sla_first_response_breached = true THEN 1 END) as sla_violations,
                AVG(EXTRACT(EPOCH FROM (t.first_response_at - t.created_at)) / 60) as avg_first_response_min,
                AVG(EXTRACT(EPOCH FROM (t.closed_at - t.created_at)) / 3600) as avg_resolution_hours
            ')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn (object $row): array => [
                'agent_name' => (string) $row->agent_name,
                'count' => (int) $row->count,
                'sla_violations' => (int) $row->sla_violations,
                'avg_first_response_minutes' => round((float) ($row->avg_first_response_min ?? 0), 1),
                'avg_resolution_hours' => round((float) ($row->avg_resolution_hours ?? 0), 1),
            ])->all();
    }

    /**
     * Tickets abertos há mais de 24h/48h/72h.
     *
     * @return array<string, int>
     */
    private function getOverdueTickets(ReportsFilterDTO $dto): array
    {
        $query = DB::table('chat_tickets as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.closed_at')
            ->whereIn('t.status', ['pending', 'open', 'waiting']);

        return [
            'over_24h' => (int) (clone $query)->where('t.created_at', '<', now()->subHours(24))->count(),
            'over_48h' => (int) (clone $query)->where('t.created_at', '<', now()->subHours(48))->count(),
            'over_72h' => (int) (clone $query)->where('t.created_at', '<', now()->subHours(72))->count(),
        ];
    }

    /**
     * Query base com filtros comuns de chat_tickets.
     */
    private function baseQuery(ReportsFilterDTO $dto): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('chat_tickets as t')
            ->leftJoin('chat_tickets_extended as cte', 'cte.ticket_id', '=', 't.id')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', [$dto->startDate, $dto->endDate]);

        if ($dto->channel !== null) {
            $query->where('t.channel', $dto->channel);
        }

        if ($dto->instanceId !== null) {
            $query->where('t.instance_id', $dto->instanceId);
        }

        if ($dto->userId !== null) {
            $query->where('t.assigned_to', $dto->userId);
        }

        return $query;
    }
}
