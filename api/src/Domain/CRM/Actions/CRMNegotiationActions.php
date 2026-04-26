<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;

/**
 * Thin orchestration facade for CRM negotiation use-cases.
 */
final class CRMNegotiationActions
{
    use DelegatesCRMNegotiationActions;

    public function markWon(string $tenantId, string $id): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->markWon($tenantId, $id);
    }

    public function markLost(string $tenantId, string $id, ?string $reasonId): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->markLost($tenantId, $id, $reasonId);
    }
}
