<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMProduct;
use Domain\CRM\Models\CRMProposal;
use Domain\CRM\Models\CRMProposalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para criar uma proposta comercial com itens de linha no CRM.
 *
 * Input esperado: negotiation_id, title e items (array com name, quantity, unit_price); valid_until opcional.
 * Output produzido: proposal_id, número da proposta, quantidade de itens e total calculado.
 * Quando usar: cliente solicitar proposta formal ou após qualificação de lead com produtos definidos.
 */
class CreateProposalTool implements AiToolInterface
{
    /** Executa a criação da proposta e seus itens em transação. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $negotiationId = (string) ($input->parameters['negotiation_id'] ?? '');
        $title = trim((string) ($input->parameters['title'] ?? ''));
        $items = $input->parameters['items'] ?? null;

        if ($negotiationId === '' || $title === '') {
            return ToolResultDTO::failure('negotiation_id and title are required');
        }

        if (! is_array($items) || $items === []) {
            return ToolResultDTO::failure('items must be a non-empty array');
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        $proposal = DB::transaction(function () use ($tenantId, $negotiation, $title, $items, $input): CRMProposal {
            $lastNumber = (int) CRMProposal::query()
                ->where('tenant_id', $tenantId)
                ->max('number');

            $proposal = CRMProposal::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'crm_negotiation_id' => $negotiation->id,
                'title' => $title,
                'number' => $lastNumber + 1,
                'status' => 'draft',
                'valid_until' => $input->parameters['valid_until'] ?? null,
                'total' => 0,
            ]);

            $total = 0.0;

            foreach (array_values($items) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $productId = (string) ($item['product_id'] ?? '');
                $product = null;
                if ($productId !== '') {
                    $product = CRMProduct::query()
                        ->where('tenant_id', $tenantId)
                        ->find($productId);

                    if (! $product) {
                        continue;
                    }
                }

                $name = trim((string) ($item['name'] ?? ($product ? $product->name : '')));
                if ($name === '') {
                    continue;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = max(0.0, (float) ($item['unit_price'] ?? ($product ? $product->price : 0.0)));
                $discount = max(0.0, (float) ($item['discount'] ?? 0.0));

                $lineTotal = max(0.0, ($quantity * $unitPrice) - $discount);

                CRMProposalItem::query()->create([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                    'crm_proposal_id' => $proposal->id,
                    'crm_product_id' => $product?->id,
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'total' => $lineTotal,
                    'position' => $index,
                ]);

                $total += $lineTotal;
            }

            $proposal->update(['total' => $total]);

            return $proposal->fresh(['items']) ?? $proposal;
        });

        return ToolResultDTO::success(
            message: "Proposal '{$proposal->title}' created",
            data: [
                'proposal_id' => $proposal->id,
                'proposal_number' => $proposal->number,
                'items_count' => $proposal->items->count(),
                'total' => (float) $proposal->total,
            ]
        );
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Creates a commercial proposal and its line items for a negotiation.';
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
            'title' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Proposal title',
            ],
            'items' => [
                'type' => 'array',
                'required' => true,
                'description' => 'Proposal line items',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Item name'],
                        'quantity' => ['type' => 'integer', 'description' => 'Item quantity'],
                        'price' => ['type' => 'number', 'description' => 'Item unit price'],
                    ],
                    'required' => ['name', 'quantity', 'price'],
                ],
            ],
            'valid_until' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Expiration date (Y-m-d)',
            ],
        ];
    }
}
