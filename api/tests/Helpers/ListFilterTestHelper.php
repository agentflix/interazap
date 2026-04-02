<?php

declare(strict_types=1);

namespace Tests\Helpers;

/**
 * Helper para payloads de filtros em testes de listagem.
 */
final class ListFilterTestHelper
{
    /**
     * @return array<string, mixed>
     */
    public static function make(
        ?string $search = null,
        bool|string|null $isActive = null,
        int $perPage = 15,
        string $sortBy = 'created_at',
        string $sortDir = 'desc',
    ): array {
        return [
            'search' => $search,
            'is_active' => $isActive,
            'per_page' => $perPage,
            'sort_by' => $sortBy,
            'sort_dir' => $sortDir,
        ];
    }
}
