<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;

/**
 * Política de Segurança para Mensagens de Chat.
 *
 * Define as regras de autorização para envio e visualização de mensagens
 * dentro do contexto de um ticket de atendimento.
 *
 * @category Policies
 */
final class ChatMessagePolicy
{
    /**
     * Determina se o usuário pode visualizar as mensagens de um ticket.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function viewAny(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.messages.view') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode enviar novas mensagens para um ticket.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTicket  $ticket  Modelo do ticket.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function create(AuthUser $user, ChatTicket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.messages.create') && $ticket->tenant_id === $user->tenant_id;
    }

    /**
     * Determina se o usuário pode visualizar metadados de mensagens.
     */
    public function view(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $user->can('chat.messages.view');
    }

    /**
     * Determina se o usuário pode editar/reagir mensagens.
     */
    public function update(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $user->can('chat.messages.update');
    }

    /**
     * Determina se o usuário pode excluir mensagens.
     */
    public function delete(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $user->can('chat.messages.delete');
    }
}
