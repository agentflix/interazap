<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Http\Request;

/**
 * DTO for authenticated user profile update.
 *
 * @readonly
 */
final readonly class AuthProfileDTO
{
    public function __construct(
        public string $name,
        public ?string $avatarUrl = null,
        public ?string $phone = null,
    ) {}

    /**
     * Create DTO from request.
     */
    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->only(['name', 'avatar_url', 'phone']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            avatarUrl: $payload['avatar_url'] ?? null,
            phone: $payload['phone'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
            'phone' => $this->phone,
        ], static fn ($value) => $value !== null);
    }
}
