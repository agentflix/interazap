<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMDepartmentActions;
use Domain\CRM\DTOs\CRMDepartmentDTO;
use Domain\CRM\Http\Requests\CRMDepartmentRequest;
use Domain\CRM\Http\Resources\CRMDepartmentResource;
use Domain\CRM\Models\CRMDepartment;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Http\Controllers\Concerns\HandlesCrudOperations;
use Domain\Shared\Support\ListFilterNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de departamentos.
 *
 * @see CRMDepartmentActions
 */
final class CRMDepartmentController extends BaseController
{
    use HandlesCrudOperations;

    /**
     * @param  CRMDepartmentActions  $actions  Ação de gestão de departamentos.
     */
    public function __construct(private readonly CRMDepartmentActions $actions) {}

    /**
     * Listar departamentos com filtros.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de departamentos.
     */
    public function index(Request $request): JsonResponse
    {
        $isActive = ListFilterNormalizer::normalizeIsActive($request->input('is_active'), true);

        return $this->crudIndex(
            $request,
            CRMDepartment::class,
            fn (string $tenantId) => $this->actions->list($tenantId, [
                'search' => $request->input('search'),
                'is_active' => $isActive,
                'per_page' => $request->input('per_page'),
                'sort_by' => $request->input('sort_by'),
                'sort_dir' => $request->input('sort_dir'),
            ]),
            fn (mixed $item) => new CRMDepartmentResource($item),
            'Departamentos listados',
        );
    }

    /**
     * Listar todos os departamentos (sem paginação).
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Lista de departamentos.
     */
    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CRMDepartment::class);

        $tenantId = $this->tenantId();
        $departments = $this->actions->all($tenantId)
            ->map(fn ($item) => new CRMDepartmentResource($item));

        return $this->success(['departments' => $departments], 'Departamentos carregados');
    }

    /**
     * Obter detalhes de um departamento.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do departamento.
     * @return JsonResponse Dados do departamento.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        return $this->crudShow(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (mixed $item) => new CRMDepartmentResource($item),
            'Departamento carregado',
        );
    }

    /**
     * Criar novo departamento.
     *
     * @param  CRMDepartmentRequest  $request  Requisição com dados do departamento.
     * @return JsonResponse Departamento criado.
     */
    public function store(CRMDepartmentRequest $request): JsonResponse
    {
        return $this->crudStore(
            $request,
            CRMDepartment::class,
            fn (string $tenantId) => $this->actions->create($tenantId, CRMDepartmentDTO::fromRequest($request)),
            fn (mixed $item) => new CRMDepartmentResource($item),
            'Departamento criado',
        );
    }

    /**
     * Atualizar departamento.
     *
     * @param  CRMDepartmentRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do departamento.
     * @return JsonResponse Departamento atualizado.
     */
    public function update(CRMDepartmentRequest $request, string $id): JsonResponse
    {
        return $this->crudUpdate(
            $request,
            $id,
            fn (string $tenantId, string $modelId) => $this->actions->find($tenantId, $modelId),
            fn (string $tenantId, string $modelId) => $this->actions->update($tenantId, $modelId, CRMDepartmentDTO::fromRequest($request)),
            fn (mixed $item) => new CRMDepartmentResource($item),
            'Departamento atualizado',
        );
    }

    /**
     * Excluir departamento.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do departamento.
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

    /**
     * Ativar/inativar departamento.
     *
     * @param  Request  $request  Requisição atual.
     * @param  string  $id  ID do departamento.
     * @return JsonResponse Departamento com status atualizado.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId();
        $department = $this->actions->find($tenantId, $id);
        $this->authorize('update', $department);

        $department = $this->actions->toggleActive($tenantId, $id);

        return $this->success(new CRMDepartmentResource($department), 'Departamento atualizado');
    }
}
