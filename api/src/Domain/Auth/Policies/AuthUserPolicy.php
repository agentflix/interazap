<?php

declare(strict_types=1);

namespace Domain\Auth\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Política de autorização para gestão de usuários.
 */
final class AuthUserPolicy
{
    private const PERMISSION_VIEW = 'users.user.view';

    private const PERMISSION_MANAGE = 'users.user.manage';

    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    /** Permite listar usuários para super-admins e quem tem permissão de visualização/gestão. */
    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->hasPermission($user, self::PERMISSION_VIEW)
            || $this->hasPermission($user, self::PERMISSION_MANAGE);
    }

    /** Permite visualizar usuário para quem pode listar. */
    public function view(AuthUser $user, AuthUser $model): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Permite criar usuário verificando o limite de usuários do plano do tenant.
     *
     * @throws PlanLimitExceededException Se o plano atingiu o limite de usuários.
     */
    public function create(AuthUser $user): bool
    {
        $canManage = $this->canManageUsers($user);

        if (! $canManage) {
            return false;
        }

        $tenantId = (string) $user->tenant_id;
        if ($tenantId === '') {
            return true;
        }

        if (! $this->enforcementService->canCreateUser($tenantId)) {
            throw PlanLimitExceededException::forResource('usuários');
        }

        return true;
    }

    /** Permite atualizar usuário, exceto se o alvo for super-admin e o solicitante nao for. */
    public function update(AuthUser $user, AuthUser $model): bool
    {
        if ($model->isSuperAdmin() && ! $user->isSuperAdmin()) {
            return false;
        }

        return $this->canManageUsers($user);
    }

    /** Permite excluir usuário, exceto super-admins. */
    public function delete(AuthUser $user, AuthUser $model): bool
    {
        if ($model->isSuperAdmin()) {
            return false;
        }

        return $this->canManageUsers($user);
    }

    /** Permite alternar status de usuário, exceto super-admins. */
    public function toggle(AuthUser $user, AuthUser $model): bool
    {
        if ($model->isSuperAdmin()) {
            return false;
        }

        return $this->canManageUsers($user);
    }

    /**
     * Verifica se o usuário pode atualizar as preferências de outro usuário.
     *
     * Apenas o próprio usuário pode atualizar suas próprias preferências.
     */
    public function updatePreferences(AuthUser $user, AuthUser $model): bool
    {
        return $user->id === $model->id;
    }

    /**
     * Apenas super admins podem impersonar usuários.
     */
    public function impersonate(AuthUser $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Verifica se o usuário pode gerenciar outros usuários (super-admin, inquilino ou permissão explícita). */
    private function canManageUsers(AuthUser $user): bool
    {
        return $user->isSuperAdmin()
            || $user->isInquilino()
            || $this->hasPermission($user, self::PERMISSION_MANAGE);
    }

    /** Verifica permissão tratando PermissionDoesNotExist como false. */
    private function hasPermission(AuthUser $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission, 'sanctum');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
