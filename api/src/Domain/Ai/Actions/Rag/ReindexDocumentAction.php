<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Rag;

use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;

/**
 * Action para reindexação de um documento de conhecimento.
 *
 * Remove todos os chunks existentes e reenfileira o processamento,
 * resetando o status para PENDING. Só aceita documentos ativos e
 * que não estejam em processamento.
 */
final class ReindexDocumentAction
{
    /**
     * Reindexar um documento excluindo chunks existentes e reenfileirando o processamento.
     *
     * @param  AiKnowledgeDocument  $document  Documento a reindexar.
     * @return AiKnowledgeDocument Documento com status resetado para PENDING.
     *
     * @throws \InvalidArgumentException Se o documento estiver inativo ou em processamento.
     */
    public function execute(AiKnowledgeDocument $document): AiKnowledgeDocument
    {
        // Apenas documentos ativos podem ser reindexados
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
