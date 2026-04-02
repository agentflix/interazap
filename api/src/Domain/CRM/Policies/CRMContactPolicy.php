<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;

/**
 * Policy for CRM Contacts.
 */
final class CRMContactPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }
}
