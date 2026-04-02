<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMReasonLoss;

/**
 * Tool to close an open CRM negotiation.
 */
class CloseNegotiationTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = (string) ($input->parameters['negotiation_id'] ?? '');
        $outcome = (string) ($input->parameters['outcome'] ?? '');
        $reasonLossId = (string) ($input->parameters['reason_loss_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($negotiationId === '') {
            return ToolResultDTO::failure('Negotiation ID is required');
        }

        if (! in_array($outcome, ['won', 'lost'], true)) {
            return ToolResultDTO::failure('Outcome must be "won" or "lost"');
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        if ((string) $negotiation->status->value !== 'open') {
            return ToolResultDTO::failure("Negotiation is already {$negotiation->status->value}");
        }

        $payload = [
            'status' => $outcome,
            'closed_at' => now(),
        ];

        if ($outcome === 'lost' && $reasonLossId !== '') {
            $reasonLoss = CRMReasonLoss::query()
                ->where('tenant_id', $tenantId)
                ->find($reasonLossId);

            if (! $reasonLoss) {
                return ToolResultDTO::failure('Reason loss not found');
            }

            $payload['crm_reason_loss_id'] = $reasonLoss->id;
        }

        $negotiation->update($payload);

        return ToolResultDTO::success(
            message: "Negotiation closed as {$outcome}",
            data: [
                'negotiation_id' => $negotiation->id,
                'outcome' => $outcome,
                'reason' => $input->parameters['reason'] ?? null,
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Closes a negotiation as won or lost.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'negotiation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of negotiation',
            ],
            'outcome' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Final outcome: won or lost',
            ],
            'reason' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional textual reason',
            ],
            'reason_loss_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of reason loss (used when outcome is lost)',
            ],
        ];
    }
}
