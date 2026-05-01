<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts\Chunkers;

use Domain\Ai\DTOs\ChunkDTO;

/**
 * Interface for chunking strategies.
 */
interface ChunkerStrategyInterface
{
    /**
     * Chunk text into smaller pieces.
     *
     * @return list<ChunkDTO>
     */
    public function chunk(string $text): array;
}
