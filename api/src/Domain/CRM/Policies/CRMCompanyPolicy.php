<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;

/**
 * Policy for CRM Companies.
 */
final class CRMCompanyPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function view(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }

    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    public function update(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }

    public function delete(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }
}
