<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthTwoFactorActions;
use Domain\Auth\Http\Requests\AuthTwoFactorPasswordRequest;
use Domain\Auth\Http\Requests\AuthTwoFactorValidateRequest;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de configuração e gestão do 2FA.
 *
 * @see AuthTwoFactorActions
 */
final class AuthTwoFactorController extends BaseController
{
    /**
     * @param  AuthTwoFactorActions  $twoFactorActions  Ação de gestão 2FA.
     */
    public function __construct(private readonly AuthTwoFactorActions $twoFactorActions) {}

    /**
     * Obter status atual do 2FA do usuário.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Status do 2FA.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return $this->success($this->twoFactorActions->getStatus($user));
    }

    /**
     * Iniciar configuração do 2FA.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Dados para setup (QR code, secret).
     */
    public function setup(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return $this->success($this->twoFactorActions->setup($user), '2FA setup iniciado');
    }

    /**
     * Validar código e ativar 2FA.
     *
     * @param  AuthTwoFactorValidateRequest  $request  Requisição com código de validação.
     * @return JsonResponse Confirmação de ativação.
     */
    public function validateSetup(AuthTwoFactorValidateRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return $this->success(
            $this->twoFactorActions->validate($user, (string) $request->input('code')),
            '2FA ativado com sucesso'
        );
    }

    /**
     * Desativar 2FA com senha de confirmação.
     *
     * @param  AuthTwoFactorPasswordRequest  $request  Requisição com senha.
     * @return JsonResponse Confirmação.
     */
    public function disable(AuthTwoFactorPasswordRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $this->twoFactorActions->disable($user, (string) $request->input('password'));

        return $this->success(null, '2FA desativado com sucesso');
    }

    /**
     * Regenerar códigos de recuperação.
     *
     * @param  AuthTwoFactorPasswordRequest  $request  Requisição com senha.
     * @return JsonResponse Novos códigos de recuperação.
     */
    public function regenerate(AuthTwoFactorPasswordRequest $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $codes = $this->twoFactorActions->regenerateRecoveryCodes($user, (string) $request->input('password'));

        return $this->success(['recovery_codes' => $codes], 'Códigos de recuperação regenerados');
    }
}
