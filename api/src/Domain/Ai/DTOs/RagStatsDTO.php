<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO for RAG query statistics.
 *
 * @readonly
 */
final readonly class RagStatsDTO
{
    /**
     * @param  array<string, int>  $modeDistribution
     * @param  list<array{query_hash: string, count: int}>  $topQueryHashes
     */
    public function __construct(
        public int $totalQueries,
        public float $zeroResultsRate,
        public ?float $p50LatencyMs,
        public ?float $p95LatencyMs,
        public ?float $p99LatencyMs,
        public ?float $avgTopScore,
        public ?float $avgResultsCount,
        public array $modeDistribution,
        public array $topQueryHashes,
    ) {}
}
