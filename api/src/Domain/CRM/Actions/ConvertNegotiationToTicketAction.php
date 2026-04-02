<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

/**
 * Converts a negotiation into a ticket payload.
 */
final class ConvertNegotiationToTicketAction
{
    public function __construct(
        private readonly ListCRMNegotiationsAction $listAction,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function convert(string $tenantId, string $negotiationId): array
    {
        $negotiation = $this->listAction->find($tenantId, $negotiationId);

        return [
            'negotiation_id' => (string) $negotiation->id,
            'tenant_id' => (string) $negotiation->tenant_id,
            'contact_id' => (string) ($negotiation->crm_contact_id ?? ''),
            'company_id' => (string) ($negotiation->crm_company_id ?? ''),
            'title' => (string) $negotiation->title,
            'status' => $negotiation->status instanceof \BackedEnum ? $negotiation->status->value : (string) $negotiation->status,
            'notes' => (string) ($negotiation->notes ?? ''),
        ];
    }
}
