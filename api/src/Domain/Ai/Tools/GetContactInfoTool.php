<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;

/**
 * Ferramenta de IA para recuperar informações de um contato do CRM.
 *
 * Input esperado: contact_id (UUID do contato).
 * Output produzido: dados do contato incluindo nome, email, cidade, telefone, empresa e tags.
 * Quando usar: antes de personalizar comunicação ou verificar dados cadastrais do cliente.
 * Segurança: valida tenant_id para evitar acesso entre tenants.
 */
class GetContactInfoTool implements AiToolInterface
{
    /** Executa a recuperação dos dados do contato. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $contactId = $input->parameters['contact_id'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access contacts from the same tenant
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->with(['company', 'tags'])
            ->find($contactId);

        if (! $contact) {
            return ToolResultDTO::failure('Contact not found');
        }

        return ToolResultDTO::success(
            message: 'Contact info retrieved successfully',
            data: [
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'city' => data_get($contact->custom_fields, 'city'),
                    'phone' => $contact->phone,
                    'phone_e164' => $contact->phone_e164,
                    'company' => $contact->company?->name,
                    'position' => $contact->position,
                    'tags' => $contact->tags->pluck('name')->toArray(),
                    'created_at' => $contact->created_at?->toIso8601String(),
                ],
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Retrieves detailed information about a CRM contact including name, email, city, phone, company, and tags.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'contact_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the contact to retrieve',
            ],
        ];
    }
}
