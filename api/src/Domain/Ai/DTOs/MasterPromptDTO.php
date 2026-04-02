<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Master Prompt creation and update.
 *
 * @readonly
 */
final readonly class MasterPromptDTO
{
    public function __construct(
        public string $name,
        public string $content,
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
            name: (string) ($data['name'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'content' => $this->content,
        ];

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
