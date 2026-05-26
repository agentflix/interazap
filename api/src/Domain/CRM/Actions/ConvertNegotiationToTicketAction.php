<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

/**
 * Converte uma negociação em payload de ticket para integração com o módulo de Chat.
 */
final class ConvertNegotiationToTicketAction
{
    public function __construct(
        private readonly ListCRMNegotiationsAction $listAction,
    ) {}

    /**
     * Converte a negociação em array de dados para criação de ticket.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  string  $negotiationId  ID da negociação
     * @return array<string, mixed> Payload pronto para criação de ticket
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
