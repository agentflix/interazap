<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNote;

/**
 * Policy for CRM Notes.
 */
final class CRMNotePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.notes.view');
    }

    public function view(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.view');
    }

    public function create(AuthUser $user): bool
    {
        return $user->can('crm.notes.create');
    }

    public function update(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.update');
    }

    public function delete(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.delete');
    }
}
