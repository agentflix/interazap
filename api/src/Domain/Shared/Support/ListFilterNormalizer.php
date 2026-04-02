<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Normaliza filtros de listagem para evitar duplicação entre controllers/actions.
 */
final class ListFilterNormalizer
{
    /**
     * Normaliza o filtro `is_active`.
     */
    public static function normalizeIsActive(mixed $value, bool $allowAll = false): bool|string|int|float|null
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['active', 'true', '1'], true)) {
            return true;
        }

        if (in_array($normalized, ['inactive', 'false', '0'], true)) {
            return false;
        }

        if ($allowAll && $normalized === 'all') {
            return null;
        }

        return $value;
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function normalizeSortBy(mixed $value, array $allowed, string $default): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    public static function normalizeSortDirection(mixed $value, string $default = 'asc'): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : $default;
    }

    public static function normalizePerPage(mixed $value, int $default = 15, ?int $max = null): int
    {
        $perPage = (int) $value;
        if ($perPage < 1) {
            $perPage = $default;
        }

        if (is_int($max) && $max > 0) {
            $perPage = min($perPage, $max);
        }

        return $perPage;
    }
}
