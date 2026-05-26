<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redireciona para a tela de consentimento do Google OAuth.
 *
 * @see https://laravel.com/docs/12.x/socialite
 */
final class AuthGoogleRedirectAction
{
    /**
     * Gera e retorna o redirect para o Google OAuth consent screen.
     *
     * O intent é codificado no state OAuth para que o callback saiba o contexto
     * sem depender de sessão (fluxo stateless).
     *
     * @param  string  $intent  'login' | 'signup' | 'link'
     * @param  string|null  $userId  Obrigatório quando intent='link'
     */
    public function execute(string $intent = 'login', ?string $userId = null): RedirectResponse
    {
        $stateData = ['intent' => $intent];

        if ($intent === 'link' && $userId !== null) {
            $stateData['user_id'] = $userId;
        }

        $state = json_encode($stateData);

        /** @var RedirectResponse */
        return Socialite::driver('google')->stateless()->with(['state' => $state])->redirect();
    }
}
