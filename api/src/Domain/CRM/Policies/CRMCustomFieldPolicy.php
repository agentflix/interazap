<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCustomField;

/**
 * Policy para campos personalizados do CRM.
 *
 * Exige permissões explícitas (crm.custom_fields.*) além do isolamento por tenant.
 */
final class CRMCustomFieldPolicy
{
    /** Permite listar se o usuário tem permissão crm.custom_fields.view. */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.custom_fields.view');
    }

    /** Permite visualizar campo do mesmo tenant com permissão view. */
    public function view(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.view');
    }

    /** Permite criar se o usuário tem permissão crm.custom_fields.create. */
    public function create(AuthUser $user): bool
    {
        return $user->can('crm.custom_fields.create');
    }

    /** Permite atualizar campo do mesmo tenant com permissão update. */
    public function update(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.update');
    }

    /** Permite excluir campo do mesmo tenant com permissão delete. */
    public function delete(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.delete');
    }
}
