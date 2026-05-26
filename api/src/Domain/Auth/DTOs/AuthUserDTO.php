<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para criação e atualização de usuário.
 *
 * @readonly
 */
final readonly class AuthUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password,
        public string $tenantId,
        public ?string $departmentId,
        public ?string $phone,
        public ?string $role,
        /** @var list<string> */
        public array $roles,
        public bool $isActive,
        public bool $forcePasswordChange = false,
    ) {}

    /**
     * Cria DTO a partir do form request validado.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria DTO a partir de array com dados do usuário.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            email: (string) ($payload['email'] ?? ''),
            password: $payload['password'] ?? null,
            tenantId: (string) ($payload['tenant_id'] ?? ''),
            departmentId: $payload['department_id'] ?? null,
            phone: $payload['phone'] ?? null,
            role: $payload['role'] ?? null,
            roles: (array) ($payload['roles'] ?? []),
            isActive: (bool) ($payload['is_active'] ?? true),
            forcePasswordChange: (bool) ($payload['force_password_change'] ?? false),
        );
    }

    /**
     * Serializa o DTO para array de persistência (campos nulos omitidos).
     *
     * @return array<string, mixed>
     */
    public function toPersistenceArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->tenantId,
            'department_id' => $this->departmentId,
            'phone' => $this->phone,
            'is_active' => $this->isActive,
            'force_password_change' => $this->forcePasswordChange,
        ];

        if ($this->password !== null && $this->password !== '') {
            $data['password'] = $this->password;
        }

        return array_filter($data, static fn ($value) => $value !== null);
    }
}
