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
     * @throws AuthorizationException
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
