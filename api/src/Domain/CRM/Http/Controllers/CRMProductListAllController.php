<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMProductActions;
use Domain\CRM\Http\Resources\CRMProductResource;
use Domain\CRM\Models\CRMProduct;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para listagem completa de produtos/serviços do CRM sem paginação.
 *
 * Retorna todos os produtos ativos para uso em selects e componentes de seleção. Requer autenticação Sanctum.
 */
final class CRMProductListAllController extends BaseController
{
    /**
     * @param  CRMProductActions  $actions  Ação de gestão de produtos.
     */
    public function __construct(
        private readonly CRMProductActions $actions
    ) {}

    /**
     * Lista todos os produtos/serviços sem paginação, com filtro opcional por tipo.
     *
     * @param  Request  $request  Dados da requisição com filtro opcional de tipo (product|service).
     * @return JsonResponse Lista de produtos/serviços.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMProduct::class);

        $tenantId = $this->tenantId();
        $typeInput = $request->input('type');
        $type = is_string($typeInput) && in_array($typeInput, ['product', 'service'], true)
            ? $typeInput
            : null;

        $items = $this->actions->listAll($tenantId, $type)
            ->map(fn ($item) => new CRMProductResource($item));

        return $this->success($items, 'Produtos carregados');
    }
}
