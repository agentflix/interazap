<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEventClientConfirmation;

/**
 * Policy for CRM Event Client Confirmations.
 */
final class CRMEventClientConfirmationPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.events.view');
    }

    public function view(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.view');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('crm.events.create');
    }

    public function update(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.update');
    }

    public function delete(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.delete');
    }
}
