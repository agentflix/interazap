<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiKnowledgeChunk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de chunk da base de conhecimento.
 *
 * @mixin AiKnowledgeChunk
 */
final class KnowledgeChunkResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'chunk_index' => $this->chunk_index,
            'content' => $this->content,
            'content_preview' => $this->getContentPreview(),
            'token_count' => $this->token_count,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
