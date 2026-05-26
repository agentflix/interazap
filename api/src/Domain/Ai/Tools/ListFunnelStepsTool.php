<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiationFunnel;

/**
 * Ferramenta de IA para listar funis de negociação e suas etapas.
 *
 * Input esperado: funnel_id opcional para filtrar por funil específico.
 * Output produzido: lista de funis com seus respectivos steps ordenados.
 * Quando usar: antes de criar uma negociação ou mover um lead de etapa, para obter os IDs disponíveis.
 */
class ListFunnelStepsTool implements AiToolInterface
{
    /** Executa a listagem de funis e etapas. */
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

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Lists available negotiation funnels and their steps.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
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
