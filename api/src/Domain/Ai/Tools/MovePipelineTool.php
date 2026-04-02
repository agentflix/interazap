<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;

/**
 * Tool to move a negotiation to a different pipeline step.
 *
 * Security: Validates tenant_id for both negotiation and step to prevent cross-tenant access.
 */
class MovePipelineTool implements AiToolInterface
{
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = $input->parameters['negotiation_id'] ?? null;
        $stepId = $input->parameters['step_id'] ?? null;
        $reason = $input->parameters['reason'] ?? null;
        $tenantId = $input->context['tenant_id'] ?? null;

        // Tenant isolation: only access negotiations from the same tenant
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        // Tenant isolation: only access steps from the same tenant
        $step = CRMNegotiationFunnelStep::query()
            ->whereHas('funnel', fn ($q) => $q->where('tenant_id', $tenantId))
            ->find($stepId);

        if (! $step) {
            return ToolResultDTO::failure('Step not found');
        }

        $previousStepId = $negotiation->crm_negotiation_funnel_step_id;

        $negotiation->update([
            'crm_negotiation_funnel_id' => $step->crm_negotiation_funnel_id,
            'crm_negotiation_funnel_step_id' => $step->id,
        ]);

        return ToolResultDTO::success(
            message: 'Negotiation moved to new pipeline step',
            data: [
                'negotiation_id' => $negotiation->id,
                'previous_step_id' => $previousStepId,
                'new_step_id' => $step->id,
                'new_step_name' => $step->name,
                'reason' => $reason,
            ]
        );
    }

    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE;
    }

    public function getDescription(): string
    {
        return 'Moves a negotiation to a different step in the sales pipeline/funnel.';
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
                'description' => 'UUID of the negotiation',
            ],
            'step_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the target funnel step',
            ],
            'reason' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Reason for moving the negotiation',
            ],
        ];
    }
}
