<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Autopilot execution.
 *
 * @readonly
 */
final readonly class AiAutopilotRunDTO
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        public string $playbookId,
        public ?array $context = null,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request, string $playbookId): self
    {
        return self::fromArray([
            'playbook_id' => $playbookId,
            'context' => $request->input('context'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            playbookId: (string) ($data['playbook_id'] ?? ''),
            context: isset($data['context']) && is_array($data['context']) ? $data['context'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'playbook_id' => $this->playbookId,
            'context' => $this->context,
        ];
    }
}
