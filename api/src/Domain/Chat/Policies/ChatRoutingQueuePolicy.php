<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatRoutingQueue;

/**
 * Policy para filas de roteamento de atendimentos.
 *
 * Controla o acesso às operações de visualização, gestão e adição de agentes
 * em filas de distribuição automática de tickets, garantindo isolamento por tenant.
 */
final class ChatRoutingQueuePolicy
{
    /** Determina se o usuário pode visualizar uma fila de roteamento específica. */
    public function view(AuthUser $user, ChatRoutingQueue $queue): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $queue->tenant_id === $user->tenant_id
            && $user->can('chat.routing.view');
    }

    /**
     * Determina se o usuário pode criar ou alterar configurações de uma fila de roteamento.
     *
     * Quando `$queue` é fornecida, valida também o isolamento de tenant.
     */
    public function manage(AuthUser $user, ?ChatRoutingQueue $queue = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($queue !== null && $queue->tenant_id !== $user->tenant_id) {
            return false;
        }

        return $user->can('chat.routing.manage');
    }

    /**
     * Verifica se o usuário pode adicionar um agente à fila.
     *
     * Além da permissão de gestão, garante que o agente a ser adicionado
     * pertença ao mesmo tenant da fila (cross-tenant isolation).
     */
    public function addAgent(AuthUser $user, ChatRoutingQueue $queue, string $agentUserId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($queue->tenant_id !== $user->tenant_id) {
            return false;
        }

        if (! $user->can('chat.routing.manage')) {
            return false;
        }

        $agentBelongsToTenant = AuthUser::query()
            ->where('id', $agentUserId)
            ->where('tenant_id', $queue->tenant_id)
            ->exists();

        return $agentBelongsToTenant;
    }
}
