<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para criação e atualização de perfil de acesso (role).
 *
 * @readonly
 */
final readonly class AuthRoleDTO
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
    ) {}

    /**
     * Cria DTO a partir do form request validado.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria DTO a partir de array com nome e permissões.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            permissions: (array) ($payload['permissions'] ?? []),
        );
    }
}
