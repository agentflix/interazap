<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Ai\DTOs\KnowledgeSearchFiltersDTO;
use Domain\Ai\Enums\AiRagSearchModeEnum;

/**
 * Contrato para o serviço de busca RAG (Retrieval-Augmented Generation).
 *
 * Define a interface para busca semântica na Knowledge Base e
 * formatação dos resultados como contexto para o LLM.
 */
interface AiRagServiceInterface
{
    /**
     * Busca chunks relevantes para a query.
     *
     * @return list<\Domain\Ai\DTOs\KnowledgeSearchResultDTO>
     */
    public function search(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
        ?KnowledgeSearchFiltersDTO $filters = null,
    ): array;

    /**
     * Busca e formata os resultados como contexto para o LLM.
     */
    public function getContextForLLM(
        string $query,
        string $tenantId,
        int $limit = 5,
        float $minScore = 0.30,
        AiRagSearchModeEnum $mode = AiRagSearchModeEnum::VECTOR,
    ): string;
}
