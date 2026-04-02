<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Uazapi instance creation.
 *
 * @readonly
 */
final readonly class PlatformUazapiInstanceDTO
{
    /**
     * @param  array<string, mixed>  $config  Campos de configuração JSONB.
     */
    public function __construct(
        public string $name,
        public ?string $systemName = null,
        public array $config = [],
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
            systemName: $data['system_name'] ?? null,
            config: (array) ($data['config'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'system_name' => $this->systemName,
            'config' => $this->config,
        ];
    }
}
