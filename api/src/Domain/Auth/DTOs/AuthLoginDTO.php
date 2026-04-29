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
        public ?string $deviceName = null,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['email', 'password', 'remember', 'device_name']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $deviceName = $payload['device_name'] ?? null;

        return new self(
            email: $payload['email'],
            password: $payload['password'],
            remember: $payload['remember'] ?? false,
            deviceName: is_string($deviceName) && $deviceName !== '' ? $deviceName : null,
        );
    }

    /**
     * @return array{email:string,password:string,remember:bool,device_name:?string}
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'remember' => $this->remember,
            'device_name' => $this->deviceName,
        ];
    }
}
