<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNote;

/**
 * Policy para notas do CRM.
 *
 * Exige permissões explícitas (crm.notes.*) além do isolamento por tenant.
 */
final class CRMNotePolicy
{
    /** Permite listar se o usuário tem permissão crm.notes.view. */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.notes.view');
    }

    /** Permite visualizar nota do mesmo tenant com permissão view. */
    public function view(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.view');
    }

    /** Permite criar se o usuário tem permissão crm.notes.create. */
    public function create(AuthUser $user): bool
    {
        return $user->can('crm.notes.create');
    }

    /** Permite atualizar nota do mesmo tenant com permissão update. */
    public function update(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.update');
    }

    /** Permite excluir nota do mesmo tenant com permissão delete. */
    public function delete(AuthUser $user, CRMNote $note): bool
    {
        return $note->tenant_id === $user->tenant_id
            && $user->can('crm.notes.delete');
    }
}
