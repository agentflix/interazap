<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Http\Requests\AuthUserAvatarRequest;
use Domain\Auth\Http\Requests\AuthUserStoreRequest;
use Domain\Auth\Http\Requests\AuthUserUpdateRequest;
use Domain\Auth\Http\Resources\AuthUserResource;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Actions\PlatformUserImpersonateAction;
use Domain\Platform\Http\Requests\PlatformTenantImpersonateRequest;
use Domain\Platform\Services\PlatformUserService;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestão de usuários em nível de plataforma (todos os tenants).
 *
 * @see PlatformUserService
 */
final class PlatformUserController extends BaseController
{
    /**
     * @param  PlatformUserService  $service  Serviço de gestão de usuários.
     * @param  PlatformUserImpersonateAction  $impersonateAction  Ação de impersonação.
     */
    public function __construct(
        private readonly PlatformUserService $service,
        private readonly PlatformUserImpersonateAction $impersonateAction,
    ) {}

    /**
     * Listar usuários de todos os tenants.
     *
     * @param  Request  $request  Requisição com filtros.
     * @return JsonResponse Lista paginada de usuários.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuthUser::class);

        $filters = AuthUserFiltersDTO::fromArray($request->only([
            'search', 'role', 'department_id', 'is_active', 'per_page', 'page', 'tenant_id',
        ]));

        $paginator = $this->service->listAllTenants($filters);
        $paginator->getCollection()->transform(fn ($item) => new AuthUserResource($item));

        return $this->paginated($paginator, 'Usuários listados com sucesso');
    }

    /**
     * Criar usuário em qualquer tenant.
     *
     * @param  AuthUserStoreRequest  $request  Requisição com dados do usuário.
     * @return JsonResponse Usuário criado.
     */
    public function store(AuthUserStoreRequest $request): JsonResponse
    {
        $this->authorize('create', AuthUser::class);
        $user = $this->service->createForAnyTenant(AuthUserDTO::fromRequest($request));

        return $this->created(new AuthUserResource($user), 'Usuário criado com sucesso');
    }

    /**
     * Obter detalhes de um usuário.
     *
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Dados do usuário.
     */
    public function show(string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('roles')
            ->findOrFail($id);
        $this->authorize('view', $user);

        return $this->success(new AuthUserResource($user), 'Usuário carregado');
    }

    /**
     * Atualizar usuário.
     *
     * @param  AuthUserUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Usuário atualizado.
     */
    public function update(AuthUserUpdateRequest $request, string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
        $this->authorize('update', $user);
        $user = $this->service->updateForAnyTenant($id, AuthUserDTO::fromRequest($request));

        return $this->success(new AuthUserResource($user), 'Usuário atualizado com sucesso');
    }

    /**
     * Excluir usuário.
     *
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Resposta sem conteúdo.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
        $this->authorize('delete', $user);
        $this->service->deleteForAnyTenant($id);

        return $this->noContent();
    }

    /**
     * Ativar/inativar usuário.
     *
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Usuário com status atualizado.
     */
    public function toggle(string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
        $this->authorize('toggle', $user);
        $user = $this->service->toggleActiveForAnyTenant($id);

        return $this->success(new AuthUserResource($user), 'Status atualizado');
    }

    /**
     * Upload de avatar do usuário.
     *
     * @param  AuthUserAvatarRequest  $request  Requisição com arquivo.
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Avatar atualizado.
     */
    public function uploadAvatar(AuthUserAvatarRequest $request, string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
        $this->authorize('update', $user);
        $payload = $this->service->updateAvatarForAnyTenant($id, $request->file('image'));

        return $this->success($payload, 'Avatar atualizado');
    }

    /**
     * Remover avatar do usuário.
     *
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Confirmação.
     */
    public function deleteAvatar(string $id): JsonResponse
    {
        $user = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
        $this->authorize('update', $user);
        $payload = $this->service->deleteAvatarForAnyTenant($id);

        return $this->success($payload, 'Avatar removido');
    }

    /**
     * Impersonar um usuário como super admin.
     *
     * Solicita a senha do super admin e retorna uma sessão autenticada
     * em nome do usuário especificado.
     *
     * @param  PlatformTenantImpersonateRequest  $request  Requisição com senha do super admin.
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Sessão do usuário com metadados de impersonação.
     */
    public function impersonate(PlatformTenantImpersonateRequest $request, string $id): JsonResponse
    {
        $targetUser = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('tenant')
            ->findOrFail($id);
        $this->authorize('impersonate', $targetUser);

        /** @var \Domain\Auth\Models\AuthUser $superAdmin */
        $superAdmin = $request->user();

        $session = $this->impersonateAction->execute(
            $targetUser,
            $superAdmin,
            $request->validated('password'),
        );

        return $this->success($session, 'Impersonação realizada com sucesso');
    }
}
