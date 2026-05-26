<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMEventClientConfirmation;

/**
 * Policy para confirmações de agendamento do cliente.
 *
 * Reutiliza as permissões de eventos (crm.events.*) para controle de acesso
 * às confirmações de agendamento enviadas ao cliente via WhatsApp.
 */
final class CRMEventClientConfirmationPolicy
{
    /** Permite listar se o usuário tem permissão crm.events.view. */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('crm.events.view');
    }

    /** Permite visualizar confirmação do mesmo tenant com permissão view. */
    public function view(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.view');
    }

    /** Permite criar se o usuário tem permissão crm.events.create. */
    public function create(AuthUser $user): bool
    {
        return $user->can('crm.events.create');
    }

    /** Permite atualizar confirmação do mesmo tenant com permissão update. */
    public function update(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.update');
    }

    /** Permite excluir confirmação do mesmo tenant com permissão delete. */
    public function delete(AuthUser $user, CRMEventClientConfirmation $confirmation): bool
    {
        return $confirmation->tenant_id === $user->tenant_id
            && $user->can('crm.events.delete');
    }
}
