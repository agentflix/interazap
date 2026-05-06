<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Plan Prompt creation and update.
 *
 * @readonly
 */
final readonly class PlanPromptDTO
{
    public function __construct(
        public string $content,
        public ?bool $isActive = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            content: (string) $request->validated('content'),
            isActive: $request->validated('is_active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'content' => $this->content,
        ];

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
