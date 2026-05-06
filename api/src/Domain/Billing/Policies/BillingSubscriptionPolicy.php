<?php

declare(strict_types=1);

namespace Domain\Billing\Policies;

use Domain\Auth\Models\AuthUser;

/**
 * Policy para endpoints de assinatura tenant-facing.
 */
final class BillingSubscriptionPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        if (! $user->can('billing.view')) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isInquilino();
    }

    public function manage(AuthUser $user): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->can('billing.plan.manage');
    }
}
