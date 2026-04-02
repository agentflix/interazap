<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatAutoReplyRule;

/**
 * Política de Segurança para Regras de Auto Reply.
 *
 * Define as regras de autorização para acesso e manipulação das regras
 * de automação, garantindo que usuários acessem apenas dados de seu tenant.
 *
 * @category Policies
 */
final class ChatAutoReplyRulePolicy
{
    /**
     * Determina se o usuário pode listar as regras de auto reply do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('chat.auto_reply_rules.view');
    }

    /**
     * Determina se o usuário pode visualizar uma regra específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatAutoReplyRule  $rule  Modelo da regra.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function view(AuthUser $user, ChatAutoReplyRule $rule): bool
    {
        return $rule->tenant_id === $user->tenant_id
            && $user->can('chat.auto_reply_rules.view');
    }

    /**
     * Determina se o usuário pode criar novas regras de auto reply.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('chat.auto_reply_rules.create');
    }

    /**
     * Determina se o usuário pode atualizar uma regra existente.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatAutoReplyRule  $rule  Modelo da regra.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function update(AuthUser $user, ChatAutoReplyRule $rule): bool
    {
        return $rule->tenant_id === $user->tenant_id
            && $user->can('chat.auto_reply_rules.update');
    }

    /**
     * Determina se o usuário pode remover uma regra de auto reply.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatAutoReplyRule  $rule  Modelo da regra.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function delete(AuthUser $user, ChatAutoReplyRule $rule): bool
    {
        return $rule->tenant_id === $user->tenant_id
            && $user->can('chat.auto_reply_rules.delete');
    }
}
