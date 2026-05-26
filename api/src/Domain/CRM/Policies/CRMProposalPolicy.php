<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMProposal;

/**
 * Policy para propostas comerciais do CRM.
 *
 * Controla o acesso às operações de propostas garantindo isolamento por tenant.
 */
final class CRMProposalPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite visualizar apenas propostas do mesmo tenant. */
    public function view(AuthUser $user, CRMProposal $proposal): bool
    {
        return $proposal->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite atualizar apenas propostas do mesmo tenant. */
    public function update(AuthUser $user, CRMProposal $proposal): bool
    {
        return $proposal->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas propostas do mesmo tenant. */
    public function delete(AuthUser $user, CRMProposal $proposal): bool
    {
        return $proposal->tenant_id === $user->tenant_id;
    }
}
