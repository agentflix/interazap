<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO representing a search result from the RAG system.
 *
 * @readonly
 */
final readonly class KnowledgeSearchResultDTO
{
    /**
     * @param  string  $chunkId  Chunk ID
     * @param  string  $documentId  Parent document ID
     * @param  string  $documentName  Document name for display
     * @param  string  $content  Chunk content
     * @param  int  $chunkIndex  Chunk index within document
     * @param  float  $score  Similarity score (0-1, higher is more similar)
     */
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $documentName,
        public string $content,
        public int $chunkIndex,
        public float $score,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'document_id' => $this->documentId,
            'document_name' => $this->documentName,
            'content' => $this->content,
            'chunk_index' => $this->chunkIndex,
            'score' => round($this->score, 4),
        ];
    }
}
