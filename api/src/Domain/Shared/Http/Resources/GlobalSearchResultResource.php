<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para o payload de resultados da busca global agrupados por tipo.
 *
 * Serializa os resultados retornados pela GlobalSearchAction em um envelope
 * com data (agrupado por tipo) e meta (total, duração, parâmetros da query).
 */
final class GlobalSearchResultResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, array{total:int,items:list<array<string,mixed>>}> $data */
        $data = is_array($this->resource['data'] ?? null) ? $this->resource['data'] : [];

        $total = 0;
        foreach ($data as $group) {
            $total += $group['total'];
        }

        return [
            'data' => $data,
            'meta' => [
                'query' => (string) ($this->resource['query'] ?? ''),
                'total' => $total,
                'per_type' => (int) ($this->resource['per_type'] ?? 5),
                'duration_ms' => (int) ($this->resource['duration_ms'] ?? 0),
            ],
        ];
    }
}
