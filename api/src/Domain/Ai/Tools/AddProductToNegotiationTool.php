<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationProduct;
use Domain\CRM\Models\CRMProduct;
use Illuminate\Support\Str;

/**
 * Tool to add products to a negotiation.
 */
class AddProductToNegotiationTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $negotiationId = (string) ($input->parameters['negotiation_id'] ?? '');
        $productId = (string) ($input->parameters['product_id'] ?? '');
        $name = trim((string) ($input->parameters['name'] ?? ''));
        $quantity = max(1, (int) ($input->parameters['qty'] ?? 1));
        $tenantId = (string) ($input->context['tenant_id'] ?? '');

        if ($negotiationId === '') {
            return ToolResultDTO::failure('Negotiation ID is required');
        }

        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->find($negotiationId);

        if (! $negotiation) {
            return ToolResultDTO::failure('Negotiation not found');
        }

        $product = null;
        if ($productId !== '') {
            $product = CRMProduct::query()
                ->where('tenant_id', $tenantId)
                ->find($productId);

            if (! $product) {
                return ToolResultDTO::failure('Product not found');
            }
        }

        if ($name === '' && ! $product) {
            return ToolResultDTO::failure('Either product_id or name must be informed');
        }

        $unitPrice = (float) ($input->parameters['unit_price'] ?? ($product ? $product->price : 0));
        if ($unitPrice < 0) {
            return ToolResultDTO::failure('Unit price cannot be negative');
        }

        $itemName = $name !== '' ? $name : (string) ($product ? $product->name : '');
        $total = $quantity * $unitPrice;

        $item = CRMNegotiationProduct::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'crm_negotiation_id' => $negotiation->id,
            'crm_product_id' => $product?->id,
            'name' => $itemName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => $total,
        ]);

        $negotiation->update([
            'amount' => (float) $negotiation->amount + $total,
        ]);

        return ToolResultDTO::success(
            message: 'Product added to negotiation',
            data: [
                'negotiation_product_id' => $item->id,
                'negotiation_id' => $negotiation->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $total,
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Adds a product/item to an existing negotiation.';
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
            'product_id' => [
                'type' => 'string',
                'required' => false,
                'description' => 'UUID of product from catalog',
            ],
            'name' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Custom item name when product_id is not informed',
            ],
            'qty' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Item quantity',
            ],
            'unit_price' => [
                'type' => 'number',
                'required' => false,
                'description' => 'Unit price override',
            ],
        ];
    }
}
