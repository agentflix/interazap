<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;

/**
 * Assign a negotiation to a user.
 */
final class AssignNegotiationAction
{
    public function __construct(
        private readonly ListCRMNegotiationsAction $listAction,
    ) {}

    /**
     * Atribuir uma negociação para um responsável dentro do tenant.
     */
    public function assign(string $tenantId, string $negotiationId, string $userId): CRMNegotiation
    {
        $negotiation = $this->listAction->find($tenantId, $negotiationId);
        $negotiation->auth_user_id = $userId;
        $negotiation->save();

        return $negotiation->refresh()->load(['company', 'contact', 'funnel', 'step', 'tags', 'customFieldValues.field', 'user']);
    }
}
