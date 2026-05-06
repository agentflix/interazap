<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Implementação baseada em Eloquent para o repositório de usuários.
 */
final class EloquentAuthUserRepository implements AuthUserRepository
{
    public function findByEmail(string $email): ?AuthUser
    {
        return AuthUser::where('email', $email)->first();
    }

    public function save(AuthUser $user): void
    {
        $user->save();
    }

    public function paginate(AuthUserFiltersDTO $filters): LengthAwarePaginator
    {
        $query = AuthUser::query()->with(['roles', 'tenant']);

        if ($filters->search !== null && $filters->search !== '') {
            $search = $filters->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                    ->orWhere('email', 'ilike', SearchSanitizer::likeContains($search));
            });
        }

        if ($filters->tenantId) {
            $query->where('tenant_id', $filters->tenantId);
        }

        if ($filters->isActive !== null) {
            $query->where('is_active', $filters->isActive);
        }

        if ($filters->role !== null && $filters->role !== '') {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters->role));
        }

        return $query
            ->orderBy($filters->sanitizedSortBy(), $filters->sanitizedSortDirection())
            ->paginate($filters->sanitizedPerPage());
    }

    public function findOrFail(string $id, array $relations = []): AuthUser
    {
        return AuthUser::with(array_values(array_unique([...$relations, 'tenant'])))->findOrFail($id);
    }

    public function create(array $attributes): AuthUser
    {
        return AuthUser::create($attributes);
    }

    public function delete(AuthUser $user): void
    {
        $user->delete();
    }
}
