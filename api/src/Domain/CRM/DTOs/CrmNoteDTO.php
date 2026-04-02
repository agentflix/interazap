<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM note.
 *
 * @readonly
 */
final readonly class CRMNoteDTO
{
    public function __construct(
        public string $entity_type,
        public string $entity_id,
        public string $content,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request, string $entityType, string $entityId): self
    {
        return self::fromArray($request->validated(), $entityType, $entityId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $entityType = '', string $entityId = ''): self
    {
        return new self(
            entity_type: $entityType ?: ($data['entity_type'] ?? ''),
            entity_id: $entityId ?: ($data['entity_id'] ?? ''),
            content: (string) ($data['content'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'content' => $this->content,
        ];
    }
}
