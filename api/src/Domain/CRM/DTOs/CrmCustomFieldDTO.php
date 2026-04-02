<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM custom field.
 *
 * @readonly
 */
final readonly class CRMCustomFieldDTO
{
    /**
     * @param  array<int, string>|null  $options
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $entity,
        public ?array $options = null,
        public bool $is_required = false,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            type: (string) ($data['type'] ?? ''),
            entity: (string) ($data['entity'] ?? 'contact'),
            options: isset($data['options']) && is_array($data['options']) ? $data['options'] : null,
            is_required: (bool) ($data['is_required'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'entity' => $this->entity,
            'options' => $this->options,
            'is_required' => $this->is_required,
        ];
    }
}
