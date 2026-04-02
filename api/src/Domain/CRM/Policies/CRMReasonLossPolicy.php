<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMReasonLoss;

/**
 * Policy for CRM Reason Losses.
 */
final class CRMReasonLossPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    public function view(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    public function update(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }
}
