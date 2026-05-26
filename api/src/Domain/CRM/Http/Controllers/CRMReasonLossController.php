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
 * Controller para motivos de perda de negociações do CRM.
 *
 * Gerencia criação, listagem, atualização e remoção de motivos de perda. Requer autenticação Sanctum.
 */
final class CRMReasonLossController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMReasonLossActions  $actions  Ação de gestão de motivos de perda.
     */
    public function __construct(private readonly CRMReasonLossActions $actions) {}

    /**
     * Lista motivos de perda com filtro opcional por status ativo.
     *
     * @param  Request  $request  Dados da requisição com filtro opcional active.
     * @return JsonResponse Lista paginada de motivos de perda.
     */
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

    /**
     * Lista todos os motivos de perda sem paginação.
     *
     * @param  Request  $request  Dados da requisição.
     * @return JsonResponse Lista completa de motivos de perda.
     */
    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMReasonLoss::class);

        $tenantId = $this->tenantId();
        $reasons = $this->actions->all($tenantId)
            ->map(fn (mixed $item) => new CRMReasonLossResource($item));

        return $this->success($reasons, 'Motivos de perda carregados');
    }

    /**
     * Cria novo motivo de perda.
     *
     * @param  CRMReasonLossRequest  $request  Dados da requisição com nome e descrição.
     * @return JsonResponse Motivo de perda criado.
     */
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

    /**
     * Atualiza motivo de perda.
     *
     * @param  CRMReasonLossRequest  $request  Dados atualizados do motivo.
     * @param  string  $id  ID do motivo de perda.
     * @return JsonResponse Motivo de perda atualizado.
     */
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

    /**
     * Remove motivo de perda.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $id  ID do motivo de perda.
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
