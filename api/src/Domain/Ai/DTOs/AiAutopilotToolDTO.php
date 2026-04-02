<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for Autopilot tool.
 *
 * @readonly
 */
final readonly class AiAutopilotToolDTO
{
    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        public string $name,
        public ?string $handlerClass = null,
        public ?string $displayName = null,
        public ?string $description = null,
        public ?array $schema = null,
        public bool $isSystem = false,
        public bool $isActive = true,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            handlerClass: $request->input('handler_class'),
            displayName: $request->input('display_name'),
            description: $request->input('description'),
            schema: $request->input('parameters_schema') ? (array) $request->input('parameters_schema') : null,
            isSystem: (bool) $request->input('is_system', false),
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
            handlerClass: $data['handler_class'] ?? null,
            displayName: $data['display_name'] ?? null,
            description: $data['description'] ?? null,
            schema: isset($data['parameters_schema']) ? (array) $data['parameters_schema'] : null,
            isSystem: (bool) ($data['is_system'] ?? false),
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
            'handler_class' => $this->handlerClass,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'parameters_schema' => $this->schema,
            'is_system' => $this->isSystem,
            'is_active' => $this->isActive,
        ];
    }
}
