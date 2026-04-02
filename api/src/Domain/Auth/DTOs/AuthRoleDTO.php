<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for role creation and update.
 *
 * @readonly
 */
final readonly class AuthRoleDTO
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $permissions = [],
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
            permissions: (array) ($payload['permissions'] ?? []),
        );
    }
}
