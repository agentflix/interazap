<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Utilitário estático que normaliza parâmetros de filtro de listagem.
 *
 * Centraliza a lógica de normalização de is_active, sort_by, sort_direction
 * e per_page para evitar duplicação entre controllers e actions.
 */
final class ListFilterNormalizer
{
    /**
     * Normaliza o filtro `is_active` para booleano, null ou valor original.
     *
     * Aceita strings 'active'/'true'/'1' (true), 'inactive'/'false'/'0' (false)
     * e 'all' (null) quando $allowAll é verdadeiro.
     *
     * @param  mixed  $value  Valor bruto do filtro.
     * @param  bool  $allowAll  Se verdadeiro, aceita 'all' retornando null.
     * @return bool|string|int|float|null Valor normalizado.
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
     * Normaliza o campo de ordenação validando contra uma lista de valores permitidos.
     *
     * @param  mixed  $value  Valor bruto do campo de ordenação.
     * @param  list<string>  $allowed  Lista de campos de ordenação aceitos.
     * @param  string  $default  Valor padrão quando o campo não for permitido.
     * @return string Campo de ordenação validado.
     */
    public static function normalizeSortBy(mixed $value, array $allowed, string $default): string
    {
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            return $default;
        }

        return $value;
    }

    /**
     * Normaliza a direção de ordenação para 'asc' ou 'desc'.
     *
     * @param  mixed  $value  Valor bruto da direção.
     * @param  string  $default  Direção padrão ('asc').
     * @return string Direção normalizada.
     */
    public static function normalizeSortDirection(mixed $value, string $default = 'asc'): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['asc', 'desc'], true) ? $normalized : $default;
    }

    /**
     * Normaliza o número de itens por página respeitando mínimo de 1 e máximo opcional.
     *
     * @param  mixed  $value  Valor bruto de per_page.
     * @param  int  $default  Valor padrão quando inválido (padrão: 15).
     * @param  int|null  $max  Valor máximo permitido (nulo = sem limite).
     * @return int Número normalizado de itens por página.
     */
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
