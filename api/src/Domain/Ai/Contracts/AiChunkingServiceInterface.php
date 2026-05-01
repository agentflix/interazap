<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\Enums\AiDocumentType;

/**
 * Interface for chunking service.
 */
interface AiChunkingServiceInterface
{
    /**
     * Chunk text into smaller pieces with overlap.
     *
     * @param  string  $text  The text to chunk
     * @param  AiDocumentType|null  $type  Document type for type-aware chunking
     * @return list<\Domain\Ai\DTOs\ChunkDTO>
     */
    public function chunk(string $text, ?AiDocumentType $type = null): array;

    /**
     * Estimate token count for a text.
     */
    public function estimateTokens(string $text): int;
}
