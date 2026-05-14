<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthRoleDTO;
use Domain\Auth\DTOs\AuthRoleFiltersDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Casos de uso relacionados ao gerenciamento de roles.
 */
final class AuthRoleActions
{
    /**
     * UUIDs das roles de sistema que não podem ser excluídas.
     */
    private const SYSTEM_ROLE_IDS = [
        AuthRole::ADMINISTRADOR_ID,
        AuthRole::INQUILINO_ID,
        AuthRole::GERENTE_ID,
        AuthRole::ATENDENTE_ID,
    ];

    /**
     * @return LengthAwarePaginator<int, AuthRole>
     */
    public function list(AuthRoleFiltersDTO $filters, bool $excludeSuperAdmin = false): LengthAwarePaginator
    {
        $query = AuthRole::query()
            ->withCount(['permissions', 'users'])
            ->with('permissions:id,name');

        if ($filters->search !== null && $filters->search !== '') {
            $query->where('name', 'ilike', SearchSanitizer::likeContains($filters->search));
        }

        if ($excludeSuperAdmin) {
            $query->where('id', '!=', AuthRole::ADMINISTRADOR_ID);
        }

        return $query
            ->orderBy($filters->sanitizedSortBy(), $filters->sanitizedSortDirection())
            ->paginate($filters->sanitizedPerPage());
    }

    public function find(string $id): AuthRole
    {
        return AuthRole::with(['permissions:id,name'])
            ->withCount(['users'])
            ->findOrFail($id);
    }

    public function create(AuthRoleDTO $dto): AuthRole
    {
        return DB::transaction(function () use ($dto) {
            /** @var AuthRole $role */
            $role = AuthRole::create([
                'name' => $dto->name,
                'guard_name' => 'sanctum',
            ]);

            if ($dto->permissions !== []) {
                $permissions = AuthPermission::whereIn('name', $dto->permissions)
                    ->where('guard_name', 'sanctum')
                    ->get();

                $role->syncPermissions($permissions);
            }

            return $role->load('permissions:id,name')->loadCount('users');
        });
    }

    public function update(string|AuthRole $role, AuthRoleDTO $dto): AuthRole
    {
        $resolvedRole = $this->resolveRole($role);

        return DB::transaction(function () use ($resolvedRole, $dto) {
            $resolvedRole->update(['name' => $dto->name]);

            $permissions = AuthPermission::whereIn('name', $dto->permissions)
                ->where('guard_name', 'sanctum')
                ->get();

            $resolvedRole->syncPermissions($permissions);

            return $resolvedRole->load('permissions:id,name')->loadCount('users');
        });
    }

    public function delete(string|AuthRole $role): void
    {
        $resolvedRole = $this->resolveRole($role);

        if (in_array($resolvedRole->id, self::SYSTEM_ROLE_IDS, true)) {
            throw new HttpException(403, 'Perfis protegidos não podem ser excluídos.');
        }

        $resolvedRole->delete();
    }

    private function resolveRole(string|AuthRole $role): AuthRole
    {
        if ($role instanceof AuthRole) {
            return $role;
        }

        return $this->find($role);
    }

    /**
     * Retorna todas as permissões disponíveis agrupadas por módulo.
     *
     * @return Collection<string, Collection<int, mixed>>
     */
    public function allPermissions(): Collection
    {
        return AuthPermission::where('guard_name', 'sanctum')
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0]);
    }

    /**
     * Lista usuários que possuem uma role específica.
     *
     * @param  string  $roleId  UUID da role.
     * @param  AuthUserFiltersDTO  $filters  Filtros de busca e paginação.
     * @return LengthAwarePaginator<int, AuthUser>
     */
    public function listUsersByRole(string $roleId, AuthUserFiltersDTO $filters): LengthAwarePaginator
    {
        $role = AuthRole::findOrFail($roleId);

        $query = AuthUser::query()
            ->whereHas('roles', fn ($q) => $q->where('id', $role->id))
            ->with(['roles', 'tenant']);

        if ($filters->search !== null && $filters->search !== '') {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                    ->orWhere('email', 'ilike', SearchSanitizer::likeContains($search));
            });
        }

        return $query
            ->orderBy($filters->sanitizedSortBy(), $filters->sanitizedSortDirection())
            ->paginate($filters->sanitizedPerPage());
    }
}
