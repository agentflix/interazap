<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatMessageTemplate;

/**
 * Policy para templates de mensagem de chat.
 */
final class ChatMessageTemplatePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    public function view(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template);
    }

    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '' && $user->hasPermissionTo('chat.templates.manage');
    }

    public function update(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template) && $user->hasPermissionTo('chat.templates.manage');
    }

    public function delete(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $this->belongsToTenant($user, $template) && $user->hasPermissionTo('chat.templates.manage');
    }

    public function sync(AuthUser $user): bool
    {
        return $this->create($user);
    }

    private function belongsToTenant(AuthUser $user, ChatMessageTemplate $template): bool
    {
        return $template->tenant_id === $user->tenant_id;
    }
}
