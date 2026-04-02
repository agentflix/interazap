<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;

/**
 * Política de Segurança para Tickets de Chat.
 *
 * Define as regras de autorização para gerenciamento de tickets,
 * incluindo visualização, criação, atualização e transferência entre agentes.
 *
 * @category Policies
 */
final class ChatTicketPolicy
{
    /**
     * Determina se o usuário pode listar os tickets do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.view');
    }

    /**
     * Determina se o usuário pode visualizar um ticket específico.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function view(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.view') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode criar manualmente novos tickets.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function create(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.create');
    }

    /**
     * Determina se o usuário pode atualizar as informações de um ticket.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function update(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.update') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode excluir um ticket.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function delete(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.delete') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode transferir um ticket para outro agente ou setor.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function transfer(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.transfer') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode forçar o encerramento de um ticket.
     *
     * Restricted to managers and admins with explicit permission.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão de gerente.
     */
    public function forceClose(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.tickets.force_close') && $ticket->tenant_id === $user->tenant_id;
    }
}
