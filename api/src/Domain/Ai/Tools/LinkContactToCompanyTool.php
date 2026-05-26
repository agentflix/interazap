<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para vincular um contato existente a uma empresa no CRM.
 *
 * Input esperado: contact_id e company_id (UUIDs obrigatórios).
 * Output produzido: contact_id e company_id da associação realizada.
 * Quando usar: cliente revelar a empresa onde trabalha ou confirmar vínculo empresarial.
 */
class LinkContactToCompanyTool implements AiToolInterface
{
    /** Executa o vínculo entre contato e empresa. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $contactId = (string) ($input->parameters['contact_id'] ?? '');
        $companyId = (string) ($input->parameters['company_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($contactId === '' || $companyId === '') {
            return ToolResultDTO::failure('contact_id and company_id are required');
        }

        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->find($contactId);

        if (! $contact) {
            return ToolResultDTO::failure('Contact not found');
        }

        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->find($companyId);

        if (! $company) {
            return ToolResultDTO::failure('Company not found');
        }

        DB::table('crm_company_contacts')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'crm_company_id' => $company->id,
                'crm_contact_id' => $contact->id,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $contact->update([
            'crm_company_id' => $company->id,
        ]);

        return ToolResultDTO::success(
            message: 'Contact linked to company successfully',
            data: [
                'contact_id' => $contact->id,
                'company_id' => $company->id,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Links an existing contact to an existing company in CRM.';
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
                'description' => 'UUID of contact',
            ],
            'company_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of company',
            ],
        ];
    }
}
