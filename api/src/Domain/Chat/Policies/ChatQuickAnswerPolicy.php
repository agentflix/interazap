<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatQuickAnswer;

/**
 * Política de Segurança para Respostas Rápidas.
 *
 * Define as regras de autorização para acesso e manipulação das respostas
 * pré-definidas (Quick Answers), garantindo o isolamento multi-tenant.
 *
 * @category Policies
 */
final class ChatQuickAnswerPolicy
{
    /**
     * Determina se o usuário pode listar as respostas rápidas do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('chat.quick_answers.view');
    }

    /**
     * Determina se o usuário pode visualizar uma resposta rápida específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatQuickAnswer  $qa  Modelo da resposta rápida.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function view(AuthUser $user, ChatQuickAnswer $qa): bool
    {
        return $qa->tenant_id === $user->tenant_id
            && $user->can('chat.quick_answers.view');
    }

    /**
     * Determina se o usuário pode criar novas respostas rápidas.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('chat.quick_answers.create');
    }

    /**
     * Determina se o usuário pode atualizar uma resposta rápida existente.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatQuickAnswer  $qa  Modelo da resposta rápida.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function update(AuthUser $user, ChatQuickAnswer $qa): bool
    {
        return $qa->tenant_id === $user->tenant_id
            && $user->can('chat.quick_answers.update');
    }

    /**
     * Determina se o usuário pode remover uma resposta rápida.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatQuickAnswer  $qa  Modelo da resposta rápida.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function delete(AuthUser $user, ChatQuickAnswer $qa): bool
    {
        return $qa->tenant_id === $user->tenant_id
            && $user->can('chat.quick_answers.delete');
    }
}
