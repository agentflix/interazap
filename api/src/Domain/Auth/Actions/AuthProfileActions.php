<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthProfileDTO;
use Domain\Auth\DTOs\AuthUpdatePasswordDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Services\AuthAvatarManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso relacionados ao perfil do usuário autenticado.
 */
final class AuthProfileActions
{
    public function __construct(private readonly AuthAvatarManager $avatarManager) {}

    /**
     * Retorna o model do usuário autenticado.
     *
     * A serialização deve ser feita pelo controller/resource.
     */
    public function get(AuthUser $user): AuthUser
    {
        return $user;
    }

    /**
     * Atualiza o perfil do usuário autenticado.
     */
    public function update(AuthUser $user, AuthProfileDTO $dto): AuthUser
    {
        $user->update($dto->toArray());

        return $this->get($user->refresh());
    }

    /**
     * @throws ValidationException
     */
    public function updatePassword(AuthUser $user, AuthUpdatePasswordDTO $dto): void
    {
        if (! Hash::check($dto->currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A senha atual está incorreta.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($dto->password),
        ]);
    }

    /**
     * @return array{avatar_url:string}
     */
    public function updateAvatar(AuthUser $user, UploadedFile $image): array
    {
        return $this->avatarManager->update($user, $image);
    }

    /**
     * @return array{avatar_url:null}
     */
    public function deleteAvatar(AuthUser $user): array
    {
        return $this->avatarManager->remove($user);
    }
}
