<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMProduct;

/**
 * Tool to list CRM products catalog.
 */
class ListProductsTool implements AiToolInterface
{
    /**
     * Execute tool handler.
     */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $activeOnly = filter_var($input->parameters['active_only'] ?? true, FILTER_VALIDATE_BOOL);
        $limit = min(100, max(1, (int) ($input->parameters['limit'] ?? 20)));

        $products = CRMProduct::query()
            ->where('tenant_id', $tenantId)
            ->when($activeOnly, fn ($builder) => $builder->where('is_active', true))
            ->limit($limit)
            ->get();

        return ToolResultDTO::success(
            message: sprintf('%d products found', $products->count()),
            data: [
                'products' => $products->map(static fn (CRMProduct $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => (float) $product->price,
                    'is_active' => (bool) $product->is_active,
                ])->values()->all(),
            ]
        );
    }

    /**
     * Return tool unique name.
     */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS;
    }

    /**
     * Return tool description.
     */
    public function getDescription(): string
    {
        return 'Lists available CRM products with optional active filter.';
    }

    /**
     * Return expected tool parameters.
     *
     * @return array<string, array{type: string, required: bool, description: string}>
     */
    public function getParameters(): array
    {
        return [
            'active_only' => [
                'type' => 'boolean',
                'required' => false,
                'description' => 'When true, returns only active products',
            ],
            'limit' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Maximum number of products (1-100)',
            ],
        ];
    }
}
