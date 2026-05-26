<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiRagQueryLog;
use Illuminate\Support\Facades\Log;

/**
 * Logger de consultas RAG para rastreamento de qualidade e latência.
 *
 * Contexto: armazena hashes anonimizados de queries e métricas de qualidade (score, latência).
 * Nunca persiste o texto bruto da query. Todas as exceções são silenciadas para garantir
 * que o logging nunca interrompa a funcionalidade de busca.
 */
final class AiRagQueryLogger
{
    /**
     * Registra uma consulta RAG com métricas de qualidade e latência.
     *
     * A query é normalizada e convertida em hash SHA-256 antes de ser persistida.
     *
     * @param  string  $query  Texto original da consulta (não persiste em banco).
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $mode  Modo de busca utilizado (vector, hybrid, etc.).
     * @param  int  $resultsCount  Número de resultados retornados.
     * @param  float|null  $topScore  Score do resultado mais relevante ou null.
     * @param  float|null  $avgScore  Score médio dos resultados ou null.
     * @param  int  $latencyMs  Latência total em milissegundos.
     */
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

    /**
     * Normaliza a query para geração do hash (lowercase, trim, espaços únicos).
     *
     * @param  string  $query  Texto bruto da consulta.
     * @return string Texto normalizado.
     */
    private static function normalize(string $query): string
    {
        $query = mb_strtolower($query);
        $query = trim($query);
        $query = preg_replace('/\s+/', ' ', $query) ?? $query;

        return $query;
    }
}
