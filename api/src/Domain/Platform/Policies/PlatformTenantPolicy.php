<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para gestão de tenants da plataforma.
 */
final class PlatformTenantPolicy
{
    private const PERMISSION_MANAGE = 'platform.tenants.manage';

    private const GUARD = 'sanctum';

    /** Determina se o usuário pode listar tenants. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode visualizar um tenant específico. */
    public function view(AuthUser $user, PlatformTenant $tenant): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode criar tenants. */
    public function create(AuthUser $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode atualizar um tenant. */
    public function update(AuthUser $user, PlatformTenant $tenant): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode excluir um tenant. */
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

    /** Verifica se o usuário é super admin. */
    private function isGlobalAdmin(AuthUser $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Verifica se o usuário possui a permissão de gerenciamento de tenants. */
    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
