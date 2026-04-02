<?php

declare(strict_types=1);

namespace Domain\Shared\Support;

/**
 * Utilitário para sanitização de termos de busca em queries LIKE/ILIKE.
 *
 * Escapa caracteres wildcard (%, _) do input do usuário para evitar
 * buscas lentas com padrões maliciosos.
 */
final class SearchSanitizer
{
    /**
     * Escapa wildcards LIKE/ILIKE em uma string de busca do usuário.
     *
     * @param  string  $term  Termo de busca do usuário.
     * @return string Termo com wildcards escapados.
     */
    public static function escapeLike(string $term): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $term,
        );
    }

    /**
     * Retorna um pattern LIKE/ILIKE seguro com wildcards nas extremidades.
     *
     * @param  string  $term  Termo de busca do usuário.
     * @return string Pattern no formato "%{escaped_term}%".
     */
    public static function likeContains(string $term): string
    {
        return '%'.self::escapeLike($term).'%';
    }
}
