<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Performance de Agentes.
 *
 * Retorna métricas por agente: tickets, tempos, CSAT, SLA violations.
 */
final class GetAgentPerformanceReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de performance de agentes.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:agent_perf:%s',
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
            $agents = $this->getAgentMetrics($dto);
            $csatMap = $this->getCsatByAgent($dto);
            $messageMap = $this->getMessagesByAgent($dto);
            $openMap = $this->getOpenTicketsByAgent($dto);

            return [
                'agents' => array_map(function (array $agent) use ($csatMap, $messageMap, $openMap): array {
                    $agentId = $agent['agent_id'];
                    $agent['csat_avg'] = $csatMap[$agentId] ?? 0.0;
                    $agent['messages_sent'] = $messageMap[$agentId] ?? 0;
                    $agent['open_now'] = $openMap[$agentId] ?? 0;
                    unset($agent['agent_id']);

                    return $agent;
                }, $agents),
            ];
        });
    }

    /**
     * Métricas de tickets por agente.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAgentMetrics(ReportsFilterDTO $dto): array
    {
        $query = DB::table('chat_tickets as t')
            ->join('auth_users as u', 'u.id', '=', 't.assigned_to')
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

        return $query
            ->selectRaw('
                u.id as agent_id,
                u.name as agent_name,
                COUNT(t.id) as tickets_handled,
                COUNT(CASE WHEN t.closed_at IS NOT NULL THEN 1 END) as tickets_resolved,
                AVG(EXTRACT(EPOCH FROM (t.first_response_at - t.created_at)) / 60) as avg_first_response_min,
                AVG(CASE WHEN t.closed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (t.closed_at - t.created_at)) / 3600 END) as avg_resolution_hours,
                COUNT(CASE WHEN cte.sla_first_response_breached = true OR cte.sla_resolution_breached = true THEN 1 END) as sla_violations
            ')
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('tickets_handled')
            ->get()
            ->map(fn (object $row): array => [
                'agent_id' => (string) $row->agent_id,
                'agent_name' => (string) $row->agent_name,
                'tickets_handled' => (int) $row->tickets_handled,
                'tickets_resolved' => (int) $row->tickets_resolved,
                'avg_first_response_min' => round((float) ($row->avg_first_response_min ?? 0), 1),
                'avg_resolution_hours' => round((float) ($row->avg_resolution_hours ?? 0), 1),
                'sla_violations' => (int) $row->sla_violations,
            ])->all();
    }

    /**
     * CSAT médio por agente.
     *
     * @return array<string, float>
     */
    private function getCsatByAgent(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('chat_ticket_evaluations as e')
            ->join('chat_tickets as t', 't.id', '=', 'e.ticket_id')
            ->where('e.tenant_id', $dto->tenantId)
            ->whereNotNull('e.submitted_at')
            ->where('e.rating', '>', 0)
            ->whereBetween('e.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw('t.assigned_to as agent_id, AVG(e.rating) as csat_avg')
            ->groupBy('t.assigned_to')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->agent_id !== null) {
                $map[(string) $row->agent_id] = round((float) $row->csat_avg, 2);
            }
        }

        return $map;
    }

    /**
     * Contagem de mensagens enviadas por agente (placeholder — conta tickets resolvidos como proxy).
     *
     * @return array<string, int>
     */
    private function getMessagesByAgent(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('chat_tickets as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereNotNull('t.assigned_to')
            ->whereBetween('t.created_at', [$dto->startDate, $dto->endDate])
            ->selectRaw('t.assigned_to as agent_id, COUNT(t.id) as msg_count')
            ->groupBy('t.assigned_to')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->agent_id] = (int) $row->msg_count;
        }

        return $map;
    }

    /**
     * Tickets abertos atualmente por agente.
     *
     * @return array<string, int>
     */
    private function getOpenTicketsByAgent(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('chat_tickets as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereNull('t.closed_at')
            ->whereNotNull('t.assigned_to')
            ->whereIn('t.status', ['pending', 'open', 'waiting'])
            ->selectRaw('t.assigned_to as agent_id, COUNT(t.id) as open_count')
            ->groupBy('t.assigned_to')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->agent_id] = (int) $row->open_count;
        }

        return $map;
    }
}
