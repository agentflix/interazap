<?php

declare(strict_types=1);

namespace Domain\Billing\Policies;

use Domain\Auth\Models\AuthUser;

/**
 * Policy para endpoints de assinatura tenant-facing.
 */
final class BillingSubscriptionPolicy
{
    /** Verifica se o usuário pode acessar dados de assinatura (apenas super-admin ou inquilino). */
    public function viewAny(AuthUser $user): bool
    {
        if (! $user->can('billing.view')) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isInquilino();
    }

    /** Verifica se o usuário pode gerenciar o plano de assinatura (requer billing.plan.manage). */
    public function manage(AuthUser $user): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->can('billing.plan.manage');
    }
}
