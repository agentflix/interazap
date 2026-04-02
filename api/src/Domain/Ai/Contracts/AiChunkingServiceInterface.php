<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

/**
 * Interface for chunking service.
 */
interface AiChunkingServiceInterface
{
    /**
     * Chunk text into smaller pieces with overlap.
     *
     * @param  string  $text  The text to chunk
     * @return list<\Domain\Ai\DTOs\ChunkDTO>
     */
    public function chunk(string $text): array;

    /**
     * Estimate token count for a text.
     */
    public function estimateTokens(string $text): int;
}
