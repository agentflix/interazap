<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotificationPreference;

/**
 * Política de autorização para preferências de notificação do usuário.
 *
 * Garante que cada usuário acesse e edite somente as próprias preferências
 * dentro do tenant ao qual pertence.
 */
final class ConfigurationNotificationPreferencePolicy
{
    /** Permite listar preferências se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar apenas preferências do próprio usuário no mesmo tenant. */
    public function view(AuthUser $user, ConfigurationNotificationPreference $preference): bool
    {
        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }

    /** Permite criar preferências se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar preferências do próprio usuário no mesmo tenant. */
    public function update(AuthUser $user, ?ConfigurationNotificationPreference $preference = null): bool
    {
        if ($preference === null) {
            return (bool) $user->tenant_id;
        }

        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }

    /** Permite excluir apenas preferências do próprio usuário no mesmo tenant. */
    public function delete(AuthUser $user, ConfigurationNotificationPreference $preference): bool
    {
        return $preference->tenant_id === $user->tenant_id
            && $preference->user_id === $user->id;
    }
}
