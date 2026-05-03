<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatRoutingQueue;
use Domain\Chat\Models\ChatRoutingQueueAgent;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\DB;

/**
 * Serviço de Roteamento Automático de Atendimentos.
 *
 * Responsável por distribuir tickets recebidos para agentes conforme
 * a fila e estratégia configuradas (rodízio, menos ocupado, por habilidade).
 *
 * @category Services
 */
final class ChatRoutingService
{
    /**
     * Resolve a fila apropriada e roteia o ticket para um agente.
     *
     * @return string|null UUID do agente atribuído, ou null se não houver fila/agente.
     */
    public function route(ChatTicket $ticket): ?string
    {
        $queue = $this->resolveQueue($ticket);

        if ($queue === null) {
            return null;
        }

        return match ($queue->strategy) {
            'round_robin' => $this->roundRobin($queue),
            'least_busy' => $this->leastBusy($queue),
            'skill_based' => $this->skillBased($queue, $ticket),
            default => null,
        };
    }

    /**
     * Resolve a fila de roteamento para o ticket.
     *
     * Prioridade:
     * 1. Fila específica da instância (instance_id) e habilitada.
     * 2. Fila global (instance_id IS NULL) do tenant e habilitada.
     * 3. Nenhuma fila encontrada.
     */
    private function resolveQueue(ChatTicket $ticket): ?ChatRoutingQueue
    {
        if ($ticket->instance_id !== null) {
            $instanceQueue = ChatRoutingQueue::query()
                ->forInstance($ticket->instance_id)
                ->where('is_enabled', true)
                ->first();

            if ($instanceQueue !== null) {
                return $instanceQueue;
            }
        }

        return ChatRoutingQueue::query()
            ->global()
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * Executa rodízio (round-robin) entre agentes ativos da fila.
     *
     * Utiliza FOR UPDATE SKIP LOCKED para evitar deadlocks em concorrência.
     *
     * @return string|null UUID do agente selecionado, ou null.
     */
    public function roundRobin(ChatRoutingQueue $queue): ?string
    {
        return DB::transaction(function () use ($queue): ?string {
            $agent = ChatRoutingQueueAgent::query()
                ->where('queue_id', $queue->id)
                ->where('is_active', true)
                ->orderByRaw('last_assigned_at ASC NULLS FIRST')
                ->orderBy('position', 'asc')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($agent === null) {
                return null;
            }

            $agent->last_assigned_at = now();
            $agent->save();

            return $agent->user_id;
        });
    }

    /**
     * Executa distribuição por menor carga (least-busy) entre agentes ativos.
     *
     * Ordena agentes pelo número de tickets abertos (status pending/open).
     * Respeita max_open_tickets_per_agent quando configurado (NULL = ilimitado).
     * Desempata por last_assigned_at ASC NULLS FIRST, depois por position.
     *
     * @return string|null UUID do agente selecionado, ou null.
     */
    public function leastBusy(ChatRoutingQueue $queue): ?string
    {
        return DB::transaction(function () use ($queue): ?string {
            $agent = $this->resolveLeastBusyAgent($queue);

            if ($agent === null) {
                return null;
            }

            $agent->last_assigned_at = now();
            $agent->save();

            return $agent->user_id;
        });
    }

    /**
     * Resolve o agente com menor carga de tickets abertos.
     *
     * Usa subquery correlacionada para contar tickets sem JOIN (evita lock
     * em múltiplas tabelas com FOR UPDATE SKIP LOCKED).
     */
    private function resolveLeastBusyAgent(ChatRoutingQueue $queue): ?ChatRoutingQueueAgent
    {
        $tenantId = $queue->tenant_id;
        $maxOpen = $queue->max_open_tickets_per_agent;

        $baseQuery = ChatRoutingQueueAgent::query()
            ->where('queue_id', $queue->id)
            ->where('is_active', true);

        if ($maxOpen !== null) {
            $baseQuery->whereRaw(
                "(
                    SELECT COUNT(*)
                    FROM chat_tickets t
                    WHERE t.assigned_to = chat_routing_queue_agents.user_id
                      AND t.tenant_id = ?
                      AND t.status IN ('pending', 'open')
                ) < ?",
                [$tenantId, $maxOpen]
            );
        }

        return $baseQuery
            ->orderByRaw(
                "(
                    SELECT COUNT(*)
                    FROM chat_tickets t
                    WHERE t.assigned_to = chat_routing_queue_agents.user_id
                      AND t.tenant_id = ?
                      AND t.status IN ('pending', 'open')
                ) ASC",
                [$tenantId]
            )
            ->orderByRaw('last_assigned_at ASC NULLS FIRST')
            ->orderBy('position', 'asc')
            ->lock('FOR UPDATE SKIP LOCKED')
            ->first();
    }

    /**
     * Executa distribuição por habilidade (skill-based) entre agentes ativos.
     *
     * Filtra agentes cujas skills batem com a categoria do ticket.
     * Aplica round-robin no grupo filtrado.
     * Se nenhum agente tiver a skill, retorna null (sem atribuição automática).
     *
     * @return string|null UUID do agente selecionado, ou null.
     */
    public function skillBased(ChatRoutingQueue $queue, ChatTicket $ticket): ?string
    {
        $category = $ticket->category;

        if ($category === null || $category === '') {
            return null;
        }

        return DB::transaction(function () use ($queue, $category): ?string {
            $agent = ChatRoutingQueueAgent::query()
                ->where('queue_id', $queue->id)
                ->where('is_active', true)
                ->whereExists(function ($query) use ($queue, $category): void {
                    $query->select(DB::raw(1))
                        ->from('chat_routing_agent_skills')
                        ->whereColumn('chat_routing_agent_skills.user_id', 'chat_routing_queue_agents.user_id')
                        ->where('chat_routing_agent_skills.queue_id', $queue->id)
                        ->where('chat_routing_agent_skills.skill', $category);
                })
                ->orderByRaw('last_assigned_at ASC NULLS FIRST')
                ->orderBy('position', 'asc')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($agent === null) {
                return null;
            }

            $agent->last_assigned_at = now();
            $agent->save();

            return $agent->user_id;
        });
    }
}
