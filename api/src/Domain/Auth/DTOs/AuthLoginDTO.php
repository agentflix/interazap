<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for login credentials.
 *
 * @readonly
 */
final readonly class AuthLoginDTO
{
    public function __construct(
        public string $email,
        public string $password,
        public bool $remember,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['email', 'password', 'remember']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            email: $payload['email'],
            password: $payload['password'],
            remember: $payload['remember'] ?? false,
        );
    }

    /**
     * @return array{email:string,password:string,remember:bool}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'remember' => $this->remember,
        ];
    }
}
