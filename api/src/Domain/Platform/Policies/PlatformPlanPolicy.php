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

    public function viewAny(AuthUser $user): bool
    {
        return $this->create($user);
    }

    public function view(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    public function create(AuthUser $user): bool
    {
        return $this->isGlobalAdmin($user) || $this->hasManagePermission($user);
    }

    public function update(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    public function delete(AuthUser $user, PlatformPlan $plan): bool
    {
        return $this->create($user);
    }

    private function isGlobalAdmin(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
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
