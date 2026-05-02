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
            'least_busy' => null,
            'skill_based' => null,
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
}
