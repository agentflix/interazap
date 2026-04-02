<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO for role listing filters.
 *
 * @readonly
 */
final readonly class AuthRoleFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public string $sortBy = 'name',
        public string $sortDirection = 'asc',
        public int $perPage = 15,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            search: $payload['search'] ?? null,
            sortBy: $payload['sort_by'] ?? 'name',
            sortDirection: $payload['sort_direction'] ?? 'asc',
            perPage: isset($payload['per_page']) ? (int) $payload['per_page'] : 15,
        );
    }

    public function sanitizedSortBy(): string
    {
        return in_array($this->sortBy, ['name', 'created_at'], true) ? $this->sortBy : 'name';
    }

    public function sanitizedSortDirection(): string
    {
        return in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'asc';
    }

    public function sanitizedPerPage(): int
    {
        return max(1, min($this->perPage, 100));
    }
}
