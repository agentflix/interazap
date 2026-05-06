<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para gestao de tenants da plataforma.
 */
final class PlatformTenantPolicy
{
    private const PERMISSION_MANAGE = 'platform.tenants.manage';

    private const GUARD = 'sanctum';

    public function viewAny(AuthUser $user): bool
    {
        return $this->create($user);
    }

    public function view(AuthUser $user, PlatformTenant $tenant): bool
    {
        return $this->create($user);
    }

    public function create(AuthUser $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->hasManagePermission($user);
    }

    public function update(AuthUser $user, PlatformTenant $tenant): bool
    {
        return $this->create($user);
    }

    public function delete(AuthUser $user, PlatformTenant $tenant): bool
    {
        return $this->create($user);
    }

    /**
     * Apenas super admins podem impersonar tenants.
     */
    public function impersonate(AuthUser $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Verifica se o usuário pode gerenciar as configurações do tenant.
     *
     * SuperAdmin pode gerenciar qualquer tenant.
     * Usuários comuns precisam pertencer ao tenant E ter a permissão
     * 'platform.tenants.manage'.
     */
    public function updateSettings(AuthUser $user, PlatformTenant $tenant): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant_id === $tenant->id && $this->hasManagePermission($user);
    }

    private function isGlobalAdmin(AuthUser $user): bool
    {
        return $user->isSuperAdmin();
    }

    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
