<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for authenticated user password change.
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
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['current_password', 'password']));
    }

    /**
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
