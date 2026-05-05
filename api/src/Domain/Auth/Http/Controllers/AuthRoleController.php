<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthRoleActions;
use Domain\Auth\DTOs\AuthRoleDTO;
use Domain\Auth\DTOs\AuthRoleFiltersDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Http\Requests\AuthRoleStoreRequest;
use Domain\Auth\Http\Requests\AuthRoleUpdateRequest;
use Domain\Auth\Http\Resources\AuthRoleResource;
use Domain\Auth\Http\Resources\AuthUserResource;
use Domain\Auth\Models\AuthRole;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestão de perfis de acesso (roles) e permissões.
 *
 * @see AuthRoleActions
 */
final class AuthRoleController extends BaseController
{
    /**
     * @param  AuthRoleActions  $actions  Ação de gestão de roles.
     */
    public function __construct(private readonly AuthRoleActions $actions) {}

    /**
     * Listar perfis de acesso com paginação.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de perfis.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuthRole::class);

        $filters = AuthRoleFiltersDTO::fromArray($request->only([
            'search', 'sort_by', 'sort_direction', 'per_page',
        ]));

        $excludeSuperAdmin = ! $request->user()->isSuperAdmin();
        $paginator = $this->actions->list($filters, $excludeSuperAdmin);
        $paginator->getCollection()->transform(fn ($item) => new AuthRoleResource($item));

        return $this->paginated($paginator, 'Perfis listados com sucesso');
    }

    /**
     * Criar novo perfil de acesso.
     *
     * @param  AuthRoleStoreRequest  $request  Requisição com dados do perfil.
     * @return JsonResponse Perfil criado.
     */
    public function store(AuthRoleStoreRequest $request): JsonResponse
    {
        $this->authorize('create', AuthRole::class);

        $role = $this->actions->create(AuthRoleDTO::fromRequest($request));

        return $this->created(new AuthRoleResource($role), 'Perfil criado com sucesso');
    }

    /**
     * Obter detalhes de um perfil.
     *
     * @param  string  $id  ID do perfil.
     * @return JsonResponse Dados do perfil.
     */
    public function show(string $id): JsonResponse
    {
        $role = $this->actions->find($id);
        $this->authorize('view', $role);

        return $this->success(new AuthRoleResource($role), 'Perfil carregado');
    }

    /**
     * Atualizar perfil de acesso.
     *
     * @param  AuthRoleUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do perfil.
     * @return JsonResponse Perfil atualizado.
     */
    public function update(AuthRoleUpdateRequest $request, string $id): JsonResponse
    {
        $role = $this->actions->find($id);
        $this->authorize('update', $role);

        $role = $this->actions->update($role, AuthRoleDTO::fromRequest($request));

        return $this->success(new AuthRoleResource($role), 'Perfil atualizado com sucesso');
    }

    /**
     * Excluir perfil de acesso.
     *
     * @param  string  $id  ID do perfil.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(string $id): JsonResponse
    {
        $role = $this->actions->find($id);
        $this->authorize('delete', $role);

        $this->actions->delete($role);

        return $this->noContent();
    }

    /**
     * Retorna todas as permissões disponíveis agrupadas por módulo.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Permissões agrupadas.
     */
    public function permissions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuthRole::class);

        $grouped = $this->actions->allPermissions();

        return $this->success(['permissions' => $grouped], 'Permissões carregadas');
    }

    /**
     * Listar usuários de um perfil de acesso.
     *
     * @param  Request  $request  Requisição com filtros.
     * @param  string  $id  ID do perfil.
     * @return JsonResponse Lista paginada de usuários.
     */
    public function users(Request $request, string $id): JsonResponse
    {
        $role = $this->actions->find($id);
        $this->authorize('viewUsers', $role);

        $filters = AuthUserFiltersDTO::fromArray($request->only([
            'search', 'per_page', 'page',
        ]));

        $paginator = $this->actions->listUsersByRole($id, $filters);
        $paginator->getCollection()->transform(fn ($item) => new AuthUserResource($item));

        return $this->paginated($paginator, 'Usuários do perfil listados com sucesso');
    }
}
