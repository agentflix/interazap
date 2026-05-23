<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Policy para instâncias de chat.
 */
final class ChatInstancePolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    public function create(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = (string) $user->tenant_id;
        if ($tenantId === '') {
            return false;
        }

        if (! $this->enforcementService->canCreateInstance($tenantId)) {
            throw PlanLimitExceededException::forResource('instâncias WhatsApp');
        }

        return true;
    }

    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.channel.view')
            || $user->can('channels.whatsapp.view');
    }

    public function view(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.view') || $user->can('channels.whatsapp.view'));
    }

    public function update(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.manage') || $user->can('channels.whatsapp.manage'));
    }

    public function delete(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.manage') || $user->can('channels.whatsapp.manage'));
    }
}
