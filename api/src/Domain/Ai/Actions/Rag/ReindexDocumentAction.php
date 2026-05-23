<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;

/**
 * Action for reindexing a knowledge document.
 *
 * Deletes existing chunks and reprocesses the document.
 */
final class ReindexDocumentAction
{
    /**
     * Reindex a document by reprocessing it.
     */
    public function execute(AiKnowledgeDocument $document): AiKnowledgeDocument
    {
        // Only reindex active documents
        if (! $document->is_active) {
            throw new \InvalidArgumentException('Cannot reindex inactive document');
        }

        // Only reindex documents that are ready or failed
        if (! $document->canReprocess()) {
            throw new \InvalidArgumentException('Document is currently being processed');
        }

        // Delete existing chunks
        AiKnowledgeChunk::where('document_id', $document->id)->delete();

        // Reset status to pending
        $document->update([
            'embedding_status' => AiEmbeddingStatus::PENDING,
            'chunk_count' => 0,
            'error_message' => null,
        ]);

        // Dispatch processing job
        AiKnowledgeProcessJob::dispatch($document->id, (string) $document->tenant_id);

        return $document->fresh() ?? $document;
    }
}
