<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\DTOs\KnowledgeSearchFiltersDTO;
use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiRagSearchModeEnum;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for RAG search functionality.
 *
 * Uses pgvector cosine similarity for semantic search.
 */
final class AiRagService implements AiRagServiceInterface
{
    private readonly float $vectorWeight;

    private readonly float $keywordWeight;

    public function __construct(
        private readonly AiEmbeddingServiceInterface $embeddingService,
    ) {
        $this->vectorWeight = (float) config('ai.rag.vector_weight', 0.6);
        $this->keywordWeight = (float) config('ai.rag.keyword_weight', 0.4);

        if (abs($this->vectorWeight + $this->keywordWeight - 1.0) > 0.001) {
            throw new InvalidArgumentException(
                sprintf(
                    'RAG vector_weight + keyword_weight must equal 1.0 (tolerance 0.001), got %.3f + %.3f = %.3f',
                    $this->vectorWeight,
                    $this->keywordWeight,
                    $this->vectorWeight + $this->keywordWeight,
                )
            );
        }
    }

    /**
     * Search for relevant chunks by query.
     *
     * @return list<KnowledgeSearchResultDTO>
     */
    public function search(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
        ?KnowledgeSearchFiltersDTO $filters = null,
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $start = hrtime(true);
        $rawResults = [];

        try {
            $queryEmbedding = $this->embeddingService->embed($query);
            $embeddingString = '['.implode(',', $queryEmbedding).']';
            $efSearch = (int) config('ai.rag.ef_search', 100);
            $filterSql = $this->buildFilterSql($filters);

            $rawResults = DB::transaction(function () use (
                $mode,
                $embeddingString,
                $tenantId,
                $query,
                $minScore,
                $limit,
                $efSearch,
                $filterSql,
            ): array {
                DB::statement("SET LOCAL hnsw.ef_search = {$efSearch}");

                if ($mode === AiRagSearchModeEnum::HYBRID) {
                    $bindings = [
                        $embeddingString,
                        $tenantId,
                        $query,
                        $tenantId,
                        $this->vectorWeight,
                        $this->keywordWeight,
                        $this->vectorWeight,
                        $this->keywordWeight,
                        $minScore,
                        $limit,
                    ];

                    // Insert filter bindings before weights/score/limit
                    // The SQL structure is: embedding, tenant, query, tenant, [filters for vector], [filters for keyword], weights..., minScore, limit
                    // Actually we need to build the SQL with placeholders for filters
                    return $this->executeHybridSearch(
                        $embeddingString,
                        $tenantId,
                        $query,
                        $minScore,
                        $limit,
                        $filterSql,
                    );
                }

                return $this->executeVectorSearch(
                    $embeddingString,
                    $tenantId,
                    $minScore,
                    $limit,
                    $filterSql,
                );
            });

            $results = array_map(
                fn (object $row) => new KnowledgeSearchResultDTO(
                    chunkId: $row->chunk_id,
                    documentId: $row->document_id,
                    documentName: $row->document_name,
                    content: $row->content,
                    chunkIndex: (int) $row->chunk_index,
                    score: (float) $row->score,
                ),
                $rawResults
            );

            if (config('ai.rag.expand_neighbors', true)) {
                $results = $this->expandNeighbors($results, $tenantId);
            }

            return $results;
        } catch (\Throwable $e) {
            throw $e;
        } finally {
            $latencyMs = (int) ((hrtime(true) - $start) / 1e6);
            $resultsCount = count($rawResults);
            $scores = array_map(fn (object $r) => (float) $r->score, $rawResults);
            $topScore = $scores[0] ?? null;
            $avgScore = $resultsCount > 0 ? array_sum($scores) / $resultsCount : null;

            try {
                \Domain\Ai\Services\AiRagQueryLogger::log(
                    query: $query,
                    tenantId: $tenantId,
                    mode: $mode->value,
                    resultsCount: $resultsCount,
                    topScore: $topScore,
                    avgScore: $avgScore,
                    latencyMs: $latencyMs,
                );
            } catch (\Throwable $e) {
                // Logging must never break search.
            }
        }
    }

    /**
     * Build filter SQL fragments and bindings.
     *
     * @return array{sql: string, bindings: list<mixed>}
     */
    private function buildFilterSql(?KnowledgeSearchFiltersDTO $filters): array
    {
        if ($filters === null || ! $filters->hasFilters()) {
            return ['sql' => '', 'bindings' => []];
        }

        $clauses = [];
        $bindings = [];

        if ($filters->documentIds !== null && $filters->documentIds !== []) {
            $placeholders = implode(',', array_fill(0, count($filters->documentIds), '?'));
            $clauses[] = "AND c.document_id IN ({$placeholders})";
            $bindings = array_merge($bindings, $filters->documentIds);
        }

        if ($filters->fileTypes !== null && $filters->fileTypes !== []) {
            $placeholders = implode(',', array_fill(0, count($filters->fileTypes), '?'));
            $clauses[] = "AND d.file_type IN ({$placeholders})";
            $bindings = array_merge($bindings, array_map(
                fn (AiDocumentType $type): string => $type->value,
                $filters->fileTypes
            ));
        }

        if ($filters->createdAfter !== null) {
            $clauses[] = 'AND d.created_at >= ?';
            $bindings[] = $filters->createdAfter;
        }

        if ($filters->createdBefore !== null) {
            $clauses[] = 'AND d.created_at <= ?';
            $bindings[] = $filters->createdBefore;
        }

        return [
            'sql' => ' '.implode(' ', $clauses),
            'bindings' => $bindings,
        ];
    }

    /**
     * Execute vector search with optional filters.
     *
     * @param  array{sql: string, bindings: list<mixed>}  $filterSql
     * @return list<object>
     */
    private function executeVectorSearch(
        string $embeddingString,
        string $tenantId,
        float $minScore,
        int $limit,
        array $filterSql,
    ): array {
        $sql = "
            WITH query_vec AS (SELECT ?::vector AS vec)
            SELECT
                ranked.chunk_id,
                ranked.document_id,
                ranked.document_name,
                ranked.content,
                ranked.chunk_index,
                ranked.score
            FROM (
                SELECT
                    c.id as chunk_id,
                    c.document_id,
                    d.name as document_name,
                    c.content,
                    c.chunk_index,
                    1 - (c.embedding <=> q.vec) as score
                FROM ai_knowledge_chunks c
                INNER JOIN ai_knowledge_documents d ON d.id = c.document_id
                CROSS JOIN query_vec q
                WHERE c.tenant_id = ?
                  AND d.is_active = true
                  AND d.embedding_status = 'ready'
                  AND c.embedding IS NOT NULL
                  {$filterSql['sql']}
            ) AS ranked
            WHERE ranked.score >= ?
            ORDER BY ranked.score DESC
            LIMIT ?
        ";

        $bindings = array_merge(
            [$embeddingString, $tenantId],
            $filterSql['bindings'],
            [$minScore, $limit]
        );

        return DB::select($sql, $bindings);
    }

    /**
     * Execute hybrid search with optional filters.
     *
     * @param  array{sql: string, bindings: list<mixed>}  $filterSql
     * @return list<object>
     */
    private function executeHybridSearch(
        string $embeddingString,
        string $tenantId,
        string $query,
        float $minScore,
        int $limit,
        array $filterSql,
    ): array {
        $sql = "
            WITH query_vec AS (SELECT ?::vector AS vec),
            vector_ranked AS (
                SELECT
                    c.id as chunk_id,
                    c.document_id,
                    d.name as document_name,
                    c.content,
                    c.chunk_index,
                    1 - (c.embedding <=> q.vec) as vector_score
                FROM ai_knowledge_chunks c
                INNER JOIN ai_knowledge_documents d ON d.id = c.document_id
                CROSS JOIN query_vec q
                WHERE c.tenant_id = ?
                  AND d.is_active = true
                  AND d.embedding_status = 'ready'
                  AND c.embedding IS NOT NULL
                  {$filterSql['sql']}
            ),
            keyword_ranked AS (
                SELECT
                    c.id as chunk_id,
                    ts_rank(c.content_tsv, plainto_tsquery('portuguese', ?)) as keyword_score
                FROM ai_knowledge_chunks c
                INNER JOIN ai_knowledge_documents d ON d.id = c.document_id
                WHERE c.tenant_id = ?
                  AND d.is_active = true
                  AND d.embedding_status = 'ready'
                  {$filterSql['sql']}
            )
            SELECT
                v.chunk_id,
                v.document_id,
                v.document_name,
                v.content,
                v.chunk_index,
                ((? * v.vector_score) + (? * COALESCE(k.keyword_score, 0))) as score
            FROM vector_ranked v
            LEFT JOIN keyword_ranked k ON k.chunk_id = v.chunk_id
            WHERE ((? * v.vector_score) + (? * COALESCE(k.keyword_score, 0))) >= ?
            ORDER BY score DESC
            LIMIT ?
        ";

        $bindings = array_merge(
            [$embeddingString, $tenantId],
            $filterSql['bindings'],
            [$query, $tenantId],
            $filterSql['bindings'],
            [$this->vectorWeight, $this->keywordWeight, $this->vectorWeight, $this->keywordWeight, $minScore, $limit]
        );

        return DB::select($sql, $bindings);
    }

    /**
     * Expand search results with neighboring chunks.
     *
     * @param  list<KnowledgeSearchResultDTO>  $results
     * @return list<KnowledgeSearchResultDTO>
     */
    private function expandNeighbors(array $results, string $tenantId): array
    {
        if ($results === []) {
            return $results;
        }

        $window = (int) config('ai.rag.neighbor_window', 1);
        if ($window < 1) {
            return $results;
        }

        $existingIds = [];
        foreach ($results as $dto) {
            $existingIds[$dto->chunkId] = true;
        }

        $neighbors = [];

        foreach ($results as $dto) {
            $minIndex = max(0, $dto->chunkIndex - $window);
            $maxIndex = $dto->chunkIndex + $window;

            $rows = DB::select('
                SELECT
                    c.id as chunk_id,
                    c.document_id,
                    d.name as document_name,
                    c.content,
                    c.chunk_index
                FROM ai_knowledge_chunks c
                INNER JOIN ai_knowledge_documents d ON d.id = c.document_id
                WHERE c.document_id = ?
                  AND c.tenant_id = ?
                  AND c.chunk_index BETWEEN ? AND ?
            ', [$dto->documentId, $tenantId, $minIndex, $maxIndex]);

            foreach ($rows as $row) {
                if (isset($existingIds[$row->chunk_id])) {
                    continue;
                }

                $existingIds[$row->chunk_id] = true;

                $neighbors[] = new KnowledgeSearchResultDTO(
                    chunkId: $row->chunk_id,
                    documentId: $row->document_id,
                    documentName: $row->document_name,
                    content: $row->content,
                    chunkIndex: (int) $row->chunk_index,
                    score: null,
                    isNeighbor: true,
                    neighborOfChunkId: $dto->chunkId,
                );
            }
        }

        $merged = array_merge($results, $neighbors);

        usort($merged, function (KnowledgeSearchResultDTO $a, KnowledgeSearchResultDTO $b): int {
            return [$a->documentId, $a->chunkIndex] <=> [$b->documentId, $b->chunkIndex];
        });

        return $merged;
    }

    /**
     * Search and format results as context for LLM.
     */
    public function getContextForLLM(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
    ): string {
        $results = $this->search($query, $tenantId, $limit, $minScore, $mode);

        if ($results === []) {
            return '';
        }

        $context = "Relevant knowledge base context:\n\n";

        foreach ($results as $result) {
            if ($result->isNeighbor) {
                $context .= sprintf(
                    "[Source: %s | [continuação] | Chunk %d]\n%s\n\n",
                    $result->documentName,
                    $result->chunkIndex + 1,
                    $result->content
                );
            } else {
                $context .= sprintf(
                    "[Source: %s | Relevância: %d%% | Chunk %d]\n%s\n\n",
                    $result->documentName,
                    (int) round($result->score * 100),
                    $result->chunkIndex + 1,
                    $result->content
                );
            }
        }

        return $context;
    }
}
