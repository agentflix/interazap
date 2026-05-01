<?php

declare(strict_types=1);

namespace Domain\Auth\Policies;

use Domain\Auth\Models\AuthUser;

/**
 * Política de autorização para tokens de dispositivo.
 */
final class AuthDeviceTokenPolicy
{
    /**
     * Qualquer usuário autenticado pode gerenciar seus próprios tokens.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->exists;
    }
}
