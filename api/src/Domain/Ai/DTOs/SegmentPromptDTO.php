<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Segment Prompt creation and update.
 *
 * @readonly
 */
final readonly class SegmentPromptDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $content,
        public ?string $masterId = null,
        public ?string $description = null,
        public ?bool $isActive = null,
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
            code: (string) ($data['code'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            masterId: $data['master_id'] ?? null,
            description: $data['description'] ?? null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'content' => $this->content,
        ];

        if ($this->masterId !== null) {
            $data['master_id'] = $this->masterId;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
