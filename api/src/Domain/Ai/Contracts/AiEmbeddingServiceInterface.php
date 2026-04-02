<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

/**
 * Interface for embedding service.
 */
interface AiEmbeddingServiceInterface
{
    /**
     * Generate embedding for a single text.
     *
     * @return list<float> Vector with 1536 dimensions
     */
    public function embed(string $text): array;

    /**
     * Generate embeddings for multiple texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}
