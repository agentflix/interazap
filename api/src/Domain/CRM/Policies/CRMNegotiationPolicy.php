<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Policy para negociações do CRM.
 *
 * Além do isolamento por tenant, verifica os limites do plano ao criar
 * novas negociações, lançando PlanLimitExceededException quando esgotado.
 */
final class CRMNegotiationPolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    /**
     * Permite criar se o tenant não excedeu o limite de negociações do plano.
     *
     * @throws \Domain\Platform\Exceptions\PlanLimitExceededException Quando o limite do plano é atingido
     */
    public function create(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = (string) $user->tenant_id;
        if ($tenantId === '') {
            return false;
        }

        if (! $this->enforcementService->canCreateNegotiation($tenantId)) {
            throw PlanLimitExceededException::forResource('negociações');
        }

        return true;
    }

    /** Permite listar se o usuário é super-admin ou pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /** Permite visualizar negociação do mesmo tenant ou super-admin. */
    public function view(AuthUser $user, CRMNegotiation $negotiation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $negotiation->tenant_id === $user->tenant_id;
    }

    /** Permite atualizar negociação do mesmo tenant ou super-admin. */
    public function update(AuthUser $user, ?CRMNegotiation $negotiation = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($negotiation === null) {
            return (string) $user->tenant_id !== '';
        }

        return $negotiation->tenant_id === $user->tenant_id;
    }

    /** Permite excluir negociação do mesmo tenant ou super-admin. */
    public function delete(AuthUser $user, CRMNegotiation $negotiation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $negotiation->tenant_id === $user->tenant_id;
    }
}
