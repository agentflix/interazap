<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Tenant Prompt creation and update.
 *
 * @readonly
 */
final readonly class TenantPromptDTO
{
    public function __construct(
        public string $content,
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
            content: (string) ($data['content'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
