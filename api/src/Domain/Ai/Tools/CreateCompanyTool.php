<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMCompany;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para criar uma empresa no CRM.
 *
 * Input esperado: name (obrigatório) e campos opcionais como document, email, phone, address, city, state.
 * Output produzido: company_id e nome da empresa criada.
 * Quando usar: cliente mencionar nome da empresa e ainda não existir cadastro no CRM.
 */
class CreateCompanyTool implements AiToolInterface
{
    /** Executa a criação da empresa no CRM. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $name = trim((string) ($input->parameters['name'] ?? ''));
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($name === '') {
            return ToolResultDTO::failure('Company name is required');
        }

        $company = CRMCompany::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'document' => ($document = trim((string) ($input->parameters['document'] ?? ''))) !== '' ? $document : null,
            'email' => ($email = trim((string) ($input->parameters['email'] ?? ''))) !== '' ? $email : null,
            'phone' => ($phone = trim((string) ($input->parameters['phone'] ?? ''))) !== '' ? $phone : null,
            'address' => ($address = trim((string) ($input->parameters['address'] ?? ''))) !== '' ? $address : null,
            'city' => ($city = trim((string) ($input->parameters['city'] ?? ''))) !== '' ? $city : null,
            'state' => ($state = trim((string) ($input->parameters['state'] ?? ''))) !== '' ? $state : null,
            'is_active' => true,
        ]);

        return ToolResultDTO::success(
            message: "Company '{$company->name}' created",
            data: [
                'company_id' => $company->id,
                'name' => $company->name,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Creates a new company in the CRM.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Company name',
            ],
            'document' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company document number',
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company email',
            ],
            'phone' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company phone',
            ],
            'address' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company address',
            ],
            'city' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company city',
            ],
            'state' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Company state',
            ],
        ];
    }
}
