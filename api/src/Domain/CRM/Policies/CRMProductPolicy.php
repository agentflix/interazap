<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMProduct;

/**
 * Policy for CRM Products.
 */
final class CRMProductPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    public function view(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    public function update(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }
}
