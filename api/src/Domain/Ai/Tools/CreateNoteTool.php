<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNote;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para criar notas em entidades do CRM.
 *
 * Input esperado: entity_type (contact|negotiation), entity_id e content.
 * Output produzido: note_id, entity_type e entity_id vinculados.
 * Quando usar: registrar observações, resumos de conversa ou itens de ação em contatos ou negociações.
 * Segurança: valida tenant_id para evitar acesso entre tenants.
 */
class CreateNoteTool implements AiToolInterface
{
    /**
     * @var array<string, class-string>
     */
    private const array ENTITY_MAP = [
        'contact' => CRMContact::class,
        'negotiation' => CRMNegotiation::class,
    ];

    /** Executa a criação da nota na entidade informada. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $entityType = $input->parameters['entity_type'] ?? null;
        $entityId = $input->parameters['entity_id'] ?? null;
        $content = $input->parameters['content'] ?? '';
        $tenantId = $input->context['tenant_id'] ?? null;

        // Validate entity type
        if (! isset(self::ENTITY_MAP[$entityType])) {
            return ToolResultDTO::failure(
                'Invalid entity type. Allowed: '.implode(', ', array_keys(self::ENTITY_MAP))
            );
        }

        // Validate content
        if (empty(trim($content))) {
            return ToolResultDTO::failure('Content cannot be empty');
        }

        // Tenant isolation: only access entities from the same tenant
        $modelClass = self::ENTITY_MAP[$entityType];
        $entity = $modelClass::query()
            ->where('tenant_id', $tenantId)
            ->find($entityId);

        if (! $entity) {
            return ToolResultDTO::failure(ucfirst($entityType).' not found');
        }

        // Create note
        $note = CRMNote::create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId ?? $entity->tenant_id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'content' => trim($content),
            'auth_user_id' => null, // AI-created note
        ]);

        return ToolResultDTO::success(
            message: 'Note created successfully',
            data: [
                'note_id' => $note->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Creates a note attached to a CRM entity (contact or negotiation). Use to record important observations, conversation summaries, or action items.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'entity_type' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Type of entity: contact, negotiation',
            ],
            'entity_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the entity',
            ],
            'content' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Content of the note',
            ],
        ];
    }
}
