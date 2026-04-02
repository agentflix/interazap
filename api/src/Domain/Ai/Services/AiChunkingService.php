<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\DTOs\ChunkDTO;

/**
 * Service for chunking text into smaller pieces for RAG.
 *
 * Implements the chunking algorithm from PRD:
 * 1. Split by paragraphs (\n\n)
 * 2. If paragraph > 500 tokens, split by sentences
 * 3. Group sentences until ~500 tokens
 * 4. Maintain 50 token overlap between chunks
 */
final class AiChunkingService implements AiChunkingServiceInterface
{
    private const int CHUNK_SIZE_TOKENS = 500;

    private const int OVERLAP_TOKENS = 50;

    private const float DEFAULT_CHARS_PER_TOKEN = 3.5;

    /**
     * Chunk text into smaller pieces with overlap.
     *
     * @return list<ChunkDTO>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        // Step 1: Split by paragraphs
        $paragraphs = $this->splitByParagraphs($text);

        // Step 2: Process each paragraph
        $allSentences = [];
        foreach ($paragraphs as $paragraph) {
            $paragraphTokens = $this->estimateTokens($paragraph);

            if ($paragraphTokens <= self::CHUNK_SIZE_TOKENS) {
                // Paragraph fits in one chunk
                $allSentences[] = $paragraph;
            } else {
                // Split by sentences
                $sentences = $this->splitBySentences($paragraph);
                foreach ($sentences as $sentence) {
                    $allSentences[] = $sentence;
                }
            }
        }

        // Step 3: Group into chunks with overlap
        return $this->groupIntoChunks($allSentences);
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
     * Split text by paragraphs.
     *
     * @return list<string>
     */
    private function splitByParagraphs(string $text): array
    {
        $paragraphs = preg_split('/\n\n+/', $text);
        if ($paragraphs === false) {
            return [$text];
        }

        return array_values(array_filter(
            array_map('trim', $paragraphs),
            fn (string $p): bool => $p !== ''
        ));
    }

    /**
     * Split text by sentences.
     *
     * @return list<string>
     */
    private function splitBySentences(string $text): array
    {
        // Split on sentence-ending punctuation followed by whitespace
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        if ($sentences === false) {
            return [$text];
        }

        return array_values(array_filter(
            array_map('trim', $sentences),
            fn (string $s): bool => $s !== ''
        ));
    }

    /**
     * Group sentences into chunks with overlap.
     *
     * @param  list<string>  $sentences
     * @return list<ChunkDTO>
     */
    private function groupIntoChunks(array $sentences): array
    {
        if (empty($sentences)) {
            return [];
        }

        $chunks = [];
        $currentChunk = [];
        $currentTokens = 0;
        $overlapBuffer = [];
        $overlapTokens = 0;
        $chunkIndex = 0;

        foreach ($sentences as $sentence) {
            $sentenceTokens = $this->estimateTokens($sentence);

            // Check if adding this sentence exceeds the limit
            if ($currentTokens + $sentenceTokens > self::CHUNK_SIZE_TOKENS && ! empty($currentChunk)) {
                // Create chunk from current content
                $chunkContent = implode(' ', $currentChunk);
                $chunks[] = new ChunkDTO(
                    index: $chunkIndex++,
                    content: $chunkContent,
                    tokenCount: $this->estimateTokens($chunkContent),
                );

                // Prepare overlap for next chunk
                $overlapBuffer = $this->getOverlapSentences($currentChunk);
                $overlapTokens = array_sum(array_map(
                    fn (string $s): int => $this->estimateTokens($s),
                    $overlapBuffer
                ));

                // Start new chunk with overlap
                $currentChunk = $overlapBuffer;
                $currentTokens = $overlapTokens;
            }

            // Add sentence to current chunk
            $currentChunk[] = $sentence;
            $currentTokens += $sentenceTokens;
        }

        // Add the last chunk
        $chunkContent = implode(' ', $currentChunk);
        $chunks[] = new ChunkDTO(
            index: $chunkIndex,
            content: $chunkContent,
            tokenCount: $this->estimateTokens($chunkContent),
        );

        return $chunks;
    }

    /**
     * Get sentences for overlap from the end of a chunk.
     *
     * @param  list<string>  $sentences
     * @return list<string>
     */
    private function getOverlapSentences(array $sentences): array
    {
        $overlap = [];
        $overlapTokens = 0;

        // Work backwards through sentences
        for ($i = count($sentences) - 1; $i >= 0; $i--) {
            $sentenceTokens = $this->estimateTokens($sentences[$i]);

            if ($overlapTokens + $sentenceTokens > self::OVERLAP_TOKENS) {
                break;
            }

            array_unshift($overlap, $sentences[$i]);
            $overlapTokens += $sentenceTokens;
        }

        return $overlap;
    }
}
