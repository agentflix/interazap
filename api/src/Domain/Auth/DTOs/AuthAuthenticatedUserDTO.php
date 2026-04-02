<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Carbon\CarbonInterface;
use Domain\Auth\Models\AuthUser;

/**
 * DTO representing the authenticated user ready for response.
 *
 * @readonly
 */
final readonly class AuthAuthenticatedUserDTO
{
    public function __construct(
        public string $id,
        public ?string $tenantId,
        public string $name,
        public string $email,
        public ?CarbonInterface $emailVerifiedAt,
        public ?string $avatarUrl,
        public bool $twoFactorEnabled,
    ) {}

    /**
     * Create DTO from model.
     */
    public static function fromModel(AuthUser $user): self
    {
        /** @var \Carbon\CarbonInterface|null $verifiedAt */
        $verifiedAt = $user->email_verified_at;

        return new self(
            id: (string) $user->id,
            tenantId: $user->tenant_id ? (string) $user->tenant_id : null,
            name: (string) $user->name,
            email: (string) $user->email,
            emailVerifiedAt: $verifiedAt,
            avatarUrl: $user->avatar_url,
            twoFactorEnabled: (bool) ($user->two_factor_enabled ?? false),
        );
    }

    /**
     * @return array{id:string,tenant_id:?string,name:string,email:string,email_verified_at:?string,avatar_url:?string,two_factor_enabled:bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->emailVerifiedAt?->toISOString(),
            'avatar_url' => $this->avatarUrl,
            'two_factor_enabled' => $this->twoFactorEnabled,
        ];
    }
}
