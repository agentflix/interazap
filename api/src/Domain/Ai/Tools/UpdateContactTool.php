<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;

/**
 * Ferramenta de IA para atualizar dados de um contato no CRM.
 *
 * Input esperado: contact_id (obrigatório) e campos opcionais: name, email, phone, whatsapp, position, document, notes, city.
 * Output produzido: contact_id e lista de campos atualizados.
 * Quando usar: cliente fornecer ou corrigir informações de cadastro durante a conversa.
 */
class UpdateContactTool implements AiToolInterface
{
    /** Executa a atualização dos dados do contato. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $contactId = (string) ($input->parameters['contact_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($contactId === '') {
            return ToolResultDTO::failure('Contact ID is required');
        }

        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->find($contactId);

        if (! $contact) {
            return ToolResultDTO::failure('Contact not found');
        }

        $updatableFields = ['name', 'email', 'phone', 'whatsapp', 'position', 'document', 'notes'];
        $updates = [];

        foreach ($updatableFields as $field) {
            if (! array_key_exists($field, $input->parameters)) {
                continue;
            }

            $value = $input->parameters[$field];
            if (is_string($value)) {
                $value = trim($value);
            }

            $updates[$field] = $value;
        }

        if (array_key_exists('city', $input->parameters)) {
            $city = $input->parameters['city'];
            if (is_string($city)) {
                $city = trim($city);
            }

            $customFields = $contact->custom_fields;
            if (! is_array($customFields)) {
                $customFields = [];
            }

            $customFields['city'] = $city;
            $updates['custom_fields'] = $customFields;
        }

        if ($updates === []) {
            return ToolResultDTO::failure('No valid fields informed to update');
        }

        $contact->update($updates);

        return ToolResultDTO::success(
            message: 'Contact updated successfully',
            data: [
                'contact_id' => $contact->id,
                'updated_fields' => array_keys($updates),
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Updates CRM contact information such as name, email, city, phone, whatsapp, position, document and notes.';
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
                'description' => 'UUID of the contact to update',
            ],
            'name' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact full name',
            ],
            'email' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact email',
            ],
            'city' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact city (stored in custom_fields.city)',
            ],
            'phone' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact phone number',
            ],
            'whatsapp' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact WhatsApp number',
            ],
            'position' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact job position',
            ],
            'document' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Contact document',
            ],
            'notes' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Internal notes for the contact',
            ],
        ];
    }
}
