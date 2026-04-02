<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Domain\Ai\Enums\AiRagSearchModeEnum;
use Illuminate\Support\Facades\DB;

/**
 * Service for RAG search functionality.
 *
 * Uses pgvector cosine similarity for semantic search.
 */
final class AiRagService implements AiRagServiceInterface
{
    public function __construct(
        private readonly AiEmbeddingServiceInterface $embeddingService,
    ) {}

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
    ): array {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        // Generate query embedding
        $queryEmbedding = $this->embeddingService->embed($query);

        // Convert embedding to pgvector format
        $embeddingString = '['.implode(',', $queryEmbedding).']';

        if ($mode === AiRagSearchModeEnum::HYBRID) {
            $results = DB::select("
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
                )
                SELECT
                    v.chunk_id,
                    v.document_id,
                    v.document_name,
                    v.content,
                    v.chunk_index,
                    ((0.6 * v.vector_score) + (0.4 * COALESCE(k.keyword_score, 0))) as score
                FROM vector_ranked v
                LEFT JOIN keyword_ranked k ON k.chunk_id = v.chunk_id
                WHERE ((0.6 * v.vector_score) + (0.4 * COALESCE(k.keyword_score, 0))) >= ?
                ORDER BY score DESC
                LIMIT ?
            ", [$embeddingString, $tenantId, $query, $tenantId, $minScore, $limit]);
        } else {
            $results = DB::select("
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
            ) AS ranked
            WHERE ranked.score >= ?
            ORDER BY ranked.score DESC
            LIMIT ?
        ", [$embeddingString, $tenantId, $minScore, $limit]);
        }

        return array_map(
            fn (object $row) => new KnowledgeSearchResultDTO(
                chunkId: $row->chunk_id,
                documentId: $row->document_id,
                documentName: $row->document_name,
                content: $row->content,
                chunkIndex: (int) $row->chunk_index,
                score: (float) $row->score,
            ),
            $results
        );
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

        if (empty($results)) {
            return '';
        }

        $context = "Relevant knowledge base context:\n\n";

        foreach ($results as $i => $result) {
            $context .= sprintf(
                "[Source: %s | Relevância: %d%% | Chunk %d]\n%s\n\n",
                $result->documentName,
                (int) round($result->score * 100),
                $result->chunkIndex + 1,
                $result->content
            );
        }

        return $context;
    }
}
