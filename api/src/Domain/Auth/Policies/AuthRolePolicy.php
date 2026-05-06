<?php

declare(strict_types=1);

namespace Domain\Auth\Policies;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;

/**
 * Política de autorização para gestão de roles.
 */
final class AuthRolePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(AuthUser $user, AuthRole $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(AuthUser $user, AuthRole $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(AuthUser $user, AuthRole $role): bool
    {
        if ($role->id === AuthRole::ADMINISTRADOR_ID) {
            return false;
        }

        return $this->isSuperAdmin($user);
    }

    public function viewUsers(AuthUser $user, AuthRole $role): bool
    {
        return $this->viewAny($user);
    }

    private function isSuperAdmin(AuthUser $user): bool
    {
        return $user->hasRoleId(AuthRole::ADMINISTRADOR_ID);
    }
}
