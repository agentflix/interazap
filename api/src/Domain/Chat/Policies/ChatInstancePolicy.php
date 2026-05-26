<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Política de autorização para instâncias de canal de chat (WhatsApp, Webchat, etc.).
 *
 * Verifica limites do plano ao criar novas instâncias e garante o isolamento
 * multi-tenant nas operações de visualização, atualização e remoção.
 */
final class ChatInstancePolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    /**
     * Verifica se o tenant ainda tem cota para criar novas instâncias conforme o plano.
     *
     * @throws PlanLimitExceededException Se o limite de instâncias do plano for atingido.
     */
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

    /** Determina se o usuário pode listar instâncias de canal. */
    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->can('chat.channel.view')
            || $user->can('channels.whatsapp.view');
    }

    /** Determina se o usuário pode visualizar uma instância específica do tenant. */
    public function view(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.view') || $user->can('channels.whatsapp.view'));
    }

    /** Determina se o usuário pode atualizar as configurações de uma instância. */
    public function update(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.manage') || $user->can('channels.whatsapp.manage'));
    }

    /** Determina se o usuário pode remover uma instância do tenant. */
    public function delete(AuthUser $user, ChatInstance $instance): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id
            && ($user->can('chat.channel.manage') || $user->can('channels.whatsapp.manage'));
    }
}
