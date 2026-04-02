<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;

/**
 * Tool to update the lead score of a negotiation.
 *
 * Security: Validates tenant_id to prevent cross-tenant access.
 */
class UpdateLeadScoreTool implements AiToolInterface
{
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = $input->parameters['negotiation_id'] ?? null;
        $score = $input->parameters['score'] ?? null;
        $reason = $input->parameters['reason'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Validate score range
        if ($score === null || $score < 0 || $score > 100) {
            return ToolResultDTO::failure('Score must be between 0 and 100');
        }

        // Tenant isolation: only access negotiations from the same tenant
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        $previousScore = $negotiation->lead_score ?? 0;

        // Update the score
        $negotiation->update([
            'lead_score' => (int) $score,
        ]);

        return ToolResultDTO::success(
            message: "Lead score updated from {$previousScore} to {$score}",
            data: [
                'negotiation_id' => $negotiation->id,
                'previous_score' => $previousScore,
                'new_score' => (int) $score,
                'reason' => $reason,
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE;
    }

    public function getDescription(): string
    {
        return 'Updates the lead score of a negotiation based on engagement and qualification criteria. Score ranges from 0 (cold) to 100 (hot).';
    }

    /**
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'negotiation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the negotiation to update',
            ],
            'score' => [
                'type' => 'integer',
                'required' => true,
                'description' => 'New lead score (0-100)',
            ],
            'reason' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Reason for the score change',
            ],
        ];
    }
}
