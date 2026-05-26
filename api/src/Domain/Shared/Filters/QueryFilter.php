<?php

declare(strict_types=1);

namespace Domain\Shared\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Classe base para filtros de query em endpoints de listagem.
 *
 * Itera sobre os filtros recebidos e delega a aplicação para métodos
 * concretos definidos nas subclasses, ignorando valores nulos ou vazios.
 */
abstract class QueryFilter
{
    /**
     * Aplica os filtros informados à query Eloquent.
     *
     * @param  Builder  $query  Instância do query builder a filtrar.
     * @param  array<string, mixed>  $filters  Mapa de nome do filtro para valor.
     * @return Builder Query com todos os filtros aplicados.
     */
    public function apply(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || ! method_exists($this, $key)) {
                continue;
            }

            $query = $this->{$key}($query, $value);
        }

        return $query;
    }
}
