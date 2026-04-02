<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMProductActions;
use Domain\CRM\DTOs\CRMProductDTO;
use Domain\CRM\Http\Requests\CRMProductRequest;
use Domain\CRM\Http\Resources\CRMProductResource;
use Domain\CRM\Models\CRMProduct;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Domain\Shared\Support\ListFilterNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para produtos/serviços do CRM.
 *
 * @see CRMProductActions
 */
final class CRMProductController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMProductActions  $actions  Ação de gestão de produtos.
     */
    public function __construct(private readonly CRMProductActions $actions) {}

    /**
     * Listar produtos com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de produtos.
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = ListFilterNormalizer::normalizeIsActive($request->input('is_active'), true);

        return $this->crudIndex(
            $request,
            CRMProduct::class,
            fn (string $tenantId) => $this->actions->list($tenantId, [
                'search' => $request->input('search'),
                'type' => $request->input('type'),
                'is_active' => $isActive,
                'per_page' => $request->input('per_page'),
                'sort_by' => $request->input('sort_by'),
                'sort_dir' => $request->input('sort_dir'),
            ]),
            fn (mixed $item) => new CRMProductResource($item),
            'Produtos listados',
        );
    }

    /**
     * Criar novo produto.
     *
     * @param  CRMProductRequest  $request  Requisição com dados do produto.
     * @return JsonResponse Produto criado.
     */
    public function store(CRMProductRequest $request): JsonResponse
    {
        return $this->crudStore(
            $request,
            CRMProduct::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMProductDTO::fromRequest($request)),
            fn (mixed $item) => new CRMProductResource($item),
            'Produto criado',
        );
    }

    /**
     * Obter detalhes de um produto.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do produto.
     * @return JsonResponse Dados do produto.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->crudShow(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (mixed $item) => new CRMProductResource($item),
            'Produto carregado',
        );
    }

    /**
     * Atualizar produto.
     *
     * @param  CRMProductRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do produto.
     * @return JsonResponse Produto atualizado.
     */
    public function update(CRMProductRequest $request, string $id): JsonResponse
    {
        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMProductDTO::fromRequest($request)),
            fn (mixed $item) => new CRMProductResource($item),
            'Produto atualizado',
        );
    }

    /**
     * Excluir produto.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do produto.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        return $this->crudDestroy(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->delete($tenantId, $modelId),
        );
    }
}
