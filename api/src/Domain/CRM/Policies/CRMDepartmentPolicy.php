<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMDepartment;

/**
 * Policy for CRM Departments.
 */
final class CRMDepartmentPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.departments.view');
    }

    public function view(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.view');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('crm.departments.create');
    }

    public function update(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.update');
    }

    public function delete(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.delete');
    }
}
