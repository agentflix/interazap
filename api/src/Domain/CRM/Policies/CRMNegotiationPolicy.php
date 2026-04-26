<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;

/**
 * Policy para negociações do CRM.
 */
final class CRMNegotiationPolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

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

    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    public function view(AuthUser $user, CRMNegotiation $negotiation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $negotiation->tenant_id === $user->tenant_id;
    }

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

    public function delete(AuthUser $user, CRMNegotiation $negotiation): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $negotiation->tenant_id === $user->tenant_id;
    }
}
