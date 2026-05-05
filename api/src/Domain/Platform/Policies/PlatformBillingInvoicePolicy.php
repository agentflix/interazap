<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;

/**
 * Policy para gerenciamento de faturas na visão de plataforma (admin).
 */
final class PlatformBillingInvoicePolicy
{
    private const GUARD = 'sanctum';

    /**
     * Determina se o usuário pode visualizar faturas da plataforma.
     */
    public function view(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
    }

    /**
     * Determina se o usuário pode criar faturas na plataforma.
     */
    public function create(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
    }

    /**
     * Determina se o usuário pode cancelar/excluir uma fatura da plataforma.
     */
    public function delete(AuthUser $user): bool
    {
        return $user->isSuperAdmin() || $user->isInquilino();
    }
}
