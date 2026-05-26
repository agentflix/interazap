<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMReasonLoss;

/**
 * Policy para motivos de perda do CRM.
 *
 * Controla o acesso às operações de motivos de perda garantindo isolamento por tenant.
 */
final class CRMReasonLossPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite visualizar apenas motivos do mesmo tenant. */
    public function view(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite atualizar apenas motivos do mesmo tenant. */
    public function update(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas motivos do mesmo tenant. */
    public function delete(AuthUser $user, CRMReasonLoss $reasonLoss): bool
    {
        return $reasonLoss->tenant_id === $user->tenant_id;
    }
}
