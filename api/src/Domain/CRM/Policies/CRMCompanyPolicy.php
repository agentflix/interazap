<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCompany;

/**
 * Policy para empresas do CRM.
 *
 * Controla o acesso às operações de empresas garantindo isolamento por tenant.
 */
final class CRMCompanyPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar apenas empresas do mesmo tenant. */
    public function view(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar apenas empresas do mesmo tenant. */
    public function update(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas empresas do mesmo tenant. */
    public function delete(AuthUser $user, CRMCompany $company): bool
    {
        return $company->tenant_id === $user->tenant_id;
    }
}
