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
     * Escopos solicitados: email, profile (padrão do driver Google).
     */
    public function execute(): RedirectResponse
    {
        /** @var RedirectResponse */
        return Socialite::driver('google')->redirect();
    }
}
