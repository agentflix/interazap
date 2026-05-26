<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para motivo de perda de negociação.
 *
 * @readonly
 */
final readonly class CRMReasonLossDTO
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $requires_comment = false,
        public bool $is_active = true,
        public int $position = 0,
    ) {}

    /** Cria DTO a partir de um FormRequest já validado. */
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
            description: $data['description'] ?? null,
            requires_comment: (bool) ($data['requires_comment'] ?? false),
            is_active: (bool) ($data['is_active'] ?? true),
            position: (int) ($data['position'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'requires_comment' => $this->requires_comment,
            'is_active' => $this->is_active,
            'position' => $this->position,
        ];
    }
}
