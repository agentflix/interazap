<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for two-factor authentication challenge.
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
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['email', 'code']));
    }

    /**
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
