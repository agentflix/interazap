<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthPasswordResetActions;
use Domain\Auth\Http\Requests\AuthForgotPasswordRequest;
use Domain\Auth\Http\Requests\AuthResetPasswordRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Fluxo de recuperação de senha (forgot/reset).
 *
 * @see AuthPasswordResetActions
 */
final class AuthPasswordResetController extends BaseController
{
    /**
     * @param  AuthPasswordResetActions  $passwordResetActions  Ação de reset de senha.
     */
    public function __construct(private readonly AuthPasswordResetActions $passwordResetActions) {}

    /**
     * Solicitar link de redefinição de senha.
     *
     * @param  AuthForgotPasswordRequest  $request  Requisição com email.
     * @return JsonResponse Status da operação.
     */
    public function forgot(AuthForgotPasswordRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $status = $this->passwordResetActions->sendResetLink($payload['email']);

        return $this->success(['status' => $status], 'Link de redefinição enviado');
    }

    /**
     * Redefinir senha com token válido.
     *
     * @param  AuthResetPasswordRequest  $request  Requisição com token e nova senha.
     * @return JsonResponse Status da operação.
     */
    public function reset(AuthResetPasswordRequest $request): JsonResponse
    {
        $status = $this->passwordResetActions->reset($request->validated());

        return $this->success(['status' => $status], 'Senha atualizada com sucesso');
    }
}
