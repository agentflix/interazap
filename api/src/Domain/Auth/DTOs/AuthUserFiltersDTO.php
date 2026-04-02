<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO for user listing filters.
 *
 * @readonly
 */
final readonly class AuthUserFiltersDTO
{
    public function __construct(
        public ?string $search = null,
        public ?string $tenantId = null,
        public ?bool $isActive = null,
        public ?string $role = null,
        public ?string $departmentId = null,
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
            tenantId: $payload['tenant_id'] ?? null,
            isActive: isset($payload['is_active']) ? filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN) : null,
            role: $payload['role'] ?? null,
            departmentId: $payload['department_id'] ?? null,
            sortBy: $payload['sort_by'] ?? 'name',
            sortDirection: $payload['sort_direction'] ?? 'asc',
            perPage: isset($payload['per_page']) ? (int) $payload['per_page'] : 15,
        );
    }

    public function sanitizedSortBy(): string
    {
        return in_array($this->sortBy, ['name', 'email', 'created_at'], true) ? $this->sortBy : 'name';
    }

    public function sanitizedSortDirection(): string
    {
        return strtolower($this->sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    public function sanitizedPerPage(): int
    {
        return min(max($this->perPage, 1), 100);
    }
}
