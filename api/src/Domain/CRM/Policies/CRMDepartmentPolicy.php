<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMDepartment;

/**
 * Policy para departamentos do CRM.
 *
 * Exige permissões explícitas (crm.departments.*) além do isolamento por tenant.
 */
final class CRMDepartmentPolicy
{
    /** Permite listar se o usuário tem permissão crm.departments.view. */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.departments.view');
    }

    /** Permite visualizar departamento do mesmo tenant com permissão view. */
    public function view(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.view');
    }

    /** Permite criar se o usuário tem permissão crm.departments.create. */
    public function create(AuthUser $user): bool
    {
        return $user->can('crm.departments.create');
    }

    /** Permite atualizar departamento do mesmo tenant com permissão update. */
    public function update(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.update');
    }

    /** Permite excluir departamento do mesmo tenant com permissão delete. */
    public function delete(AuthUser $user, CRMDepartment $department): bool
    {
        return $department->tenant_id === $user->tenant_id
            && $user->can('crm.departments.delete');
    }
}
