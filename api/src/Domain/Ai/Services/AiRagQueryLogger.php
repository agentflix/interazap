<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiRagQueryLog;
use Illuminate\Support\Facades\Log;

/**
 * Logger for RAG search queries.
 *
 * Stores anonymised query hashes and quality metrics. Never persists the raw query text.
 * All exceptions are swallowed to ensure logging never breaks search functionality.
 */
final class AiRagQueryLogger
{
    public static function log(
        string $query,
        string $tenantId,
        string $mode,
        int $resultsCount,
        ?float $topScore,
        ?float $avgScore,
        int $latencyMs,
    ): void {
        try {
            $normalized = self::normalize($query);
            $hash = hash('sha256', $normalized);

            AiRagQueryLog::create([
                'tenant_id' => $tenantId,
                'query_hash' => $hash,
                'query_length' => mb_strlen($normalized),
                'mode' => $mode,
                'results_count' => $resultsCount,
                'top_score' => $topScore,
                'avg_score' => $avgScore,
                'latency_ms' => $latencyMs,
                'has_results' => $resultsCount > 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('AiRagQueryLogger failed to persist log entry', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private static function normalize(string $query): string
    {
        $query = mb_strtolower($query);
        $query = trim($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        return $query;
    }
}
