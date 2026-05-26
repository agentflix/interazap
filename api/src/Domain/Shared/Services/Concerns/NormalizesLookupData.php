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
    /**
     * Extrai apenas os dígitos de uma string, retornando null se vazio.
     *
     * @param  mixed  $value  Valor de entrada (qualquer tipo).
     * @return string|null Somente os dígitos ou null.
     */
    private function digitsOnly(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return is_string($digits) && $digits !== '' ? $digits : null;
    }

    /**
     * Normaliza UF para 2 letras maiúsculas, retornando null se inválido.
     *
     * @param  mixed  $value  Valor de entrada.
     * @return string|null UF em maiúsculas ou null.
     */
    private function normalizeState(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $state = strtoupper(trim($value));

        return strlen($state) === 2 ? $state : null;
    }

    /**
     * Converte um valor para string trimada, retornando null se vazia.
     *
     * @param  mixed  $value  Valor de entrada.
     * @return string|null String sem espaços nas bordas ou null.
     */
    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
