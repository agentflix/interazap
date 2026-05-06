<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthLoginActions;
use Domain\Auth\DTOs\AuthLoginDTO;
use Domain\Auth\DTOs\AuthLoginTwoFactorDTO;
use Domain\Auth\DTOs\AuthTwoFactorChallengeDTO;
use Domain\Auth\Http\Requests\AuthLoginRequest;
use Domain\Auth\Http\Requests\AuthLoginTwoFactorRequest;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints responsáveis por login, sessão e menu.
 *
 * @see AuthLoginActions
 */
final class AuthLoginController extends BaseController
{
    /**
     * @param  AuthLoginActions  $authLoginActions  Ação de autenticação injetada.
     */
    public function __construct(private readonly AuthLoginActions $authLoginActions) {}

    /**
     * Realizar login com credenciais email/senha.
     *
     * @param  AuthLoginRequest  $request  Requisição com email e senha.
     * @return JsonResponse Sessão autenticada ou desafio 2FA.
     */
    public function login(AuthLoginRequest $request): JsonResponse
    {
        $result = $this->authLoginActions->login(AuthLoginDTO::fromRequest($request));

        if ($result instanceof AuthTwoFactorChallengeDTO) {
            return $this->success($result->toArray(), '2FA requerido');
        }

        return $this->success($result->toArray(), 'Login realizado com sucesso');
    }

    /**
     * Realizar login com código 2FA.
     *
     * @param  AuthLoginTwoFactorRequest  $request  Requisição com email e código 2FA.
     * @return JsonResponse Sessão autenticada.
     */
    public function loginWith2FA(AuthLoginTwoFactorRequest $request): JsonResponse
    {
        $session = $this->authLoginActions->loginWithTwoFactor(AuthLoginTwoFactorDTO::fromRequest($request));

        return $this->success($session->toArray(), 'Login realizado com sucesso');
    }

    /**
     * Obter dados do usuário autenticado.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Dados da sessão atual.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $session = $this->authLoginActions->me($user);

        return $this->success($session->toArray(), 'Perfil carregado');
    }

    /**
     * Encerrar sessão do usuário.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Confirmação de logout.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $this->authLoginActions->logout($user);

        return $this->success(null, 'Logout realizado');
    }

    /**
     * Obter menu de navegação do usuário.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Menu estruturado.
     */
    public function getMenu(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $menu = array_map(static fn ($item) => $item->toArray(), $this->authLoginActions->getMenu($user));

        return $this->success(['menu' => $menu], 'Menu carregado');
    }

    /**
     * Renovar token de acesso.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Nova sessão com token renovado.
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $session = $this->authLoginActions->refresh($user);

        return $this->success($session->toArray(), 'Token renovado');
    }

    /**
     * Encerrar a impersonação e retornar à sessão original do super admin.
     *
     * @param  Request  $request  Requisição atual.
     * @return JsonResponse Nova sessão do super admin original.
     */
    public function stopImpersonating(Request $request): JsonResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();
        $session = $this->authLoginActions->stopImpersonating($user);

        return $this->success($session->toArray(), 'Impersonação encerrada');
    }
}
