<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contrato para recuperar e persistir usuários de autenticação.
 */
interface AuthUserRepository
{
    public function findByEmail(string $email): ?AuthUser;

    public function save(AuthUser $user): void;

    public function paginate(AuthUserFiltersDTO $filters): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $relations
     */
    public function findOrFail(string $id, array $relations = []): AuthUser;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AuthUser;

    public function delete(AuthUser $user): void;
}
