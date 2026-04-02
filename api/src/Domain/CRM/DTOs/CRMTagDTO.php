<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for CRM tag.
 *
 * @readonly
 */
final readonly class CRMTagDTO
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public ?string $category = null,
        public bool $isActive = true,
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
            color: $data['color'] ?? null,
            category: $data['category'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'category' => $this->category,
            'is_active' => $this->isActive,
        ];
    }
}
