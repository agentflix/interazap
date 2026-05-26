<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização de documento da base de conhecimento.
 *
 * @mixin AiKnowledgeDocument
 */
final class KnowledgeDocumentResource extends JsonResource
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
            'name' => $this->name,
            'original_filename' => $this->original_filename,
            'file_type' => $this->file_type->value,
            'file_type_label' => $this->file_type->label(),
            'file_size_bytes' => $this->file_size_bytes,
            'file_size_formatted' => $this->getFormattedFileSize(),
            'version' => $this->version,
            'chunk_count' => $this->chunk_count,
            'embedding_status' => $this->embedding_status->value,
            'embedding_status_label' => $this->embedding_status->label(),
            'embedding_status_color' => $this->embedding_status->badgeColor(),
            'error_message' => $this->error_message,
            'is_ready' => $this->isReady(),
            'is_processing' => $this->isProcessing(),
            'is_failed' => $this->isFailed(),
            'can_reprocess' => $this->canReprocess(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
