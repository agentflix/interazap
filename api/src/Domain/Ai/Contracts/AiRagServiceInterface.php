<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\Enums\AiRagSearchModeEnum;

/**
 * Interface for RAG service.
 */
interface AiRagServiceInterface
{
    /**
     * Search for relevant chunks by query.
     *
     * @return list<\Domain\Ai\DTOs\KnowledgeSearchResultDTO>
     */
    public function search(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
    ): array;

    /**
     * Search and format results as context for LLM.
     */
    public function getContextForLLM(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
    ): string;
}
