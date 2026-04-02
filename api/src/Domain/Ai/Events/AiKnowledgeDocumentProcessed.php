<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Domain\Ai\Enums\AiEmbeddingStatus;

/**
 * Event fired when a knowledge document reaches terminal processing state.
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
