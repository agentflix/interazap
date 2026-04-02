<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Domain\Ai\Enums\AutopilotTriggerType;
use Illuminate\Http\Request;

/**
 * DTO for Autopilot playbook.
 *
 * @readonly
 */
final readonly class AiAutopilotPlaybookDTO
{
    /**
     * @param  array<string, mixed>|null  $steps
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public AutopilotTriggerType $triggerType = AutopilotTriggerType::MANUAL,
        public int $version = 1,
        public ?array $steps = null,
        public ?array $metadata = null,
        public bool $isActive = true,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            description: $request->input('description'),
            triggerType: AutopilotTriggerType::from((string) $request->input('trigger_type', AutopilotTriggerType::MANUAL->value)),
            version: (int) $request->input('version', 1),
            steps: $request->input('steps') ? (array) $request->input('steps') : null,
            metadata: $request->input('metadata') ? (array) $request->input('metadata') : null,
            isActive: (bool) $request->input('is_active', true),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: $data['description'] ?? null,
            triggerType: AutopilotTriggerType::from((string) ($data['trigger_type'] ?? AutopilotTriggerType::MANUAL->value)),
            version: (int) ($data['version'] ?? 1),
            steps: isset($data['steps']) ? (array) $data['steps'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null,
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
            'description' => $this->description,
            'trigger_type' => $this->triggerType->value,
            'version' => $this->version,
            'steps' => $this->steps,
            'metadata' => $this->metadata,
            'is_active' => $this->isActive,
        ];
    }
}
