<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMCustomField;

/**
 * Policy for CRM Custom Fields.
 */
final class CRMCustomFieldPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.custom_fields.view');
    }

    public function view(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.view');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('crm.custom_fields.create');
    }

    public function update(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.update');
    }

    public function delete(AuthUser $user, CRMCustomField $field): bool
    {
        return $field->tenant_id === $user->tenant_id
            && $user->can('crm.custom_fields.delete');
    }
}
