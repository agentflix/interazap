<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\DTOs\KnowledgeSearchResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for Knowledge Search Result serialization.
 *
 * @mixin KnowledgeSearchResultDTO
 */
final class KnowledgeSearchResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KnowledgeSearchResultDTO $dto */
        $dto = $this->resource;

        return [
            'chunk_id' => $dto->chunkId,
            'document_id' => $dto->documentId,
            'document_name' => $dto->documentName,
            'content' => $dto->content,
            'chunk_index' => $dto->chunkIndex,
            'score' => $dto->score !== null ? round($dto->score, 4) : null,
            'relevance_percent' => $dto->score !== null ? round($dto->score * 100, 1) : null,
            'is_neighbor' => $dto->isNeighbor,
            'neighbor_of_chunk_id' => $dto->neighborOfChunkId,
        ];
    }
}
