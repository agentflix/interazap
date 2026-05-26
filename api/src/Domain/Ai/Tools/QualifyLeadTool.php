<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMTag;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para qualificar um lead em uma única operação.
 *
 * Input esperado: negotiation_id (obrigatório); score (0-100), tags e step_id opcionais.
 * Output produzido: negotiation_id, campos aplicados, lead_score e step_id resultantes.
 * Quando usar: consolidar informações coletadas na conversa atualizando pontuação, etapa e tags do lead.
 */
class QualifyLeadTool implements AiToolInterface
{
    /** Executa a qualificação do lead com os dados fornecidos. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = (string) ($input->parameters['negotiation_id'] ?? '');
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($negotiationId === '') {
            return ToolResultDTO::failure('Negotiation ID is required');
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->with('contact.tags')
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        $applied = [];

        if (array_key_exists('score', $input->parameters)) {
            $score = (int) $input->parameters['score'];
            if ($score < 0 || $score > 100) {
                return ToolResultDTO::failure('Score must be between 0 and 100');
            }

            $negotiation->lead_score = $score;
            $applied[] = 'score';
        }

        $stepId = (string) ($input->parameters['step_id'] ?? '');
        if ($stepId !== '') {
            $step = CRMNegotiationFunnelStep::query()
                ->where('tenant_id', $tenantId)
                ->find($stepId);

            if (! $step) {
                return ToolResultDTO::failure('Funnel step not found');
            }

            $negotiation->crm_negotiation_funnel_step_id = $step->id;
            $negotiation->crm_negotiation_funnel_id = $step->crm_negotiation_funnel_id;
            $applied[] = 'step';
        }

        $tags = $input->parameters['tags'] ?? null;
        if (is_array($tags) && $tags !== []) {
            if (! $negotiation->contact) {
                return ToolResultDTO::failure('Negotiation has no contact linked');
            }

            $tagIds = [];
            foreach ($tags as $tagName) {
                if (! is_string($tagName) || trim($tagName) === '') {
                    continue;
                }

                $tag = CRMTag::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'name' => trim($tagName),
                    ],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'is_active' => true,
                    ]
                );

                $tagIds[$tag->id] = [
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                ];
            }

            if ($tagIds !== []) {
                $negotiation->contact->tags()->syncWithoutDetaching($tagIds);
                $applied[] = 'tags';
            }
        }

        if ($applied === []) {
            return ToolResultDTO::failure('No qualification data informed');
        }

        $negotiation->save();

        return ToolResultDTO::success(
            message: 'Lead qualified successfully',
            data: [
                'negotiation_id' => $negotiation->id,
                'applied' => $applied,
                'lead_score' => $negotiation->lead_score,
                'step_id' => $negotiation->crm_negotiation_funnel_step_id,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Qualifies a lead by updating score, tags and funnel step in a single call.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return [
            'negotiation_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'UUID of negotiation',
            ],
            'score' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Lead score from 0 to 100',
            ],
            'tags' => [
                'type' => 'array',
                'required' => false,
                'description' => 'List of tags to associate with contact',
                'items' => [
                    'type' => 'string',
                ],
            ],
            'step_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of target funnel step',
            ],
        ];
    }
}
