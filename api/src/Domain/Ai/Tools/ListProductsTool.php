<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\CRM\Models\CRMProduct;

/**
 * Ferramenta de IA para listar o catálogo de produtos do CRM.
 *
 * Input esperado: active_only (boolean, padrão true) e limit (1-100, padrão 20).
 * Output produzido: lista de produtos com id, nome, descrição e preço.
 * Quando usar: cliente perguntar sobre produtos disponíveis ou antes de adicionar itens a uma negociação.
 */
class ListProductsTool implements AiToolInterface
{
    /** Executa a listagem de produtos do catálogo. */
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

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Lists available CRM products with optional active filter.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
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
