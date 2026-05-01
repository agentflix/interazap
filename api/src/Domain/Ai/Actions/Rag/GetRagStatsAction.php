<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\DTOs\RagStatsDTO;
use Illuminate\Support\Facades\DB;

/**
 * Action for getting RAG query statistics.
 */
final class GetRagStatsAction
{
    /**
     * Get RAG statistics for a tenant over the last N days.
     */
    public function execute(string $tenantId, int $days): RagStatsDTO
    {
        $since = now()->subDays($days)->startOfDay();

        // Basic aggregations
        $aggregates = DB::selectOne('
            SELECT
                COUNT(*) as total_queries,
                COUNT(*) FILTER (WHERE has_results = false) as zero_results_count,
                AVG(top_score) as avg_top_score,
                AVG(results_count) as avg_results_count
            FROM ai_rag_query_logs
            WHERE tenant_id = ?
              AND created_at >= ?
        ', [$tenantId, $since]);

        $totalQueries = (int) ($aggregates->total_queries ?? 0);
        $zeroResultsCount = (int) ($aggregates->zero_results_count ?? 0);
        $zeroResultsRate = $totalQueries > 0 ? $zeroResultsCount / $totalQueries : 0.0;
        $avgTopScore = $aggregates->avg_top_score !== null ? (float) $aggregates->avg_top_score : null;
        $avgResultsCount = $aggregates->avg_results_count !== null ? (float) $aggregates->avg_results_count : null;

        // Percentile latencies (PostgreSQL specific)
        $percentiles = DB::selectOne('
            SELECT
                percentile_cont(0.5) WITHIN GROUP (ORDER BY latency_ms) as p50,
                percentile_cont(0.95) WITHIN GROUP (ORDER BY latency_ms) as p95,
                percentile_cont(0.99) WITHIN GROUP (ORDER BY latency_ms) as p99
            FROM ai_rag_query_logs
            WHERE tenant_id = ?
              AND created_at >= ?
        ', [$tenantId, $since]);

        $p50LatencyMs = $percentiles->p50 !== null ? (float) $percentiles->p50 : null;
        $p95LatencyMs = $percentiles->p95 !== null ? (float) $percentiles->p95 : null;
        $p99LatencyMs = $percentiles->p99 !== null ? (float) $percentiles->p99 : null;

        // Mode distribution
        $modeRows = DB::select('
            SELECT mode, COUNT(*) as count
            FROM ai_rag_query_logs
            WHERE tenant_id = ?
              AND created_at >= ?
            GROUP BY mode
        ', [$tenantId, $since]);

        $modeDistribution = [];
        foreach ($modeRows as $row) {
            $modeDistribution[$row->mode] = (int) $row->count;
        }

        // Top 10 query hashes
        $topHashRows = DB::select('
            SELECT query_hash, COUNT(*) as count
            FROM ai_rag_query_logs
            WHERE tenant_id = ?
              AND created_at >= ?
            GROUP BY query_hash
            ORDER BY count DESC
            LIMIT 10
        ', [$tenantId, $since]);

        $topQueryHashes = [];
        foreach ($topHashRows as $row) {
            $topQueryHashes[] = [
                'query_hash' => $row->query_hash,
                'count' => (int) $row->count,
            ];
        }

        return new RagStatsDTO(
            totalQueries: $totalQueries,
            zeroResultsRate: $zeroResultsRate,
            p50LatencyMs: $p50LatencyMs,
            p95LatencyMs: $p95LatencyMs,
            p99LatencyMs: $p99LatencyMs,
            avgTopScore: $avgTopScore,
            avgResultsCount: $avgResultsCount,
            modeDistribution: $modeDistribution,
            topQueryHashes: $topQueryHashes,
        );
    }
}
