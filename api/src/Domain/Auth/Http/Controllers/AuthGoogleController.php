<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Controllers;

use Domain\Auth\Actions\AuthGoogleCallbackAction;
use Domain\Auth\Actions\AuthGoogleRedirectAction;
use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Controller dos endpoints Google OAuth.
 *
 * GET /auth/google/redirect?intent=login|signup → redireciona para consent screen (público)
 * GET /auth/google/link                         → redireciona para consent screen (autenticado)
 * GET /auth/google/callback                     → processa callback, bifurca por intent
 */
final class AuthGoogleController extends BaseController
{
    public function __construct(
        private readonly AuthGoogleRedirectAction $redirectAction,
        private readonly AuthGoogleCallbackAction $callbackAction,
    ) {}

    /**
     * Redirecionar para tela de consentimento — intent login ou signup.
     */
    public function redirect(Request $request): SymfonyRedirectResponse
    {
        $intent = in_array($request->query('intent'), ['login', 'signup'], true)
            ? (string) $request->query('intent')
            : 'login';

        return $this->redirectAction->execute($intent);
    }

    /**
     * Redirecionar para tela de consentimento com intent=link.
     *
     * Requer autenticação via auth:sanctum (definido na rota).
     */
    public function redirectForLink(Request $request): SymfonyRedirectResponse
    {
        /** @var AuthUser $user */
        $user = $request->user();

        return $this->redirectAction->execute('link', $user->id);
    }

    /**
     * Processar callback OAuth — bifurca por intent e redireciona o app.
     *
     * O app (SPA/mobile) é configurado via env `GOOGLE_APP_REDIRECT_URL`.
     * Em caso de erro, redireciona com `?error=...` para o app tratar.
     */
    public function callback(Request $request): RedirectResponse
    {
        $appRedirectUrl = rtrim(
            (string) config('services.google.app_redirect_url', config('app.frontend_url', 'http://localhost:4200').'/auth/google-callback'),
            '/'
        );

        try {
            $result = $this->callbackAction->execute($request);

            // Error markers returned as string by the action
            if (is_string($result)) {
                return match ($result) {
                    'linked' => redirect("{$appRedirectUrl}?linked=true"),
                    default  => redirect("{$appRedirectUrl}?error={$result}"),
                };
            }

            /** @var AuthSessionDTO $session */
            $session = $result;
            $token   = $session->token;

            Log::info('auth.google.callback.success', [
                'user_id' => $session->user->id ?? null,
            ]);

            return redirect("{$appRedirectUrl}?token=".urlencode((string) $token));
        } catch (\Throwable $e) {
            Log::error('auth.google.callback.error', [
                'error' => $e->getMessage(),
            ]);

            return redirect("{$appRedirectUrl}?error=oauth_failed");
        }
    }
}
