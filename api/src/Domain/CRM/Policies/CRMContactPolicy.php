<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;

/**
 * Policy para contatos do CRM.
 *
 * Controla o acesso às operações de contatos garantindo isolamento por tenant.
 */
final class CRMContactPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar apenas contatos do mesmo tenant. */
    public function view(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar apenas contatos do mesmo tenant. */
    public function update(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas contatos do mesmo tenant. */
    public function delete(AuthUser $user, CRMContact $contact): bool
    {
        return $contact->tenant_id === $user->tenant_id;
    }
}
