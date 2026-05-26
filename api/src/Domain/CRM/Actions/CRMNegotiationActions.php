<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;

/**
 * Fachada de orquestração para os casos de uso de negociações CRM.
 *
 * Delega a execução para actions especializadas através da trait DelegatesCRMNegotiationActions,
 * expondo adicionalmente os métodos de marcação de desfecho (ganha/perdida).
 */
final class CRMNegotiationActions
{
    use DelegatesCRMNegotiationActions;

    /** Marca a negociação como ganha e dispara eventos de desfecho. */
    public function markWon(string $tenantId, string $id): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->markWon($tenantId, $id);
    }

    /** Marca a negociação como perdida registrando o motivo. */
    public function markLost(string $tenantId, string $id, ?string $reasonId): CRMNegotiation
    {
        return app(ChangeNegotiationStageAction::class)->markLost($tenantId, $id, $reasonId);
    }
}
