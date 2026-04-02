<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Enums\AiRagSearchModeEnum;

/**
 * Ferramenta para pesquisar na base de conhecimento.
 */
class SearchKnowledgeTool implements AiToolInterface
{
    public function __construct(private readonly AiRagServiceInterface $ragService) {}

    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $query = $input->parameters['query'] ?? '';
        $limit = (int) ($input->parameters['limit'] ?? 5);
        $minScore = (float) ($input->parameters['min_score'] ?? 0.30);
        $modeValue = (string) ($input->parameters['mode'] ?? AiRagSearchModeEnum::VECTOR->value);
        $mode = AiRagSearchModeEnum::tryFrom($modeValue) ?? AiRagSearchModeEnum::VECTOR;
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if (trim((string) $query) === '') {
            return ToolResultDTO::failure('Query cannot be empty');
        }

        if ($tenantId === '') {
            return ToolResultDTO::failure('Tenant ID is required');
        }

        $results = $this->ragService->search((string) $query, $tenantId, $limit, $minScore, $mode);

        $normalized = array_map(
            fn ($item): array => [
                'id' => $item->chunkId,
                'content' => $item->content,
                'source' => $item->documentName,
                'relevance' => $item->score,
            ],
            $results,
        );

        return ToolResultDTO::success(
            message: count($normalized) > 0
                ? 'Found '.count($normalized).' relevant documents'
                : 'No matching documents found',
            data: [
                'query' => $query,
                'results' => $normalized,
                'count' => count($normalized),
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE;
    }

    public function getDescription(): string
    {
        return 'Searches the knowledge base for relevant information. Use to find answers about products, policies, procedures, or FAQs.';
    }

    /**
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Search query to find relevant knowledge',
            ],
            'limit' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of results (default: 5)',
            ],
            'min_score' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Minimum relevance score between 0 and 1 (default: 0.30)',
            ],
            'mode' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Search mode: vector or hybrid',
            ],
        ];
    }
}
