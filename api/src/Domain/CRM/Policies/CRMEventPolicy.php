<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEvent;

/**
 * Policy for CRM Events (Agenda).
 */
final class CRMEventPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.events.view');
    }

    public function view(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.view');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('crm.events.create');
    }

    public function update(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.update');
    }

    public function delete(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.delete');
    }
}
