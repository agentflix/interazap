<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthSignupAction;
use Domain\Auth\Http\Requests\AuthSignupRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

/**
 * Controller de signup público — cria tenant + usuário + plano trial.
 *
 * Rate-limited via throttle:public (10 req/min/IP) para prevenir abusos.
 */
final class AuthSignupController extends BaseController
{
    public function __construct(private readonly AuthSignupAction $authSignupAction) {}

    /**
     * Processar signup público com email e senha.
     *
     * @param  AuthSignupRequest  $request  Payload validado (name, email, password, accept_terms)
     * @return JsonResponse Sessão autenticada com token + plano trial
     */
    public function store(AuthSignupRequest $request): JsonResponse
    {
        $session = $this->authSignupAction->execute([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        return $this->success($session->toArray(), 'Cadastro realizado com sucesso', 201);
    }
}
