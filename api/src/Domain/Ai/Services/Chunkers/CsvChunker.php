<?php

declare(strict_types=1);

namespace Domain\Ai\Services\Chunkers;

use Domain\Ai\Contracts\Chunkers\ChunkerStrategyInterface;
use Domain\Ai\DTOs\ChunkDTO;

/**
 * CSV-aware chunking strategy.
 *
 * - Detects header (first line)
 * - Each chunk contains header + N rows until ~500 tokens
 * - Each chunk is self-sufficient (LLM doesn't need previous chunk)
 */
final class CsvChunker implements ChunkerStrategyInterface
{
    private const int CHUNK_SIZE_TOKENS = 500;

    private const float DEFAULT_CHARS_PER_TOKEN = 3.5;

    /**
     * @return list<ChunkDTO>
     */
    public function chunk(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if ($lines === false) {
            $lines = [$text];
        }

        $lines = array_values(array_filter($lines, fn (string $line): bool => trim($line) !== ''));

        if ($lines === []) {
            return [];
        }

        $header = $lines[0];
        $dataRows = array_slice($lines, 1);

        // If no data rows, return header as single chunk
        if ($dataRows === []) {
            return [
                new ChunkDTO(
                    index: 0,
                    content: $header,
                    tokenCount: $this->estimateTokens($header),
                ),
            ];
        }

        $headerTokens = $this->estimateTokens($header);
        $chunks = [];
        $currentRows = [];
        $currentTokens = $headerTokens;
        $chunkIndex = 0;

        foreach ($dataRows as $row) {
            $rowTokens = $this->estimateTokens($row);

            // If adding this row exceeds limit, flush current chunk
            if ($currentTokens + $rowTokens > self::CHUNK_SIZE_TOKENS && $currentRows !== []) {
                $content = $header."\n".implode("\n", $currentRows);
                $chunks[] = new ChunkDTO(
                    index: $chunkIndex++,
                    content: $content,
                    tokenCount: $this->estimateTokens($content),
                );

                $currentRows = [];
                $currentTokens = $headerTokens;
            }

            $currentRows[] = $row;
            $currentTokens += $rowTokens;
        }

        // Flush remaining rows (guaranteed non-empty because $dataRows was not empty)
        $content = $header."\n".implode("\n", $currentRows);
        $chunks[] = new ChunkDTO(
            index: $chunkIndex,
            content: $content,
            tokenCount: $this->estimateTokens($content),
        );

        return $chunks;
    }

    /**
     * Estimate token count for a text.
     */
    private function estimateTokens(string $text): int
    {
        $charsPerToken = (float) config('ai.chunking.chars_per_token', self::DEFAULT_CHARS_PER_TOKEN);

        return (int) ceil(mb_strlen($text, 'UTF-8') / $charsPerToken);
    }
}
