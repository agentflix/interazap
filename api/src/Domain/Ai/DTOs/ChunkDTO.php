<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO representando um trecho de texto (chunk) com seus metadados.
 *
 * @readonly
 */
final readonly class ChunkDTO
{
    /**
     * @param  int  $index  Índice do chunk dentro do documento.
     * @param  string  $content  Conteúdo textual do chunk.
     * @param  int  $tokenCount  Estimativa de tokens do chunk.
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
