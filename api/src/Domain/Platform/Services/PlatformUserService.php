<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Domain\Auth\Actions\AuthUserActions;
use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Serviço para gestão de usuários em nível de plataforma (todos os tenants).
 */
final class PlatformUserService
{
    public function __construct(
        private readonly AuthUserActions $actions,
    ) {}

    /**
     * Lista usuários de todos os tenants com filtros e paginação.
     *
     * @param  AuthUserFiltersDTO  $filters  Filtros de busca e ordenação.
     * @return LengthAwarePaginator<int, AuthUser> Lista paginada de usuários.
     */
    public function listAllTenants(AuthUserFiltersDTO $filters): LengthAwarePaginator
    {
        $query = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with(['tenant', 'roles'])
            ->withCount(['roles']);

        if ($filters->search !== null && $filters->search !== '') {
            $search = '%'.$filters->search.'%';
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search);
            });
        }

        if ($filters->role !== null) {
            $query->role($filters->role);
        }

        if ($filters->departmentId !== null) {
            $query->where('department_id', $filters->departmentId);
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        if ($filters->tenantId !== null) {
            $query->where('tenant_id', $filters->tenantId);
        }

        return $query
            ->orderBy($filters->sanitizedSortBy(), $filters->sanitizedSortDirection())
            ->paginate($filters->sanitizedPerPage());
    }

    /**
     * Cria um usuário em qualquer tenant, sem restrições de TenantScope.
     *
     * @param  AuthUserDTO  $dto  Dados do usuário.
     * @return AuthUser Usuário criado.
     */
    public function createForAnyTenant(AuthUserDTO $dto): AuthUser
    {
        return $this->actions->create($dto);
    }

    /**
     * Atualiza um usuário em qualquer tenant.
     *
     * @param  string  $id  UUID do usuário.
     * @param  AuthUserDTO  $dto  Novos dados do usuário.
     * @return AuthUser Usuário atualizado.
     */
    public function updateForAnyTenant(string $id, AuthUserDTO $dto): AuthUser
    {
        return $this->actions->update($id, $dto);
    }

    /**
     * Exclui um usuário de qualquer tenant.
     *
     * @param  string  $id  UUID do usuário.
     */
    public function deleteForAnyTenant(string $id): void
    {
        $this->actions->delete($id);
    }

    /**
     * Alterna o status ativo/inativo de um usuário de qualquer tenant.
     *
     * @param  string  $id  UUID do usuário.
     * @return AuthUser Usuário com status atualizado.
     */
    public function toggleActiveForAnyTenant(string $id): AuthUser
    {
        return $this->actions->toggleActive($id);
    }

    /**
     * @return array<string, string>
     */
    public function updateAvatarForAnyTenant(string $id, UploadedFile $file): array
    {
        return $this->actions->updateAvatar($id, $file);
    }

    /**
     * @return array{avatar_url: null}
     */
    public function deleteAvatarForAnyTenant(string $id): array
    {
        return $this->actions->deleteAvatar($id);
    }
}
