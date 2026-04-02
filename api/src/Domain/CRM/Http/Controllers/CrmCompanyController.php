<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMCompanyActions;
use Domain\CRM\DTOs\CRMCompanyDTO;
use Domain\CRM\Http\Requests\CRMCompanyRequest;
use Domain\CRM\Http\Resources\CRMCompanyResource;
use Domain\CRM\Models\CRMCompany;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para gestão de empresas do CRM.
 *
 * @see CRMCompanyActions
 */
final class CRMCompanyController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMCompanyActions  $actions  Ação de gestão de empresas.
     */
    public function __construct(private readonly CRMCompanyActions $actions) {}

    /**
     * Listar empresas com busca e filtro de status.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de empresas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMCompany::class);

        $searchTerm = $request->input('search');
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

        return $this->crudIndex(
            $request,
            CRMCompany::class,
            fn (string $tenantId) => $this->actions->list($tenantId, $searchTerm, $isActive),
            fn (mixed $item) => new CRMCompanyResource($item),
            'Empresas listadas',
        );
    }

    /**
     * Criar nova empresa.
     *
     * @param  CRMCompanyRequest  $request  Requisição com dados da empresa.
     * @return JsonResponse Empresa criada.
     */
    public function store(CRMCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', CRMCompany::class);

        return $this->crudStore(
            $request,
            CRMCompany::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMCompanyDTO::fromRequest($request)),
            fn (mixed $item) => new CRMCompanyResource($item),
            'Empresa criada',
        );
    }

    /**
     * Obter detalhes de uma empresa.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da empresa.
     * @return JsonResponse Dados da empresa.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $company = $this->actions->find($tenantId, $id);
        $this->authorize('view', $company);

        return $this->crudShow(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (mixed $item) => new CRMCompanyResource($item),
            'Empresa carregada',
        );
    }

    /**
     * Atualizar empresa.
     *
     * @param  CRMCompanyRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID da empresa.
     * @return JsonResponse Empresa atualizada.
     */
    public function update(CRMCompanyRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $company = $this->actions->find($tenantId, $id);
        $this->authorize('update', $company);

        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMCompanyDTO::fromRequest($request)),
            fn (mixed $item) => new CRMCompanyResource($item),
            'Empresa atualizada',
        );
    }

    /**
     * Excluir empresa.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID da empresa.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $company = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $company);

        return $this->crudDestroy(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->delete($tenantId, $modelId),
        );
    }
}
