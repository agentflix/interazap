<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Domain\Ai\Enums\AiEmbeddingStatus;

/**
 * Evento disparado quando um documento da Knowledge Base atinge estado terminal de processamento.
 *
 * O estado pode ser READY (sucesso) ou FAILED (falha permanente).
 * Consumido pelo gateway para notificar o frontend via WebSocket.
 */
final readonly class AiKnowledgeDocumentProcessed
{
    public function __construct(
        public string $documentId,
        public string $tenantId,
        public AiEmbeddingStatus $status,
        public int $chunkCount,
        public ?string $errorMessage = null,
    ) {}
}
