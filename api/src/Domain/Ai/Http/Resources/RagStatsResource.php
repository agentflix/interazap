<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\DTOs\RagStatsDTO;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for RAG statistics.
 *
 * @mixin RagStatsDTO
 */
final class RagStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        /** @var RagStatsDTO $dto */
        $dto = $this->resource;

        return [
            'total_queries' => $dto->totalQueries,
            'zero_results_rate' => round($dto->zeroResultsRate, 4),
            'p50_latency_ms' => $dto->p50LatencyMs !== null ? round($dto->p50LatencyMs, 2) : null,
            'p95_latency_ms' => $dto->p95LatencyMs !== null ? round($dto->p95LatencyMs, 2) : null,
            'p99_latency_ms' => $dto->p99LatencyMs !== null ? round($dto->p99LatencyMs, 2) : null,
            'avg_top_score' => $dto->avgTopScore !== null ? round($dto->avgTopScore, 4) : null,
            'avg_results_count' => $dto->avgResultsCount !== null ? round($dto->avgResultsCount, 2) : null,
            'mode_distribution' => $dto->modeDistribution,
            'top_query_hashes' => $dto->topQueryHashes,
        ];
    }
}
