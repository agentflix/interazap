<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiationFunnel;

/**
 * Tool to list negotiation funnels and steps.
 */
class ListFunnelStepsTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $funnelId = (string) ($input->parameters['funnel_id'] ?? '');

        $funnels = CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->when($funnelId !== '', fn ($builder) => $builder->where('id', $funnelId))
            ->with(['steps' => fn ($query) => $query->orderBy('order')])
            ->get();

        return ToolResultDTO::success(
            message: sprintf('%d funnel(s) found', $funnels->count()),
            data: [
                'funnels' => $funnels->map(static fn (CRMNegotiationFunnel $funnel): array => [
                    'id' => $funnel->id,
                    'name' => $funnel->name,
                    'steps' => $funnel->steps->map(static fn ($step): array => [
                        'id' => $step->id,
                        'name' => $step->name,
                        'order' => $step->order,
                        'is_active' => (bool) $step->is_active,
                    ])->values()->all(),
                ])->values()->all(),
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Lists available negotiation funnels and their steps.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'funnel_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Optional funnel UUID to filter result',
            ],
        ];
    }
}
