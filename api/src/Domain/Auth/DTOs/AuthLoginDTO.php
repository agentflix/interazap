<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO para credenciais de login.
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
     * Cria DTO a partir da requisição HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['email', 'password', 'remember', 'device_name']));
    }

    /**
     * Cria DTO a partir de array com dados de login.
     *
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
     * Serializa o DTO para array.
     *
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
