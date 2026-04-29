<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\RegisterDeviceTokenDTO;
use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Carbon;

/**
 * Caso de uso para registrar/reativar token de dispositivo.
 */
final class RegisterDeviceTokenAction
{
    public function execute(AuthUser $user, RegisterDeviceTokenDTO $dto): AuthDeviceToken
    {
        $now = Carbon::now();

        /** @var AuthDeviceToken $deviceToken */
        $deviceToken = AuthDeviceToken::query()->firstOrNew([
            'tenant_id' => (string) $user->tenant_id,
            'platform' => $dto->platform,
            'token' => $dto->token,
        ]);

        $deviceToken->user_id = (string) $user->id;
        $deviceToken->device_name = $dto->deviceName;
        $deviceToken->last_active_at = $now;
        $deviceToken->revoked_at = null;
        $deviceToken->save();

        return $deviceToken->refresh();
    }
}
