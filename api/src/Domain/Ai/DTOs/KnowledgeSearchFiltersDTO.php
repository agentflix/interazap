<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Domain\Ai\Enums\AiDocumentType;
use Illuminate\Support\Carbon;

/**
 * DTO for faceted search filters in RAG queries.
 *
 * @readonly
 */
final readonly class KnowledgeSearchFiltersDTO
{
    /**
     * @param  list<string>|null  $documentIds
     * @param  list<AiDocumentType>|null  $fileTypes
     */
    public function __construct(
        public ?array $documentIds = null,
        public ?array $fileTypes = null,
        public ?Carbon $createdAfter = null,
        public ?Carbon $createdBefore = null,
    ) {}

    /**
     * Check if any filters are applied.
     */
    public function hasFilters(): bool
    {
        return $this->documentIds !== null
            || $this->fileTypes !== null
            || $this->createdAfter !== null
            || $this->createdBefore !== null;
    }
}
