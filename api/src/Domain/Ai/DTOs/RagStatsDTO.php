<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO para estatísticas de consultas RAG do tenant.
 *
 * Agrega métricas de qualidade calculadas pela GetRagStatsAction:
 * taxa de zero resultados, latências percentis (p50/p95/p99),
 * score médio e distribuição por modo de busca.
 *
 * @readonly
 */
final readonly class RagStatsDTO
{
    /**
     * @param  int  $totalQueries  Total de consultas no período.
     * @param  float  $zeroResultsRate  Taxa de consultas sem resultado (0.0-1.0).
     * @param  float|null  $p50LatencyMs  Latência no percentil 50 em ms.
     * @param  float|null  $p95LatencyMs  Latência no percentil 95 em ms.
     * @param  float|null  $p99LatencyMs  Latência no percentil 99 em ms.
     * @param  float|null  $avgTopScore  Score médio do resultado mais relevante.
     * @param  float|null  $avgResultsCount  Média de resultados por consulta.
     * @param  array<string, int>  $modeDistribution  Contagem de consultas por modo (vector/hybrid).
     * @param  list<array{query_hash: string, count: int}>  $topQueryHashes  Top 10 hashes mais frequentes.
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
