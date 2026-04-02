<?php

declare(strict_types=1);

namespace Domain\Shared\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Base query filter helper for list endpoints.
 */
abstract class QueryFilter
{
    /**
     * @param  array<string, mixed>  $filters
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
