<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthUserActions;
use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Http\Requests\AuthUserAvatarRequest;
use Domain\Auth\Http\Requests\AuthUserStoreRequest;
use Domain\Auth\Http\Requests\AuthUserUpdateRequest;
use Domain\Auth\Http\Resources\AuthUserResource;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestão de usuários autenticados (admin/tenant).
 *
 * @see AuthUserActions
 */
final class AuthUserController extends BaseController
{
    /**
     * @param  AuthUserActions  $actions  Ação de gestão de usuários.
     */
    public function __construct(private readonly AuthUserActions $actions) {}

    /**
     * Listar usuários com paginação e filtros.
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
        $paginator = $this->actions->list($filters);
        $paginator->getCollection()->transform(fn ($item) => new AuthUserResource($item));

        return $this->paginated($paginator, 'Usuários listados com sucesso');
    }

    /**
     * Criar novo usuário.
     *
     * @param  AuthUserStoreRequest  $request  Requisição com dados do usuário.
     * @return JsonResponse Usuário criado.
     */
    public function store(AuthUserStoreRequest $request): JsonResponse
    {
        $this->authorize('create', AuthUser::class);
        $user = $this->actions->create(AuthUserDTO::fromRequest($request));

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
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('view', $user);

        return $this->success(new AuthUserResource($user), 'Usuário carregado');
    }

    /**
     * Atualizar dados do usuário.
     *
     * @param  AuthUserUpdateRequest  $request  Requisição com dados atualizados.
     * @param  string  $id  ID do usuário.
     * @return JsonResponse Usuário atualizado.
     */
    public function update(AuthUserUpdateRequest $request, string $id): JsonResponse
    {
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('update', $user);
        $user = $this->actions->update($id, AuthUserDTO::fromRequest($request));

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
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('delete', $user);
        $this->actions->delete($id);

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
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('toggle', $user);
        $user = $this->actions->toggleActive($id);

        return $this->success(new AuthUserResource($user), 'Status atualizado');
    }

    /** Alias compatível para alternância de status do usuário. */
    public function toggleStatus(string $id): JsonResponse
    {
        return $this->toggle($id);
    }

    /**
     * Sincronizar a lista completa de perfis do usuário.
     *
     * @param  Request  $request  Requisição com array 'roles'.
     * @param  string  $id  UUID do usuário.
     * @return JsonResponse Usuário com perfis atualizados.
     */
    public function syncRoles(Request $request, string $id): JsonResponse
    {
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('update', $user);

        /** @var array{roles: array<int, string>} $payload */
        $payload = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
        ]);

        $user = $this->actions->syncRoles($id, $payload['roles']);
        $user->load(['permissions']);

        return $this->success(new AuthUserResource($user), 'Perfis sincronizados');
    }

    /**
     * Remove um perfil específico do usuário sem afetar os demais.
     *
     * @param  Request  $request  Requisição com campo 'role'.
     * @param  string  $id  UUID do usuário.
     * @return JsonResponse Usuário com perfis atualizados.
     */
    public function removeRole(Request $request, string $id): JsonResponse
    {
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('update', $user);

        /** @var array{role: string} $payload */
        $payload = $request->validate([
            'role' => ['required', 'string'],
        ]);

        $user = $this->actions->removeRole($id, $payload['role']);
        $user->load(['permissions']);

        return $this->success(new AuthUserResource($user), 'Perfil removido do usuário');
    }

    /**
     * Revoga todos os tokens Sanctum do usuário, forçando novo login.
     *
     * @param  string  $id  UUID do usuário.
     * @return JsonResponse Usuário com tokens revogados.
     */
    public function revokeAllTokens(string $id): JsonResponse
    {
        $user = AuthUser::with(['roles', 'permissions'])->findOrFail($id);
        $this->authorize('update', $user);

        $user->tokens()->delete();

        return $this->success(new AuthUserResource($user), 'Tokens revogados');
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
        $user = AuthUser::findOrFail($id);
        $this->authorize('update', $user);
        $payload = $this->actions->updateAvatar($id, $request->file('image'));

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
        $user = AuthUser::findOrFail($id);
        $this->authorize('update', $user);
        $payload = $this->actions->deleteAvatar($id);

        return $this->success($payload, 'Avatar removido');
    }
}
