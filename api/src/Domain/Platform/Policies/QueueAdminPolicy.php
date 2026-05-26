<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para operações administrativas de filas.
 *
 * Controla acesso às rotas de administração de queues (pause, resume,
 * clean, DLQ, circuit breaker). Requer permissão explícita ou papel
 * de super-admin.
 */
final class QueueAdminPolicy
{
    private const PERMISSION_MANAGE = 'platform.queues.manage';

    private const GUARD = 'sanctum';

    /**
     * Qualquer ação de admin de filas requer permissão.
     */
    public function manage(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $this->hasManagePermission($user);
    }

    /** Verifica se o usuário possui a permissão de gerenciamento de filas. */
    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
