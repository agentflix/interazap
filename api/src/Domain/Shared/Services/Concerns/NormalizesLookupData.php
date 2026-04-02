<?php

declare(strict_types=1);

namespace Domain\Shared\Services\Concerns;

/**
 * Métodos utilitários de normalização compartilhados entre lookup services.
 *
 * Extrai apenas dígitos, normaliza UF (2 letras) e trata strings vazias como null.
 */
trait NormalizesLookupData
{
    private function digitsOnly(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    private function normalizeState(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $state = strtoupper(trim($value));

        return strlen($state) === 2 ? $state : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
