<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO representing a text chunk with its metadata.
 *
 * @readonly
 */
final readonly class ChunkDTO
{
    /**
     * @param  int  $index  Chunk index within the document
     * @param  string  $content  The text content of the chunk
     * @param  int  $tokenCount  Estimated token count for the chunk
     */
    public function __construct(
        public int $index,
        public string $content,
        public int $tokenCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'content' => $this->content,
            'token_count' => $this->tokenCount,
        ];
    }
}
