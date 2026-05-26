<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para o desafio de autenticação em dois fatores.
 *
 * @readonly
 */
final readonly class AuthLoginTwoFactorDTO
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}

    /**
     * Cria DTO a partir da requisição HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['email', 'code']));
    }

    /**
     * Cria DTO a partir de array com email e código.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            email: $payload['email'],
            code: $payload['code'],
        );
    }

    /**
     * Serializa o DTO para array.
     *
     * @return array{email:string,code:string}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'code' => $this->code,
        ];
    }
}
