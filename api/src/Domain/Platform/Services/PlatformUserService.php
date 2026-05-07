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
     * Lista usuários de todos os tenants.
     *
     * @return LengthAwarePaginator<int, AuthUser>
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

    public function createForAnyTenant(AuthUserDTO $dto): AuthUser
    {
        return $this->actions->create($dto);
    }

    public function updateForAnyTenant(string $id, AuthUserDTO $dto): AuthUser
    {
        return $this->actions->update($id, $dto);
    }

    public function deleteForAnyTenant(string $id): void
    {
        $this->actions->delete($id);
    }

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
