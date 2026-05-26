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
    /** Permite listar roles apenas para super-admins. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /** Permite visualizar role apenas para super-admins. */
    public function view(AuthUser $user, AuthRole $role): bool
    {
        return $this->viewAny($user);
    }

    /** Permite criar roles apenas para super-admins. */
    public function create(AuthUser $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /** Permite atualizar roles apenas para super-admins. */
    public function update(AuthUser $user, AuthRole $role): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Permite excluir roles apenas para super-admins, exceto o perfil Administrador.
     */
    public function delete(AuthUser $user, AuthRole $role): bool
    {
        if ($role->id === AuthRole::ADMINISTRADOR_ID) {
            return false;
        }

        return $this->isSuperAdmin($user);
    }

    /** Permite listar usuários de uma role apenas para super-admins. */
    public function viewUsers(AuthUser $user, AuthRole $role): bool
    {
        return $this->viewAny($user);
    }

    /** Verifica se o usuário possui a role de super-admin. */
    private function isSuperAdmin(AuthUser $user): bool
    {
        return $user->hasRoleId(AuthRole::ADMINISTRADOR_ID);
    }
}
