<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\RegisterDeviceTokenAction;
use Domain\Auth\Actions\RevokeDeviceTokenAction;
use Domain\Auth\DTOs\RegisterDeviceTokenDTO;
use Domain\Auth\Http\Requests\RegisterDeviceTokenRequest;
use Domain\Auth\Http\Resources\AuthDeviceTokenResource;
use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints para gestão de tokens de dispositivo.
 */
final class DeviceTokenController extends BaseController
{
    public function __construct(
        private readonly RegisterDeviceTokenAction $registerDeviceTokenAction,
        private readonly RevokeDeviceTokenAction $revokeDeviceTokenAction,
    ) {}

    public function register(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AuthDeviceToken::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $alreadyRegistered = AuthDeviceToken::query()
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('platform', (string) $request->string('platform'))
            ->where('token', (string) $request->string('token'))
            ->exists();

        $deviceToken = $this->registerDeviceTokenAction->execute($user, RegisterDeviceTokenDTO::fromRequest($request));

        if ($alreadyRegistered) {
            return $this->success(new AuthDeviceTokenResource($deviceToken), 'Token de dispositivo reativado');
        }

        return $this->created(new AuthDeviceTokenResource($deviceToken), 'Token de dispositivo registrado');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize('viewAny', AuthDeviceToken::class);

        /** @var AuthUser $user */
        $user = $request->user();

        try {
            $this->revokeDeviceTokenAction->execute($user, $id);
        } catch (AuthorizationException) {
            return $this->forbidden('Você não pode revogar este token de dispositivo.');
        }

        return $this->noContent();
    }
}
