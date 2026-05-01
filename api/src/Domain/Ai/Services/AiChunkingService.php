<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\Chunkers\ChunkerStrategyInterface;
use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Services\Chunkers\CsvChunker;
use Domain\Ai\Services\Chunkers\DefaultChunker;
use Domain\Ai\Services\Chunkers\MarkdownChunker;

/**
 * Service for chunking text into smaller pieces for RAG.
 *
 * Delegates to type-specific strategies:
 * - MarkdownChunker for markdown files
 * - CsvChunker for CSV files
 * - DefaultChunker for everything else
 */
final class AiChunkingService implements AiChunkingServiceInterface
{
    private const float DEFAULT_CHARS_PER_TOKEN = 3.5;

    /**
     * Chunk text into smaller pieces with overlap.
     *
     * @return list<ChunkDTO>
     */
    public function chunk(string $text, ?AiDocumentType $type = null): array
    {
        $strategy = $this->resolveStrategy($type);

        return $strategy->chunk($text);
    }

    /**
     * Estimate token count for a text.
     */
    public function estimateTokens(string $text): int
    {
        $charsPerToken = (float) config('ai.chunking.chars_per_token', self::DEFAULT_CHARS_PER_TOKEN);

        return (int) ceil(mb_strlen($text, 'UTF-8') / $charsPerToken);
    }

    /**
     * Resolve chunking strategy based on document type.
     */
    private function resolveStrategy(?AiDocumentType $type): ChunkerStrategyInterface
    {
        return match ($type) {
            AiDocumentType::MARKDOWN => new MarkdownChunker,
            AiDocumentType::CSV => new CsvChunker,
            default => new DefaultChunker,
        };
    }
}
