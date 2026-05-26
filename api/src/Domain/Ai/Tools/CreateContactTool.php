<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para criar um contato no CRM.
 *
 * Input esperado: name e phone (obrigatórios); email, whatsapp, position opcionais.
 * Output produzido: contact_id e nome do contato criado.
 * Quando usar: novo lead identificado que ainda não possui cadastro no CRM.
 */
class CreateContactTool implements AiToolInterface
{
    /** Executa a criação do contato no CRM. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $name = trim((string) ($input->parameters['name'] ?? ''));
        $phone = trim((string) ($input->parameters['phone'] ?? ''));
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($name === '' || $phone === '') {
            return ToolResultDTO::failure('Name and phone are required');
        }

        $contact = CRMContact::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $phone,
            'email' => ($email = trim((string) ($input->parameters['email'] ?? ''))) !== '' ? $email : null,
            'whatsapp' => ($whatsapp = trim((string) ($input->parameters['whatsapp'] ?? ''))) !== '' ? $whatsapp : null,
            'position' => ($position = trim((string) ($input->parameters['position'] ?? ''))) !== '' ? $position : null,
            'is_active' => true,
        ]);

        return ToolResultDTO::success(
            message: "Contact '{$contact->name}' created",
            data: [
                'contact_id' => $contact->id,
                'name' => $contact->name,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Creates a new contact in the CRM.';
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
                'description' => 'Contact full name',
            ],
            'phone' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Primary phone number',
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Email address',
            ],
            'whatsapp' => [
                'type' => 'string',
                'required' => false,
                'description' => 'WhatsApp phone number',
            ],
            'position' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Job position',
            ],
        ];
    }
}
