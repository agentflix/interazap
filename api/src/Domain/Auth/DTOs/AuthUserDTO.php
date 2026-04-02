<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for authenticated user creation and update.
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
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
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
        );
    }

    /**
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
        ];

        if ($this->password !== null && $this->password !== '') {
            $data['password'] = $this->password;
        }

        return array_filter($data, static fn ($value) => $value !== null);
    }
}
