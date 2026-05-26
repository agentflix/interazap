<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Caso de uso para revogação lógica de token de dispositivo.
 */
final class RevokeDeviceTokenAction
{
    /**
     * Revoga logicamente o token de dispositivo do usuário.
     *
     * Define revoked_at com a data/hora atual. Lança exceção se o token
     * não existir ou não pertencer ao usuário autenticado.
     *
     * @param  AuthUser  $user  Usuário solicitante.
     * @param  string  $deviceTokenId  UUID do token a revogar.
     *
     * @throws AuthorizationException Se o token não existir ou pertencer a outro usuário.
     */
    public function execute(AuthUser $user, string $deviceTokenId): void
    {
        /** @var AuthDeviceToken|null $deviceToken */
        $deviceToken = AuthDeviceToken::query()->find($deviceTokenId);

        if ($deviceToken === null || (string) $deviceToken->user_id !== (string) $user->id) {
            throw new AuthorizationException('Você não pode revogar este token de dispositivo.');
        }

        $deviceToken->revoked_at = Carbon::now();
        $deviceToken->save();
    }
}
