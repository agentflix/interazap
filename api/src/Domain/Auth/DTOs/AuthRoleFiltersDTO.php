<?php

declare(strict_types=1);

namespace Domain\Auth\DTOs;

/**
 * DTO para filtros de listagem de perfis de acesso (roles).
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
     * Cria DTO a partir de array de parâmetros de filtro.
     *
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

    /** Retorna coluna de ordenação validada (name|created_at). */
    public function sanitizedSortBy(): string
    {
        return in_array($this->sortBy, ['name', 'created_at'], true) ? $this->sortBy : 'name';
    }

    /** Retorna direção de ordenação validada (asc|desc). */
    public function sanitizedSortDirection(): string
    {
        return in_array(strtolower($this->sortDirection), ['asc', 'desc'], true) ? strtolower($this->sortDirection) : 'asc';
    }

    /** Retorna quantidade de itens por página limitada ao intervalo [1, 100]. */
    public function sanitizedPerPage(): int
    {
        return max(1, min($this->perPage, 100));
    }
}
