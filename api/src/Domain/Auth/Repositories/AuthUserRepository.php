<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthUser;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Contrato para recuperar e persistir usuários de autenticação.
 *
 * Implementadores devem respeitar o escopo de tenant quando aplicável.
 */
interface AuthUserRepository
{
    /** Busca usuário pelo endereço de e-mail. */
    public function findByEmail(string $email): ?AuthUser;

    /** Persiste o usuário no banco de dados. */
    public function save(AuthUser $user): void;

    /**
     * Retorna lista paginada de usuários conforme os filtros.
     *
     * @return LengthAwarePaginator<int, AuthUser>
     */
    public function paginate(AuthUserFiltersDTO $filters): LengthAwarePaginator;

    /**
     * Busca usuário por ID ou lança ModelNotFoundException.
     *
     * @param  array<int, string>  $relations  Relações a eager-load.
     */
    public function findOrFail(string $id, array $relations = []): AuthUser;

    /**
     * Cria e persiste novo usuário com os atributos fornecidos.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): AuthUser;

    /** Remove o usuário (soft-delete). */
    public function delete(AuthUser $user): void;
}
