<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformPlan;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para gestão de planos da plataforma.
 */
final class PlatformPlanPolicy
{
    private const PERMISSION_MANAGE = 'platform.plans.manage';

    private const GUARD = 'sanctum';

    /** Determina se o usuário pode listar planos. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode visualizar um plano específico. */
    public function view(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode criar planos. */
    public function create(AuthUser $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode atualizar um plano. */
    public function update(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    /** Determina se o usuário pode excluir um plano. */
    public function delete(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    /** Verifica se o usuário é super admin ou inquilino. */
    private function isGlobalAdmin(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
    }

    /** Verifica se o usuário possui a permissão de gerenciamento de planos. */
    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
