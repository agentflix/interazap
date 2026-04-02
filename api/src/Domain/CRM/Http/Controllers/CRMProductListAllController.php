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
 * Lista produtos/serviços sem paginação para selects.
 */
final class CRMProductListAllController extends BaseController
{
    public function __construct(
        private readonly CRMProductActions $actions
    ) {}

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
