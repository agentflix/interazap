<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\Models\AuthUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Processa o callback do Google OAuth e emite sessão autenticada.
 *
 * Fluxo:
 * - Se `auth_users.provider_id` bater → login direto
 * - Se email existir mas sem provider → vincula provider + emite token
 * - Se novo → chama `AuthSignupAction` com email verificado
 *
 * Race condition de concorrência para o mesmo email → resolvida via lock por email.
 */
final class AuthGoogleCallbackAction
{
    public function __construct(
        private readonly AuthSignupAction $signupAction,
        private readonly AuthLoginActions $loginActions,
    ) {}

    /**
     * @throws \Throwable se Socialite ou DB transação falharem
     */
    public function execute(): AuthSessionDTO
    {
        /** @var SocialiteUser $googleUser */
        $googleUser = Socialite::driver('google')->user();

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();
        $name = (string) ($googleUser->getName() ?? $email);

        // Distributed lock por email para evitar race condition de concorrência
        $lockKey = "google-oauth-lock:{$email}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($email, $googleId, $name): AuthSessionDTO {
            return DB::transaction(function () use ($email, $googleId, $name): AuthSessionDTO {
                // Cenário 1: provider já vinculado — login direto
                $existingByProvider = AuthUser::query()
                    ->where('provider', 'google')
                    ->where('provider_id', $googleId)
                    ->first();

                if ($existingByProvider) {
                    Log::info('auth.google.login.existing_provider', [
                        'user_id' => $existingByProvider->id,
                        'provider_id' => $googleId,
                    ]);

                    return $this->loginActions->createSession($existingByProvider, includeToken: true);
                }

                // Cenário 2: email existe → vincula provider
                $existingByEmail = AuthUser::query()
                    ->where('email', $email)
                    ->first();

                if ($existingByEmail) {
                    $existingByEmail->provider = 'google';
                    $existingByEmail->provider_id = $googleId;

                    // Marcar email como verificado se ainda não estava
                    if (! $existingByEmail->email_verified_at) {
                        $existingByEmail->email_verified_at = now();
                    }

                    $existingByEmail->save();

                    Log::info('auth.google.login.linked_provider', [
                        'user_id' => $existingByEmail->id,
                        'email' => $email,
                    ]);

                    return $this->loginActions->createSession($existingByEmail, includeToken: true);
                }

                // Cenário 3: usuário novo → signup com trial
                Log::info('auth.google.signup', ['email' => $email]);

                return $this->signupAction->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => null,
                    'email_verified_at' => now()->toIso8601String(),
                    'provider' => 'google',
                    'provider_id' => $googleId,
                ]);
            });
        });
    }
}
