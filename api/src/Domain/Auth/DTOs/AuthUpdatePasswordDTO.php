<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para troca de senha do usuário autenticado.
 *
 * @readonly
 */
final readonly class AuthUpdatePasswordDTO
{
    public function __construct(
        public string $currentPassword,
        public string $password,
    ) {}

    /**
     * Cria DTO a partir da requisição HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['current_password', 'password']));
    }

    /**
     * Cria DTO a partir de array com senhas atual e nova.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            currentPassword: $payload['current_password'],
            password: $payload['password'],
        );
    }
}
