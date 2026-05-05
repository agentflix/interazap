<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\DTOs\AuthUserFiltersDTO;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Repositories\AuthUserRepository;
use Domain\Auth\Services\AuthAvatarManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Casos de uso relacionados ao gerenciamento de usuários do tenant.
 */
final class AuthUserActions
{
    public function __construct(
        private readonly AuthUserRepository $authUserRepository,
        private readonly AuthAvatarManager $avatarManager,
    ) {}

    /**
     * @return LengthAwarePaginator<int, AuthUser>
     */
    public function list(AuthUserFiltersDTO $filters): LengthAwarePaginator
    {
        return $this->authUserRepository->paginate($filters);
    }

    /**
     * Buscar usuário por ID com relações carregadas.
     *
     * @param  string  $id  UUID do usuário.
     */
    public function find(string $id): AuthUser
    {
        return $this->authUserRepository->findOrFail($id, ['roles']);
    }

    /**
     * Criar um novo usuário no tenant.
     *
     * @param  AuthUserDTO  $dto  Dados do usuário.
     * @return AuthUser Usuário criado com roles e tenant.
     *
     * @throws AuthorizationException Quando tenta atribuir super-admin sem permissão.
     */
    public function create(AuthUserDTO $dto): AuthUser
    {
        $this->guardSuperAdminAssignment($dto);

        return DB::transaction(function () use ($dto) {
            $data = $dto->toPersistenceArray();

            if (isset($data['password'])) {
                $data['password'] = Hash::make((string) $data['password']);
            }

            $user = $this->authUserRepository->create($data);

            if ($dto->roles !== []) {
                $user->syncRoles($dto->roles);
            } elseif ($dto->role) {
                $user->assignRole($dto->role);
            }

            return $user->load(['roles', 'tenant']);
        });
    }

    /**
     * Atualizar dados de um usuário existente.
     *
     * @param  string  $id  UUID do usuário.
     * @param  AuthUserDTO  $dto  Dados atualizados.
     * @return AuthUser Usuário atualizado com roles e tenant.
     *
     * @throws AuthorizationException Quando tenta atribuir super-admin sem permissão.
     */
    public function update(string $id, AuthUserDTO $dto): AuthUser
    {
        $this->guardSuperAdminAssignment($dto);

        return DB::transaction(function () use ($id, $dto) {
            $user = $this->authUserRepository->findOrFail($id);
            $data = $dto->toPersistenceArray();

            if (isset($data['password'])) {
                $data['password'] = Hash::make((string) $data['password']);
            }

            $user->fill($data);
            $this->authUserRepository->save($user);

            if ($dto->roles !== []) {
                $user->syncRoles($dto->roles);
            } elseif ($dto->role) {
                $user->syncRoles([$dto->role]);
            }

            return $user->load(['roles', 'tenant']);
        });
    }

    /**
     * Sincronizar perfis do usuário com proteção centralizada de super-admin.
     *
     * @param  string  $id  UUID do usuário.
     * @param  list<string>  $roles  Perfis a sincronizar.
     * @return AuthUser Usuário atualizado com roles e tenant.
     *
     * @throws AuthorizationException Quando tenta atribuir super-admin sem permissão.
     */
    public function syncRoles(string $id, array $roles): AuthUser
    {
        $this->guardSuperAdminRoles($roles);

        $user = $this->authUserRepository->findOrFail($id);
        $user->syncRoles($roles);

        return $user->load(['roles', 'tenant']);
    }

    /**
     * Impede que um usuário não-super-admin atribua o perfil super-admin.
     *
     * @param  AuthUserDTO  $dto  DTO com os roles a serem atribuídos.
     *
     * @throws AuthorizationException Quando tenta atribuir super-admin sem permissão.
     */
    private function guardSuperAdminAssignment(AuthUserDTO $dto): void
    {
        $rolesToAssign = $dto->roles !== [] ? $dto->roles : ($dto->role ? [$dto->role] : []);

        $this->guardSuperAdminRoles($rolesToAssign);
    }

    /**
     * @param  list<string>  $rolesToAssign
     *
     * @throws AuthorizationException Quando tenta atribuir super-admin sem permissão.
     */
    private function guardSuperAdminRoles(array $rolesToAssign): void
    {
        if (! auth()->check()) {
            return;
        }

        $currentUser = auth()->user();

        if ($currentUser->isSuperAdmin()) {
            return;
        }

        if ($rolesToAssign === []) {
            return;
        }

        if (in_array(AuthRole::ADMINISTRADOR_NAME, $rolesToAssign, true)) {
            throw new AuthorizationException('Não é permitido atribuir o perfil super-admin.');
        }
    }

    /**
     * Remover usuário (soft-delete).
     *
     * @param  string  $id  UUID do usuário.
     *
     * @throws AuthorizationException Quando o usuário é super-admin.
     */
    public function delete(string $id): void
    {
        $user = $this->authUserRepository->findOrFail($id);

        if ($user->isSuperAdmin()) {
            throw new AuthorizationException('Não é permitido remover um usuário super-admin.');
        }

        $this->authUserRepository->delete($user);
    }

    /**
     * Alternar status ativo/inativo do usuário.
     *
     * @param  string  $id  UUID do usuário.
     * @return AuthUser Usuário com status atualizado.
     *
     * @throws AuthorizationException Quando o usuário é super-admin.
     */
    public function toggleActive(string $id): AuthUser
    {
        $user = $this->authUserRepository->findOrFail($id);

        if ($user->isSuperAdmin()) {
            throw new AuthorizationException('Não é permitido alterar o status de um usuário super-admin.');
        }

        $user->is_active = ! $user->is_active;
        $this->authUserRepository->save($user);

        return $user->fresh(['roles', 'tenant']);
    }

    /**
     * @return array{avatar_url:string}
     */
    public function updateAvatar(string $id, UploadedFile $image): array
    {
        $user = $this->authUserRepository->findOrFail($id);

        return $this->avatarManager->update($user, $image);
    }

    /**
     * @return array{avatar_url:null}
     */
    public function deleteAvatar(string $id): array
    {
        $user = $this->authUserRepository->findOrFail($id);

        return $this->avatarManager->remove($user);
    }
}
