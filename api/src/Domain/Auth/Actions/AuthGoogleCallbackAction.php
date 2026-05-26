<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\Models\AuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

/**
 * Processa o callback do Google OAuth bifurcando por intent.
 *
 * Intent codificado no state OAuth (JSON):
 * - login  → exige provider já vinculado; rejeita autos-link silencioso
 * - signup → exige email inédito; cria conta com trial
 * - link   → exige user autenticado (user_id no state); vincula provider ao user
 *
 * Race condition para o mesmo email → resolvida via lock por email.
 */
final class AuthGoogleCallbackAction
{
    public function __construct(
        private readonly AuthSignupAction $signupAction,
        private readonly AuthLoginActions $loginActions,
    ) {}

    /**
     * @param  Request  $request  Necessário para ler o state OAuth do query string
     *
     * @throws \Throwable se Socialite ou DB transação falharem
     */
    public function execute(Request $request): AuthSessionDTO|string
    {
        /** @var SocialiteUser $googleUser */
        // @phpstan-ignore-next-line — stateless() existe em AbstractProvider mas não está na interface Contract
        $googleUser = Socialite::driver('google')->stateless()->user();

        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();
        $name = (string) ($googleUser->getName() ?? $email);

        $rawState = $request->query('state', '{}');
        $stateData = json_decode((string) $rawState, true) ?? [];
        $intent = $stateData['intent'] ?? 'login';

        $lockKey = "google-oauth-lock:{$email}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($email, $googleId, $name, $intent, $stateData): AuthSessionDTO|string {
            return DB::transaction(function () use ($email, $googleId, $name, $intent, $stateData): AuthSessionDTO|string {
                return match ($intent) {
                    'signup' => $this->handleSignup($email, $googleId, $name),
                    'link' => $this->handleLink($email, $googleId, $stateData),
                    default => $this->handleLogin($email, $googleId),
                };
            });
        });
    }

    private function handleLogin(string $email, string $googleId): AuthSessionDTO|string
    {
        $byProvider = AuthUser::query()
            ->where('provider', 'google')
            ->where('provider_id', $googleId)
            ->first();

        if ($byProvider) {
            Log::info('auth.google.login.existing_provider', [
                'user_id' => $byProvider->id,
                'provider_id' => $googleId,
            ]);

            return $this->loginActions->createSession($byProvider, includeToken: true);
        }

        $byEmail = AuthUser::query()->where('email', $email)->first();

        // Email existe mas sem provider Google → não auto-vincula no fluxo de login
        if ($byEmail) {
            return 'not_linked';
        }

        return 'not_registered';
    }

    private function handleSignup(string $email, string $googleId, string $name): AuthSessionDTO|string
    {
        $existing = AuthUser::query()->where('email', $email)->first();

        if ($existing) {
            return 'already_registered';
        }

        Log::info('auth.google.signup', ['email' => $email]);

        return $this->signupAction->execute([
            'name' => $name,
            'email' => $email,
            'password' => null,
            'email_verified_at' => now()->toIso8601String(),
            'provider' => 'google',
            'provider_id' => $googleId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stateData
     */
    private function handleLink(string $email, string $googleId, array $stateData): string
    {
        $userId = $stateData['user_id'] ?? null;

        if (! $userId) {
            return 'unauthenticated';
        }

        /** @var AuthUser|null $currentUser */
        $currentUser = AuthUser::query()->find($userId);

        if (! $currentUser) {
            return 'unauthenticated';
        }

        // Verifica se o provider_id já está vinculado a OUTRO user
        $takenByOther = AuthUser::query()
            ->where('provider', 'google')
            ->where('provider_id', $googleId)
            ->where('id', '!=', $userId)
            ->exists();

        if ($takenByOther) {
            return 'already_linked';
        }

        $currentUser->provider = 'google';
        $currentUser->provider_id = $googleId;

        if (! $currentUser->email_verified_at) {
            $currentUser->email_verified_at = now();
        }

        $currentUser->save();

        Log::info('auth.google.link.success', [
            'user_id' => $currentUser->id,
            'provider_id' => $googleId,
        ]);

        // Vinculação não emite novo token — retorna marcador para o controller
        return 'linked';
    }
}
