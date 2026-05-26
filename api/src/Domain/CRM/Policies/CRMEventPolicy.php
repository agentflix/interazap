<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEvent;

/**
 * Policy para eventos de agenda do CRM.
 *
 * Exige permissões explícitas (crm.events.*) além do isolamento por tenant.
 */
final class CRMEventPolicy
{
    /** Permite listar se o usuário tem permissão crm.events.view. */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.events.view');
    }

    /** Permite visualizar evento do mesmo tenant com permissão view. */
    public function view(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.view');
    }

    /** Permite criar se o usuário tem permissão crm.events.create. */
    public function create(AuthUser $user): bool
    {
        return $user->can('crm.events.create');
    }

    /** Permite atualizar evento do mesmo tenant com permissão update. */
    public function update(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.update');
    }

    /** Permite excluir evento do mesmo tenant com permissão delete. */
    public function delete(AuthUser $user, CRMEvent $event): bool
    {
        return $event->tenant_id === $user->tenant_id
            && $user->can('crm.events.delete');
    }
}
