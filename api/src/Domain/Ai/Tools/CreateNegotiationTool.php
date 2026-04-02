<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Illuminate\Support\Str;

/**
 * Tool to create a CRM negotiation.
 */
class CreateNegotiationTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $title = trim((string) ($input->parameters['title'] ?? ''));
        $contactId = (string) ($input->parameters['contact_id'] ?? '');
        $stepId = (string) ($input->parameters['step_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $amount = max(0.0, (float) ($input->parameters['amount'] ?? 0.0));

        if ($title === '') {
            return ToolResultDTO::failure('Title is required');
        }

        if ($stepId === '') {
            return ToolResultDTO::failure('Step ID is required');
        }

        if ($contactId !== '') {
            $contact = CRMContact::query()
                ->where('tenant_id', $tenantId)
                ->find($contactId);

            if (! $contact) {
                return ToolResultDTO::failure('Contact not found');
            }
        }

        $step = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->find($stepId);

        if (! $step) {
            return ToolResultDTO::failure('Funnel step not found');
        }

        $negotiation = CRMNegotiation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'crm_contact_id' => $contactId !== '' ? $contactId : null,
            'crm_negotiation_funnel_id' => $step->crm_negotiation_funnel_id,
            'crm_negotiation_funnel_step_id' => $step->id,
            'title' => $title,
            'amount' => $amount,
            'status' => 'open',
            'lead_score' => 0,
            'position' => 0,
        ]);

        return ToolResultDTO::success(
            message: "Negotiation '{$negotiation->title}' created",
            data: [
                'negotiation_id' => $negotiation->id,
                'funnel_step_id' => $step->id,
                'funnel_step_name' => $step->name,
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Creates a new negotiation/deal in a specific funnel step.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Negotiation title',
            ],
            'amount' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Negotiation amount',
            ],
            'contact_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of contact',
            ],
            'step_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of funnel step',
            ],
        ];
    }
}
