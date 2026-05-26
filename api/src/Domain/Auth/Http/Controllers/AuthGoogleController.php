<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthGoogleCallbackAction;
use Domain\Auth\Actions\AuthGoogleRedirectAction;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Controller dos endpoints Google OAuth.
 *
 * GET /auth/google/redirect → redireciona para consent screen do Google
 * GET /auth/google/callback → processa callback, cria/loga user, redireciona app com token
 */
final class AuthGoogleController extends BaseController
{
    public function __construct(
        private readonly AuthGoogleRedirectAction $redirectAction,
        private readonly AuthGoogleCallbackAction $callbackAction,
    ) {}

    /**
     * Redirecionar para tela de consentimento do Google.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return $this->redirectAction->execute();
    }

    /**
     * Processar callback OAuth — criar/logar user e redirecionar app com token.
     *
     * O app (SPA/mobile) é configurado via env `GOOGLE_APP_REDIRECT_URL`.
     * Em caso de erro, redireciona com `?error=...` para o app tratar.
     */
    public function callback(): RedirectResponse
    {
        $appRedirectUrl = rtrim(
            (string) config('services.google.app_redirect_url', config('app.frontend_url', 'http://localhost:4200').'/auth/google-callback'),
            '/'
        );

        try {
            $session = $this->callbackAction->execute();

            $token = $session->token;

            Log::info('auth.google.callback.success', [
                'user_id' => $session->user->id ?? null,
            ]);

            return redirect("{$appRedirectUrl}?token=".urlencode($token));
        } catch (\Throwable $e) {
            Log::error('auth.google.callback.error', [
                'error' => $e->getMessage(),
            ]);

            return redirect("{$appRedirectUrl}?error=oauth_failed");
        }
    }
}
