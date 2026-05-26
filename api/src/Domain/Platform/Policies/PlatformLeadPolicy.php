<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformLead;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para gestão administrativa de leads da plataforma.
 */
final class PlatformLeadPolicy
{
    private const PERMISSION_MANAGE = 'platform.tenants.manage';

    private const GUARD = 'sanctum';

    /** Determina se o usuário pode listar leads. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode visualizar um lead específico. */
    public function view(AuthUser $user, PlatformLead $lead): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode criar/converter leads. */
    public function create(AuthUser $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->hasManagePermission($user);
    }

    /** Verifica se o usuário é super admin ou inquilino. */
    private function isGlobalAdmin(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
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
