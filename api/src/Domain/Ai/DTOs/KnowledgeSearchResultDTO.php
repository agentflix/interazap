<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO representando um resultado de busca do sistema RAG.
 *
 * @readonly
 */
final readonly class KnowledgeSearchResultDTO
{
    /**
     * @param  string  $chunkId  UUID do chunk retornado.
     * @param  string  $documentId  UUID do documento pai.
     * @param  string  $documentName  Nome do documento para exibição.
     * @param  string  $content  Conteúdo textual do chunk.
     * @param  int  $chunkIndex  Índice do chunk dentro do documento.
     * @param  float|null  $score  Score de similaridade (0-1, maior = mais similar).
     * @param  bool  $isNeighbor  Indica se o chunk é expansão de vizinhança.
     * @param  string|null  $neighborOfChunkId  UUID do chunk original que gerou esta expansão.
     */
    public function __construct(
        public string $chunkId,
        public string $documentId,
        public string $documentName,
        public string $content,
        public int $chunkIndex,
        public ?float $score = null,
        public bool $isNeighbor = false,
        public ?string $neighborOfChunkId = null,
    ) {}

    /**
     * Converte para array para serialização na resposta HTTP.
     *
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
            'score' => $this->score !== null ? round($this->score, 4) : null,
            'is_neighbor' => $this->isNeighbor,
            'neighbor_of_chunk_id' => $this->neighborOfChunkId,
        ];
    }
}
