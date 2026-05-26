<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;

/**
 * Ferramenta de IA para recuperar informações completas de uma negociação.
 *
 * Input esperado: negotiation_id (UUID da negociação).
 * Output produzido: dados completos incluindo contato, empresa, funil, etapa, produtos e motivo de perda.
 * Quando usar: contextualizar o agente sobre uma negociação antes de tomar ações relacionadas.
 */
class GetNegotiationInfoTool implements AiToolInterface
{
    /** Executa a recuperação dos dados da negociação com seus relacionamentos. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = (string) ($input->parameters['negotiation_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($negotiationId === '') {
            return ToolResultDTO::failure('Negotiation ID is required');
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'contact',
                'company',
                'funnel',
                'step',
                'products.product',
                'proposals.items',
                'reasonLoss',
            ])
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        return ToolResultDTO::success(
            message: 'Negotiation loaded successfully',
            data: [
                'negotiation' => [
                    'id' => $negotiation->id,
                    'title' => $negotiation->title,
                    'amount' => (float) $negotiation->amount,
                    'status' => $negotiation->status->value,
                    'lead_score' => $negotiation->lead_score,
                    'closed_at' => $negotiation->closed_at?->toIso8601String(),
                    'contact' => $negotiation->contact ? [
                        'id' => $negotiation->contact->id,
                        'name' => $negotiation->contact->name,
                        'email' => $negotiation->contact->email,
                        'phone' => $negotiation->contact->phone,
                    ] : null,
                    'company' => $negotiation->company ? [
                        'id' => $negotiation->company->id,
                        'name' => $negotiation->company->name,
                        'document' => $negotiation->company->document,
                    ] : null,
                    'funnel' => $negotiation->funnel ? [
                        'id' => $negotiation->funnel->id,
                        'name' => $negotiation->funnel->name,
                    ] : null,
                    'step' => $negotiation->step ? [
                        'id' => $negotiation->step->id,
                        'name' => $negotiation->step->name,
                    ] : null,
                    'products' => $negotiation->products->map(static fn ($item): array => [
                        'id' => $item->id,
                        'product_id' => $item->crm_product_id,
                        'name' => $item->name,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total' => (float) $item->total,
                    ])->values()->all(),
                    'reason_loss' => $negotiation->reasonLoss ? [
                        'id' => $negotiation->reasonLoss->id,
                        'name' => $negotiation->reasonLoss->name,
                    ] : null,
                ],
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Returns complete negotiation information with related entities.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'negotiation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of the negotiation to retrieve',
            ],
        ];
    }
}
