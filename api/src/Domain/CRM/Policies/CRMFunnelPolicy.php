<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiationFunnel;

/**
 * Policy para funis de vendas do CRM.
 *
 * Controla o acesso às operações de funis garantindo isolamento por tenant.
 */
final class CRMFunnelPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar apenas funis do mesmo tenant. */
    public function view(AuthUser $user, CRMNegotiationFunnel $funnel): bool
    {
        return $funnel->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar apenas funis do mesmo tenant. */
    public function update(AuthUser $user, CRMNegotiationFunnel $funnel): bool
    {
        return $funnel->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas funis do mesmo tenant. */
    public function delete(AuthUser $user, CRMNegotiationFunnel $funnel): bool
    {
        return $funnel->tenant_id === $user->tenant_id;
    }
}
