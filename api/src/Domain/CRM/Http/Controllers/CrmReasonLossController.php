<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMReasonLossActions;
use Domain\CRM\DTOs\CRMReasonLossDTO;
use Domain\CRM\Http\Requests\CRMReasonLossRequest;
use Domain\CRM\Http\Resources\CRMReasonLossResource;
use Domain\CRM\Models\CRMReasonLoss;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para motivos de perda.
 */
final class CRMReasonLossController extends BaseController
{
    use HandlesCrudOperations;

    public function __construct(private readonly CRMReasonLossActions $actions) {}

    public function index(Request $request): JsonResponse
    {
        return $this->crudIndex(
            $request,
            CRMReasonLoss::class,
            fn (string $tenantId) => $this->actions->list(
                $tenantId,
                $request->query('active') !== null ? (bool) $request->query('active') : null
            ),
            fn (mixed $item) => new CRMReasonLossResource($item),
            'Motivos de perda listados',
        );
    }

    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMReasonLoss::class);

        $tenantId = $this->tenantId();
        $reasons = $this->actions->all($tenantId)
            ->map(fn (mixed $item) => new CRMReasonLossResource($item));

        return $this->success($reasons, 'Motivos de perda carregados');
    }

    public function store(CRMReasonLossRequest $request): JsonResponse
    {
        return $this->crudStore(
            $request,
            CRMReasonLoss::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMReasonLossDTO::fromRequest($request)),
            fn (mixed $item) => new CRMReasonLossResource($item),
            'Motivo de perda criado',
        );
    }

    public function update(CRMReasonLossRequest $request, string $id): JsonResponse
    {
        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMReasonLossDTO::fromRequest($request)),
            fn (mixed $item) => new CRMReasonLossResource($item),
            'Motivo de perda atualizado',
        );
    }

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
