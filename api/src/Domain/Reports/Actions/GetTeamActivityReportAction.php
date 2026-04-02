<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\DTOs\ReportsFilterDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Relatório de Atividade da Equipe.
 *
 * Retorna métricas de atividade por usuário e lista de usuários inativos.
 */
final class GetTeamActivityReportAction extends AbstractReportAction
{
    /**
     * Executa o relatório de atividade da equipe.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array
    {
        $cacheKey = sprintf(
            'reports:%s:team:%s',
            $dto->tenantId,
            md5(serialize([
                $dto->startDate->toDateString(),
                $dto->endDate->toDateString(),
                $dto->userId,
            ]))
        );

        /** @var array<string, mixed> */
        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($dto): array {
            $users = $this->getUsers($dto);
            $ticketStats = $this->getTicketStats($dto);
            $negotiationStats = $this->getNegotiationStats($dto);
            $taskStats = $this->getTaskStats($dto);

            return [
                'members' => array_map(function (array $user) use ($ticketStats, $negotiationStats, $taskStats): array {
                    $id = $user['user_id'];
                    $tickets = $ticketStats[$id] ?? ['created' => 0, 'resolved' => 0];
                    $negotiations = $negotiationStats[$id] ?? ['created' => 0, 'won' => 0];
                    $tasks = $taskStats[$id] ?? 0;

                    return [
                        'user_id' => $id,
                        'user_name' => $user['user_name'],
                        'last_login_at' => null,
                        'tickets_created' => $tickets['created'],
                        'tickets_resolved' => $tickets['resolved'],
                        'negotiations_created' => $negotiations['created'],
                        'negotiations_won' => $negotiations['won'],
                        'tasks_done' => $tasks,
                        'notes_created' => 0,
                        'events_created' => 0,
                        'is_inactive' => ! $user['is_active'],
                    ];
                }, $users),
                'inactive_count' => count(array_filter($users, fn (array $u): bool => ! $u['is_active'])),
            ];
        });
    }

    /**
     * Lista de usuários do tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getUsers(ReportsFilterDTO $dto): array
    {
        $query = DB::table('auth_users as u')
            ->where('u.tenant_id', $dto->tenantId)
            ->whereNull('u.deleted_at');

        if ($dto->userId !== null) {
            $query->where('u.id', $dto->userId);
        }

        return $query
            ->select(['u.id', 'u.name', 'u.is_active'])
            ->orderBy('u.name')
            ->get()
            ->map(fn (object $row): array => [
                'user_id' => (string) $row->id,
                'user_name' => (string) $row->name,
                'is_active' => (bool) $row->is_active,
            ])->all();
    }

    /**
     * Estatísticas de tickets por usuário.
     *
     * @return array<string, array<string, int>>
     */
    private function getTicketStats(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('chat_tickets as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->whereNull('t.deleted_at')
            ->whereBetween('t.created_at', [$dto->startDate, $dto->endDate])
            ->whereNotNull('t.assigned_to')
            ->selectRaw('
                t.assigned_to as user_id,
                COUNT(t.id) as created,
                COUNT(CASE WHEN t.closed_at IS NOT NULL THEN 1 END) as resolved
            ')
            ->groupBy('t.assigned_to')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->user_id] = [
                'created' => (int) $row->created,
                'resolved' => (int) $row->resolved,
            ];
        }

        return $map;
    }

    /**
     * Estatísticas de negociações por usuário.
     *
     * @return array<string, array<string, int>>
     */
    private function getNegotiationStats(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('crm_negotiations as n')
            ->where('n.tenant_id', $dto->tenantId)
            ->whereNull('n.deleted_at')
            ->whereBetween('n.created_at', [$dto->startDate, $dto->endDate])
            ->whereNotNull('n.auth_user_id')
            ->selectRaw("
                n.auth_user_id as user_id,
                COUNT(n.id) as created,
                COUNT(CASE WHEN n.status = 'won' THEN 1 END) as won
            ")
            ->groupBy('n.auth_user_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->user_id] = [
                'created' => (int) $row->created,
                'won' => (int) $row->won,
            ];
        }

        return $map;
    }

    /**
     * Tarefas concluídas por usuário.
     *
     * @return array<string, int>
     */
    private function getTaskStats(ReportsFilterDTO $dto): array
    {
        $rows = DB::table('crm_negotiation_tasks as t')
            ->where('t.tenant_id', $dto->tenantId)
            ->where('t.status', 'done')
            ->whereBetween('t.updated_at', [$dto->startDate, $dto->endDate])
            ->whereNotNull('t.auth_user_id')
            ->selectRaw('t.auth_user_id as user_id, COUNT(t.id) as count')
            ->groupBy('t.auth_user_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->user_id] = (int) $row->count;
        }

        return $map;
    }
}
