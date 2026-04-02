<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMTag;

/**
 * Policy for CRM Tags.
 */
final class CRMTagPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, CRMTag $tag): bool
    {
        return $tag->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, ?CRMTag $tag = null): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        if ($tag === null) {
            return true;
        }

        return $tag->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, CRMTag $tag): bool
    {
        return $tag->tenant_id === $user->tenant_id;
    }
}
