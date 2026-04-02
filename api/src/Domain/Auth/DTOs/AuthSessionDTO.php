<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO for authenticated session with permissions and optional token.
 *
 * @readonly
 */
final readonly class AuthSessionDTO
{
    /**
     * @param  array<int, string>  $permissions
     * @param  array<string, mixed>|null  $tenantPlan
     */
    public function __construct(
        public AuthAuthenticatedUserDTO $user,
        public array $permissions,
        public ?array $tenantPlan = null,
        public ?string $token = null,
    ) {}

    /**
     * @return array{user:array<string,mixed>,permissions:array<int,string>,tenant_plan:?array,token:?string}
     */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'permissions' => $this->permissions,
            'tenant_plan' => $this->tenantPlan,
            'token' => $this->token,
        ];
    }
}
