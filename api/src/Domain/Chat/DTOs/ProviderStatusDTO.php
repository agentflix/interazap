<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

/**
 * DTO for provider instance status.
 *
 * @readonly
 */
final readonly class ProviderStatusDTO
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public bool $connected,
        public ?string $phone = null,
        public ?string $name = null,
        public ?array $rawResponse = null,
    ) {}
}
