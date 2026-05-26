<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessageTemplate;

/**
 * Política de autorização para templates de mensagem de chat.
 *
 * Garante que apenas usuários do tenant com a permissão `chat.templates.manage`
 * possam criar, atualizar, remover ou sincronizar templates.
 */
final class ChatMessageTemplatePolicy
{
    /** Determina se o usuário pode listar templates do tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Determina se o usuário pode visualizar um template específico do tenant. */
    public function view(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template);
    }

    /** Determina se o usuário pode criar novos templates. */
    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '' && $user->hasPermissionTo('chat.templates.manage');
    }

    /** Determina se o usuário pode atualizar um template existente. */
    public function update(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template) && $user->hasPermissionTo('chat.templates.manage');
    }

    /** Determina se o usuário pode excluir um template. */
    public function delete(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template) && $user->hasPermissionTo('chat.templates.manage');
    }

    /** Determina se o usuário pode sincronizar templates com o provedor externo. */
    public function sync(AuthUser $user): bool
    {
        return $this->create($user);
    }

    /**
     * Verifica se o template pertence ao mesmo tenant do usuário.
     */
    private function belongsToTenant(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $template->tenant_id === $user->tenant_id;
    }
}
